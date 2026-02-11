<?php
/**
 * Vitalia - API Endpoint
 * Tüm API işlemleri bu dosyadan yönetilir
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

session_start();

// Guest ID
if (!isset($_SESSION['guest_id'])) {
    $_SESSION['guest_id'] = 'guest_' . bin2hex(random_bytes(6));
}

$pdo = getDbConnection();
$action = $_GET['action'] ?? '';
$userId = $_SESSION['guest_id'];

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
        
        // Mesajı kaydet
        $msgId = 'msg_' . bin2hex(random_bytes(6));
        $stmt = $pdo->prepare("INSERT INTO chat_messages (message_id, user_id, role, message_type, message_text) VALUES (?, ?, 'user', 'personal_complex', ?)");
        $stmt->execute([$msgId, $userId, $message]);
        
        // Kapsam kontrolü
        $outOfScope = ['siyaset', 'politika', 'din', 'futbol', 'maç', 'film'];
        $isOutOfScope = false;
        foreach ($outOfScope as $kw) {
            if (mb_stripos($message, $kw) !== false) {
                $isOutOfScope = true;
                break;
            }
        }
        
        if ($isOutOfScope) {
            $response = 'Bu konuda yardımcı olamıyorum, ama sağlık hedeflerinle ilgili sorularını yanıtlamaktan mutluluk duyarım! 🌿';
        } else {
            // OpenAI API çağır
            $response = callOpenAI($message, $language);
        }
        
        // Yanıtı kaydet
        $respId = 'msg_' . bin2hex(random_bytes(6));
        $stmt = $pdo->prepare("INSERT INTO chat_messages (message_id, user_id, role, message_type, message_text) VALUES (?, ?, 'assistant', 'personal_complex', ?)");
        $stmt->execute([$respId, $userId, $response]);
        
        echo json_encode(['response' => $response, 'user_id' => $userId]);
        break;
        
    case 'chat_history':
        $stmt = $pdo->prepare("SELECT message_id as id, user_id, role, message_type, message_text, created_at FROM chat_messages WHERE user_id = ? ORDER BY created_at ASC LIMIT 50");
        $stmt->execute([$userId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['messages' => $messages]);
        break;
        
    // ===== QUICK ACTIONS =====
    case 'quick_action':
        $input = json_decode(file_get_contents('php://input'), true);
        $actionType = $input['action_type'] ?? '';
        $value = $input['value'] ?? null;
        
        switch ($actionType) {
            case 'water':
                $amount = $value ?? 250;
                $stmt = $pdo->prepare("INSERT INTO daily_logs (log_id, user_id, log_date, water_ml) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE water_ml = water_ml + ?");
                $logId = 'log_' . bin2hex(random_bytes(6));
                $stmt->execute([$logId, $userId, $today, $amount, $amount]);
                
                $stmt = $pdo->prepare("SELECT water_ml FROM daily_logs WHERE user_id = ? AND log_date = ?");
                $stmt->execute([$userId, $today]);
                $log = $stmt->fetch();
                
                echo json_encode(['success' => true, 'total_water_ml' => $log['water_ml'] ?? 0]);
                break;
                
            case 'steps':
                $level = $value ?? 'medium';
                $stmt = $pdo->prepare("INSERT INTO daily_logs (log_id, user_id, log_date, steps_level) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE steps_level = ?");
                $logId = 'log_' . bin2hex(random_bytes(6));
                $stmt->execute([$logId, $userId, $today, $level, $level]);
                echo json_encode(['success' => true, 'steps_level' => $level]);
                break;
                
            case 'workout':
                $done = $value ? 1 : 0;
                $stmt = $pdo->prepare("INSERT INTO daily_logs (log_id, user_id, log_date, workout_done) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE workout_done = ?");
                $logId = 'log_' . bin2hex(random_bytes(6));
                $stmt->execute([$logId, $userId, $today, $done, $done]);
                echo json_encode(['success' => true, 'workout_done' => (bool)$done]);
                break;
                
            case 'weight':
                $weight = floatval($value);
                if ($weight <= 0) {
                    echo json_encode(['error' => 'Invalid weight']);
                    exit;
                }
                
                $logId = 'wlog_' . bin2hex(random_bytes(6));
                $stmt = $pdo->prepare("INSERT INTO weight_logs (log_id, user_id, log_date, weight_kg) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE weight_kg = ?");
                $stmt->execute([$logId, $userId, $today, $weight, $weight]);
                
                $stmt = $pdo->prepare("INSERT INTO profiles (user_id, weight_kg) VALUES (?, ?) ON DUPLICATE KEY UPDATE weight_kg = ?");
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
        
        echo json_encode([
            'log' => $log ?: ['water_ml' => 0, 'steps_level' => null, 'workout_done' => false],
            'targets' => ['water_ml' => 2000, 'glass_ml' => 250]
        ]);
        break;
        
    // ===== PROFILE =====
    case 'profile_save':
        $input = json_decode(file_get_contents('php://input'), true);
        
        $age = isset($input['age']) ? intval($input['age']) : null;
        $height = isset($input['height_cm']) ? intval($input['height_cm']) : null;
        $weight = isset($input['weight_kg']) ? floatval($input['weight_kg']) : null;
        $goal = $input['goal'] ?? null;
        
        $stmt = $pdo->prepare("INSERT INTO profiles (user_id, age, height_cm, weight_kg, goal) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE age = COALESCE(?, age), height_cm = COALESCE(?, height_cm), weight_kg = COALESCE(?, weight_kg), goal = COALESCE(?, goal)");
        $stmt->execute([$userId, $age, $height, $weight, $goal, $age, $height, $weight, $goal]);
        
        echo json_encode(['success' => true]);
        break;
        
    default:
        echo json_encode(['error' => 'Unknown action']);
}

/**
 * OpenAI API çağrısı
 */
function callOpenAI($message, $language = 'tr') {
    $systemPrompts = [
        'tr' => 'Sen Vitalia, kişisel sağlıklı yaşam danışmanısın. Görevin kullanıcıya beslenme, hareket, spor, su tüketimi ve kilo yönetimi konularında yardımcı olmak.

KURALLAR:
1. Sadece sağlık, beslenme, egzersiz, su ve kilo konularında yardım et
2. Sağlık dışı konularda nazikçe reddet
3. ASLA tıbbi teşhis koyma veya ilaç önerme
4. Yargılayıcı olma
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
