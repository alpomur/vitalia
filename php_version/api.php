<?php
/**
 * Vitalia - API Endpoint
 * Tüm API işlemleri bu dosyadan yönetilir
 * Cache, Rate Limiting, FAQ desteği ile
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/languages.php';

header('Content-Type: application/json; charset=utf-8');

session_start();

// Guest ID
if (!isset($_SESSION['guest_id'])) {
    $_SESSION['guest_id'] = 'guest_' . bin2hex(random_bytes(6));
}

$pdo = getDbConnection();
$action = $_GET['action'] ?? '';
$userId = $_SESSION['guest_id'];
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// Kullanıcıyı oluştur (yoksa)
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
if (!$stmt->fetch()) {
    $stmt = $pdo->prepare("INSERT INTO users (user_id, auth_provider, status, preferred_language) VALUES (?, 'guest', 'active', 'tr')");
    $stmt->execute([$userId]);
}

// Bugünün tarihi
$today = date('Y-m-d');

switch ($action) {
    
    // ===== CHAT =====
    case 'chat_send':
        $input = json_decode(file_get_contents('php://input'), true);
        $message = trim($input['message'] ?? '');
        $language = $input['language'] ?? 'tr';
        
        if (empty($message)) {
            echo json_encode(['error' => 'Message required']);
            exit;
        }
        
        // Rate limit kontrolü
        if (!checkRateLimit($pdo, $clientIp, getSetting($pdo, 'rate_limit_per_minute', 10))) {
            $t = $GLOBALS['translations'][$language] ?? $GLOBALS['translations']['tr'];
            echo json_encode([
                'response' => $t['rateLimit'],
                'message_type' => 'rate_limit',
                'user_id' => $userId
            ]);
            exit;
        }
        
        // Günlük limit kontrolü
        if (!checkDailyLimit($pdo, $userId, true)) {
            $t = $GLOBALS['translations'][$language] ?? $GLOBALS['translations']['tr'];
            echo json_encode([
                'response' => $t['dailyLimit'],
                'message_type' => 'daily_limit',
                'user_id' => $userId
            ]);
            exit;
        }
        
        // Mesajı sınıflandır
        $messageType = classifyMessage($message, $language);
        
        // Mesajı kaydet
        $msgId = 'msg_' . bin2hex(random_bytes(6));
        $stmt = $pdo->prepare("INSERT INTO chat_messages (message_id, user_id, role, message_type, message_text) VALUES (?, ?, 'user', ?, ?)");
        $stmt->execute([$msgId, $userId, $messageType, $message]);
        
        // Kapsam dışı kontrolü
        if ($messageType === 'out_of_scope') {
            $t = $GLOBALS['translations'][$language] ?? $GLOBALS['translations']['tr'];
            $response = $t['outOfScope'];
            saveAssistantMessage($pdo, $userId, 'out_of_scope', $response);
            echo json_encode(['response' => $response, 'message_type' => 'out_of_scope', 'user_id' => $userId]);
            exit;
        }
        
        // FAQ kontrolü
        $faqResponse = getFaqResponse($pdo, $message);
        if ($faqResponse) {
            saveAssistantMessage($pdo, $userId, 'faq', $faqResponse);
            echo json_encode(['response' => $faqResponse, 'message_type' => 'faq', 'user_id' => $userId, 'cached' => true]);
            exit;
        }
        
        // Cache kontrolü
        $cachedResponse = getCachedResponse($pdo, $message);
        if ($cachedResponse) {
            saveAssistantMessage($pdo, $userId, 'faq', $cachedResponse);
            echo json_encode(['response' => $cachedResponse, 'message_type' => 'faq', 'user_id' => $userId, 'cached' => true]);
            exit;
        }
        
        // OpenAI API çağır
        $response = callOpenAI($message, $language);
        
        // Başarılı yanıtı cache'e kaydet
        if ($response && $messageType !== 'personal_complex') {
            cacheResponse($pdo, $message, $response, $messageType);
        }
        
        // Token kullanımını logla
        $inputTokens = intval(mb_strlen($message) / 4);
        $outputTokens = intval(mb_strlen($response) / 4);
        logOpenAIUsage($pdo, $userId, $inputTokens, $outputTokens);
        
        // Yanıtı kaydet
        saveAssistantMessage($pdo, $userId, $messageType, $response);
        
        // Login prompt kontrolü
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM chat_messages WHERE user_id = ? AND role = 'user'");
        $stmt->execute([$userId]);
        $msgCount = $stmt->fetch()['count'];
        $threshold = getSetting($pdo, 'anon_message_threshold', 8);
        $promptLogin = $msgCount >= $threshold;
        
        echo json_encode([
            'response' => $response,
            'message_type' => $messageType,
            'user_id' => $userId,
            'prompt_login' => $promptLogin
        ]);
        break;
        
    case 'chat_history':
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
        
        $stmt = $pdo->prepare("
            SELECT message_id as id, user_id, role, message_type, message_text, created_at 
            FROM chat_messages 
            WHERE user_id = ? 
            ORDER BY created_at ASC 
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['messages' => $messages, 'user_id' => $userId]);
        break;
        
    // ===== QUICK ACTIONS =====
    case 'quick_action':
        $input = json_decode(file_get_contents('php://input'), true);
        $actionType = $input['action_type'] ?? '';
        $value = $input['value'] ?? null;
        
        switch ($actionType) {
            case 'water':
                $amount = $value ?? intval(getSetting($pdo, 'glass_ml', 250));
                $stmt = $pdo->prepare("
                    INSERT INTO daily_logs (log_id, user_id, log_date, water_ml) 
                    VALUES (?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE water_ml = water_ml + ?
                ");
                $logId = 'log_' . bin2hex(random_bytes(6));
                $stmt->execute([$logId, $userId, $today, $amount, $amount]);
                
                $stmt = $pdo->prepare("SELECT water_ml FROM daily_logs WHERE user_id = ? AND log_date = ?");
                $stmt->execute([$userId, $today]);
                $log = $stmt->fetch();
                $totalWater = $log['water_ml'] ?? 0;
                
                // Hedef kontrolü - motivasyon mesajı
                $profile = getProfile($pdo, $userId);
                $target = calculateWaterTarget($profile['weight_kg'] ?? 70);
                $motivation = null;
                if ($totalWater >= $target) {
                    $motivation = getMotivationMessage($pdo, 'water_goal', $_SESSION['lang'] ?? 'tr');
                }
                
                echo json_encode([
                    'success' => true, 
                    'total_water_ml' => $totalWater,
                    'target_ml' => $target,
                    'motivation' => $motivation
                ]);
                break;
                
            case 'steps':
                $level = $value ?? 'medium';
                $stmt = $pdo->prepare("
                    INSERT INTO daily_logs (log_id, user_id, log_date, steps_level) 
                    VALUES (?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE steps_level = ?
                ");
                $logId = 'log_' . bin2hex(random_bytes(6));
                $stmt->execute([$logId, $userId, $today, $level, $level]);
                echo json_encode(['success' => true, 'steps_level' => $level]);
                break;
                
            case 'workout':
                $done = $value ? 1 : 0;
                $stmt = $pdo->prepare("
                    INSERT INTO daily_logs (log_id, user_id, log_date, workout_done) 
                    VALUES (?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE workout_done = ?
                ");
                $logId = 'log_' . bin2hex(random_bytes(6));
                $stmt->execute([$logId, $userId, $today, $done, $done]);
                
                $motivation = null;
                if ($done) {
                    $motivation = getMotivationMessage($pdo, 'workout_done', $_SESSION['lang'] ?? 'tr');
                }
                
                echo json_encode(['success' => true, 'workout_done' => (bool)$done, 'motivation' => $motivation]);
                break;
                
            case 'weight':
                $weight = floatval($value);
                if ($weight <= 0) {
                    echo json_encode(['error' => 'Invalid weight']);
                    exit;
                }
                
                $logId = 'wlog_' . bin2hex(random_bytes(6));
                $stmt = $pdo->prepare("
                    INSERT INTO weight_logs (log_id, user_id, log_date, weight_kg) 
                    VALUES (?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE weight_kg = ?
                ");
                $stmt->execute([$logId, $userId, $today, $weight, $weight]);
                
                $stmt = $pdo->prepare("
                    INSERT INTO profiles (user_id, weight_kg) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE weight_kg = ?
                ");
                $stmt->execute([$userId, $weight, $weight]);
                
                echo json_encode(['success' => true, 'weight_kg' => $weight]);
                break;
                
            default:
                echo json_encode(['error' => 'Invalid action type']);
        }
        break;
        
    case 'log_today':
        $stmt = $pdo->prepare("SELECT * FROM daily_logs WHERE user_id = ? AND log_date = ?");
        $stmt->execute([$userId, $today]);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $profile = getProfile($pdo, $userId);
        $waterTarget = calculateWaterTarget($profile['weight_kg'] ?? 70);
        
        echo json_encode([
            'log' => $log ?: ['water_ml' => 0, 'steps_level' => null, 'workout_done' => false, 'mood' => null],
            'targets' => [
                'water_ml' => $waterTarget,
                'glass_ml' => intval(getSetting($pdo, 'glass_ml', 250))
            ]
        ]);
        break;
        
    case 'log_history':
        $days = isset($_GET['days']) ? intval($_GET['days']) : 7;
        
        $stmt = $pdo->prepare("SELECT * FROM daily_logs WHERE user_id = ? ORDER BY log_date DESC LIMIT ?");
        $stmt->execute([$userId, $days]);
        $dailyLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("SELECT * FROM weight_logs WHERE user_id = ? ORDER BY log_date DESC LIMIT ?");
        $stmt->execute([$userId, $days]);
        $weightLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['daily_logs' => $dailyLogs, 'weight_logs' => $weightLogs]);
        break;
        
    // ===== PROFILE =====
    case 'profile_get':
        $profile = getProfile($pdo, $userId);
        echo json_encode(['profile' => $profile]);
        break;
        
    case 'profile_save':
        $input = json_decode(file_get_contents('php://input'), true);
        
        $age = isset($input['age']) ? intval($input['age']) : null;
        $height = isset($input['height_cm']) ? intval($input['height_cm']) : null;
        $weight = isset($input['weight_kg']) ? floatval($input['weight_kg']) : null;
        $goal = $input['goal'] ?? null;
        
        $stmt = $pdo->prepare("
            INSERT INTO profiles (user_id, age, height_cm, weight_kg, goal) 
            VALUES (?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
                age = COALESCE(?, age), 
                height_cm = COALESCE(?, height_cm), 
                weight_kg = COALESCE(?, weight_kg), 
                goal = COALESCE(?, goal)
        ");
        $stmt->execute([$userId, $age, $height, $weight, $goal, $age, $height, $weight, $goal]);
        
        echo json_encode(['success' => true]);
        break;
        
    // ===== FAQ =====
    case 'faq_list':
        $topic = $_GET['topic'] ?? null;
        
        $sql = "SELECT * FROM qa_faq WHERE is_active = 1";
        $params = [];
        
        if ($topic) {
            $sql .= " AND topic = ?";
            $params[] = $topic;
        }
        
        $stmt = $pdo->prepare($sql . " ORDER BY id");
        $stmt->execute($params);
        $faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['faqs' => $faqs]);
        break;
        
    default:
        echo json_encode(['error' => 'Unknown action', 'available_actions' => [
            'chat_send', 'chat_history', 'quick_action', 'log_today', 'log_history',
            'profile_get', 'profile_save', 'faq_list'
        ]]);
}

// ===== YARDIMCI FONKSİYONLAR =====

function saveAssistantMessage($pdo, $userId, $messageType, $text) {
    $msgId = 'msg_' . bin2hex(random_bytes(6));
    $stmt = $pdo->prepare("INSERT INTO chat_messages (message_id, user_id, role, message_type, message_text) VALUES (?, ?, 'assistant', ?, ?)");
    $stmt->execute([$msgId, $userId, $messageType, $text]);
}

function getProfile($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function getMotivationMessage($pdo, $triggerType, $lang = 'tr') {
    $stmt = $pdo->prepare("SELECT message_text FROM motivation_templates WHERE trigger_type = ? AND language = ? AND is_active = 1 ORDER BY RAND() LIMIT 1");
    $stmt->execute([$triggerType, $lang]);
    $result = $stmt->fetch();
    return $result ? $result['message_text'] : null;
}

function callOpenAI($message, $language = 'tr') {
    $systemPrompts = [
        'tr' => 'Sen Vitalia, kişisel sağlıklı yaşam danışmanısın. Görevin kullanıcıya beslenme, hareket, spor, su tüketimi ve kilo yönetimi konularında yardımcı olmak.

KURALLAR:
1. Sadece sağlık, beslenme, egzersiz, su ve kilo konularında yardım et
2. Sağlık dışı konularda nazikçe reddet
3. ASLA tıbbi teşhis koyma veya ilaç önerme
4. Yargılayıcı olma, "bozdun" gibi ifadeler kullanma
5. Önerilerde en fazla 6 seçenek sun
6. Kısa ve motive edici cevaplar ver
7. Türkçe karakterlere dikkat et (ş, ğ, ü, ö, ç, ı)',

        'en' => 'You are Vitalia, a personal healthy living advisor. Your job is to help users with nutrition, movement, exercise, hydration, and weight management.

RULES:
1. Only help with health, nutrition, exercise, water, and weight topics
2. Politely decline non-health topics
3. NEVER make medical diagnoses or recommend medications
4. Don\'t be judgmental
5. Offer maximum 6 suggestions
6. Give short and motivating answers'
    ];
    
    $systemPrompt = $systemPrompts[$language] ?? $systemPrompts['tr'];
    
    $data = [
        'model' => OPENAI_MODEL,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $message]
        ],
        'max_tokens' => OPENAI_MAX_TOKENS,
        'temperature' => OPENAI_TEMPERATURE
    ];
    
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return 'Şu anda yanıt veremiyorum, lütfen biraz sonra tekrar dene. 🙏';
    }
    
    $result = json_decode($response, true);
    return $result['choices'][0]['message']['content'] ?? 'Bir hata oluştu.';
}
