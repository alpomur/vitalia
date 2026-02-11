<?php
/**
 * Vitalia - Chat API Handler
 * Main chat endpoint for PHP version
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// System prompts for different languages
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

$outOfScopeResponses = [
    'tr' => 'Bu konuda yardımcı olamıyorum, ama sağlık hedeflerinle ilgili sorularını yanıtlamaktan mutluluk duyarım! 🌿',
    'en' => 'I can\'t help with that topic, but I\'d be happy to answer questions about your health goals! 🌿'
];

$dailyLimitResponses = [
    'tr' => 'Bugünlük destek limitine ulaştık. Yarın devam edelim! Bu arada su içmeyi ve hareket etmeyi unutma! 💧🚶',
    'en' => 'We\'ve reached today\'s support limit. Let\'s continue tomorrow! Meanwhile, don\'t forget to drink water and stay active! 💧🚶'
];

/**
 * Classify message type
 */
function classifyMessage($message, $language = 'tr') {
    $messageLower = mb_strtolower($message);
    
    // Out of scope keywords
    $outOfScope = ['siyaset', 'politika', 'din', 'inanç', 'futbol', 'maç', 'film', 'dizi', 'politics', 'religion', 'football', 'movie'];
    foreach ($outOfScope as $kw) {
        if (strpos($messageLower, $kw) !== false) {
            return 'out_of_scope';
        }
    }
    
    // Routine keywords
    $routine = ['su', 'bardak', 'water', 'glass', 'adım', 'yürü', 'steps', 'walk', 'spor', 'egzersiz', 'workout', 'kilo', 'tartı', 'weight'];
    foreach ($routine as $kw) {
        if (strpos($messageLower, $kw) !== false) {
            return 'routine';
        }
    }
    
    // Motivation keywords
    $motivation = ['motivasyon', 'motive', 'başaramıyorum', 'zor', 'vazgeç'];
    foreach ($motivation as $kw) {
        if (strpos($messageLower, $kw) !== false) {
            return 'motivation';
        }
    }
    
    // FAQ patterns
    $faq = ['ne kadar', 'nedir', 'nasıl', 'how much', 'what is', 'how to'];
    foreach ($faq as $pattern) {
        if (strpos($messageLower, $pattern) !== false) {
            return 'faq';
        }
    }
    
    return 'personal_complex';
}

/**
 * Check rate limit
 */
function checkRateLimit($pdo, $userId, $ip) {
    $windowStart = date('Y-m-d H:i:s', strtotime('-1 minute'));
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM chat_messages 
        WHERE user_id = ? AND role = 'user' AND created_at >= ?
    ");
    $stmt->execute([$userId, $windowStart]);
    $result = $stmt->fetch();
    
    return $result['count'] < RATE_LIMIT_PER_MINUTE;
}

/**
 * Check daily limit
 */
function checkDailyLimit($pdo, $userId, $isGuest) {
    $today = date('Y-m-d');
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM chat_messages 
        WHERE user_id = ? AND role = 'user' AND DATE(created_at) = ?
    ");
    $stmt->execute([$userId, $today]);
    $result = $stmt->fetch();
    
    $limit = $isGuest ? DAILY_MESSAGE_LIMIT_GUEST : DAILY_MESSAGE_LIMIT_USER;
    return $result['count'] < $limit;
}

/**
 * Get cached response
 */
function getCachedResponse($pdo, $question) {
    $normalized = mb_strtolower(trim($question));
    $fingerprint = hash('sha256', $normalized);
    $fingerprint = substr($fingerprint, 0, 32);
    
    $stmt = $pdo->prepare("
        SELECT answer_text FROM qa_cache 
        WHERE question_fingerprint = ? AND (expires_at IS NULL OR expires_at > NOW())
    ");
    $stmt->execute([$fingerprint]);
    $result = $stmt->fetch();
    
    if ($result) {
        // Increment hit count
        $pdo->prepare("UPDATE qa_cache SET hit_count = hit_count + 1 WHERE question_fingerprint = ?")
            ->execute([$fingerprint]);
        return $result['answer_text'];
    }
    
    return null;
}

/**
 * Cache response
 */
function cacheResponse($pdo, $question, $answer, $topic = 'general') {
    $normalized = mb_strtolower(trim($question));
    $fingerprint = hash('sha256', $normalized);
    $fingerprint = substr($fingerprint, 0, 32);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . CACHE_TTL_DAYS . ' days'));
    
    $stmt = $pdo->prepare("
        INSERT INTO qa_cache (topic, question_fingerprint, normalized_question, answer_text, expires_at)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE answer_text = VALUES(answer_text), updated_at = NOW()
    ");
    $stmt->execute([$topic, $fingerprint, substr($normalized, 0, 500), $answer, $expiresAt]);
}

/**
 * Call OpenAI API
 */
function callOpenAI($message, $systemPrompt, $userId) {
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
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return null;
    }
    
    $result = json_decode($response, true);
    return $result['choices'][0]['message']['content'] ?? null;
}

/**
 * Save message to database
 */
function saveMessage($pdo, $userId, $role, $messageType, $messageText) {
    $messageId = 'msg_' . bin2hex(random_bytes(12));
    
    $stmt = $pdo->prepare("
        INSERT INTO chat_messages (message_id, user_id, role, message_type, message_text)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$messageId, $userId, $role, $messageType, $messageText]);
    
    return $messageId;
}

/**
 * Get or create guest user
 */
function getOrCreateGuestUser($pdo, $guestId) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$guestId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $stmt = $pdo->prepare("
            INSERT INTO users (user_id, auth_provider, status, preferred_language)
            VALUES (?, 'guest', 'active', 'tr')
        ");
        $stmt->execute([$guestId]);
        
        return [
            'user_id' => $guestId,
            'auth_provider' => 'guest',
            'status' => 'active'
        ];
    }
    
    return $user;
}

/**
 * Log OpenAI usage
 */
function logUsage($pdo, $userId, $inputTokens, $outputTokens) {
    $today = date('Y-m-d');
    
    $stmt = $pdo->prepare("
        INSERT INTO openai_usage_daily (user_id, usage_date, calls_count, input_tokens, output_tokens)
        VALUES (?, ?, 1, ?, ?)
        ON DUPLICATE KEY UPDATE 
            calls_count = calls_count + 1,
            input_tokens = input_tokens + VALUES(input_tokens),
            output_tokens = output_tokens + VALUES(output_tokens)
    ");
    $stmt->execute([$userId, $today, $inputTokens, $outputTokens]);
}

// Main handler
try {
    $pdo = getDbConnection();
    
    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);
    $message = trim($input['message'] ?? '');
    $language = $input['language'] ?? 'tr';
    
    if (empty($message)) {
        echo json_encode(['error' => 'Message required']);
        exit;
    }
    
    // Get or create user
    session_start();
    $guestId = $_SESSION['guest_id'] ?? ('guest_' . bin2hex(random_bytes(6)));
    $_SESSION['guest_id'] = $guestId;
    
    $user = getOrCreateGuestUser($pdo, $guestId);
    $userId = $user['user_id'];
    $isGuest = $user['auth_provider'] === 'guest';
    
    // Update last active
    $pdo->prepare("UPDATE users SET last_active_at = NOW() WHERE user_id = ?")
        ->execute([$userId]);
    
    // Check rate limit
    if (!checkRateLimit($pdo, $userId, $_SERVER['REMOTE_ADDR'])) {
        echo json_encode([
            'response' => 'Biraz yavaşla! Çok hızlı mesaj gönderiyorsun. 🐢',
            'message_type' => 'rate_limit',
            'user_id' => $userId
        ]);
        exit;
    }
    
    // Check daily limit
    if (!checkDailyLimit($pdo, $userId, $isGuest)) {
        echo json_encode([
            'response' => $dailyLimitResponses[$language] ?? $dailyLimitResponses['en'],
            'message_type' => 'daily_limit',
            'user_id' => $userId
        ]);
        exit;
    }
    
    // Classify message
    $messageType = classifyMessage($message, $language);
    
    // Save user message
    saveMessage($pdo, $userId, 'user', $messageType, $message);
    
    // Handle out of scope
    if ($messageType === 'out_of_scope') {
        $response = $outOfScopeResponses[$language] ?? $outOfScopeResponses['en'];
        saveMessage($pdo, $userId, 'assistant', 'out_of_scope', $response);
        
        echo json_encode([
            'response' => $response,
            'message_type' => 'out_of_scope',
            'user_id' => $userId,
            'is_guest' => $isGuest
        ]);
        exit;
    }
    
    // Check cache
    $cachedResponse = getCachedResponse($pdo, $message);
    if ($cachedResponse) {
        saveMessage($pdo, $userId, 'assistant', 'faq', $cachedResponse);
        
        echo json_encode([
            'response' => $cachedResponse,
            'message_type' => 'faq',
            'user_id' => $userId,
            'is_guest' => $isGuest,
            'cached' => true
        ]);
        exit;
    }
    
    // Call OpenAI
    $systemPrompt = $systemPrompts[$language] ?? $systemPrompts['en'];
    $aiResponse = callOpenAI($message, $systemPrompt, $userId);
    
    if (!$aiResponse) {
        $aiResponse = 'Şu anda yanıt veremiyorum, lütfen biraz sonra tekrar dene. 🙏';
    } else {
        // Cache response for FAQ/motivation types
        if (in_array($messageType, ['faq', 'motivation'])) {
            cacheResponse($pdo, $message, $aiResponse, $messageType);
        }
        
        // Log usage
        $inputTokens = intval(strlen($message) / 4);
        $outputTokens = intval(strlen($aiResponse) / 4);
        logUsage($pdo, $userId, $inputTokens, $outputTokens);
    }
    
    // Save assistant message
    saveMessage($pdo, $userId, 'assistant', $messageType, $aiResponse);
    
    // Check if should prompt login
    $promptLogin = false;
    if ($isGuest) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM chat_messages WHERE user_id = ? AND role = 'user'");
        $stmt->execute([$userId]);
        $msgCount = $stmt->fetch()['count'];
        $promptLogin = $msgCount >= ANON_MESSAGE_THRESHOLD;
    }
    
    echo json_encode([
        'response' => $aiResponse,
        'message_type' => $messageType,
        'user_id' => $userId,
        'is_guest' => $isGuest,
        'prompt_login' => $promptLogin
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
