<?php
/**
 * Vitalia - Yardımcı Fonksiyonlar
 * Cache, Rate Limiting, Güvenlik
 */

/**
 * Rate Limit Kontrolü
 * @param PDO $pdo
 * @param string $identifier - IP veya user_id
 * @param int $limit - Dakikada izin verilen istek sayısı
 * @return bool - true ise istek geçer
 */
function checkRateLimit($pdo, $identifier, $limit = 10) {
    $windowStart = date('Y-m-d H:i:s', strtotime('-1 minute'));
    
    // Eski kayıtları temizle
    $pdo->prepare("DELETE FROM rate_limit_events WHERE window_start < ?")->execute([$windowStart]);
    
    // Mevcut penceredeki istek sayısını al
    $stmt = $pdo->prepare("
        SELECT SUM(request_count) as total 
        FROM rate_limit_events 
        WHERE identifier = ? AND window_start >= ?
    ");
    $stmt->execute([$identifier, $windowStart]);
    $result = $stmt->fetch();
    $count = $result['total'] ?? 0;
    
    if ($count >= $limit) {
        // Güvenlik olayı kaydet
        logSecurityEvent($pdo, 'rate_limit', $identifier, null, ['count' => $count, 'limit' => $limit]);
        return false;
    }
    
    // İstek kaydı ekle/güncelle
    $currentMinute = date('Y-m-d H:i:00');
    $stmt = $pdo->prepare("
        INSERT INTO rate_limit_events (identifier, identifier_type, request_count, window_start)
        VALUES (?, 'ip', 1, ?)
        ON DUPLICATE KEY UPDATE request_count = request_count + 1
    ");
    $stmt->execute([$identifier, $currentMinute]);
    
    return true;
}

/**
 * Günlük Mesaj Limiti Kontrolü
 */
function checkDailyLimit($pdo, $userId, $isGuest = false) {
    $today = date('Y-m-d');
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM chat_messages 
        WHERE user_id = ? AND role = 'user' AND DATE(created_at) = ?
    ");
    $stmt->execute([$userId, $today]);
    $count = $stmt->fetch()['count'];
    
    // Limitleri admin ayarlarından al
    $limitKey = $isGuest ? 'daily_message_limit_guest' : 'daily_message_limit_user';
    $limit = getSetting($pdo, $limitKey, $isGuest ? 10 : 50);
    
    return $count < $limit;
}

/**
 * Cache'den cevap al
 */
function getCachedResponse($pdo, $question) {
    $normalized = mb_strtolower(trim($question));
    $fingerprint = substr(hash('sha256', $normalized), 0, 32);
    
    $stmt = $pdo->prepare("
        SELECT answer_text FROM qa_cache 
        WHERE question_fingerprint = ? 
        AND (expires_at IS NULL OR expires_at > NOW())
    ");
    $stmt->execute([$fingerprint]);
    $result = $stmt->fetch();
    
    if ($result) {
        // Hit sayısını artır
        $pdo->prepare("UPDATE qa_cache SET hit_count = hit_count + 1 WHERE question_fingerprint = ?")
            ->execute([$fingerprint]);
        return $result['answer_text'];
    }
    
    return null;
}

/**
 * Cevabı cache'e kaydet
 */
function cacheResponse($pdo, $question, $answer, $topic = 'general') {
    $normalized = mb_strtolower(trim($question));
    $fingerprint = substr(hash('sha256', $normalized), 0, 32);
    $ttlDays = getSetting($pdo, 'cache_ttl_days', 180);
    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$ttlDays} days"));
    
    $stmt = $pdo->prepare("
        INSERT INTO qa_cache (topic, question_fingerprint, normalized_question, answer_text, expires_at)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            answer_text = VALUES(answer_text), 
            updated_at = NOW()
    ");
    $stmt->execute([$topic, $fingerprint, substr($normalized, 0, 500), $answer, $expiresAt]);
}

/**
 * FAQ'dan cevap al
 */
function getFaqResponse($pdo, $question, $topic = null) {
    $normalized = mb_strtolower(trim($question));
    
    // Basit anahtar kelime eşleştirme
    $stmt = $pdo->prepare("
        SELECT answer_text, tag FROM qa_faq 
        WHERE is_active = 1 
        " . ($topic ? "AND topic = ?" : "") . "
        ORDER BY id
    ");
    
    $params = $topic ? [$topic] : [];
    $stmt->execute($params);
    $faqs = $stmt->fetchAll();
    
    foreach ($faqs as $faq) {
        $tag = mb_strtolower($faq['tag']);
        if (mb_strpos($normalized, $tag) !== false) {
            return $faq['answer_text'];
        }
    }
    
    return null;
}

/**
 * Admin ayarını al
 */
function getSetting($pdo, $key, $default = null) {
    $stmt = $pdo->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    
    if ($result) {
        $value = json_decode($result['setting_value'], true);
        return $value !== null ? $value : $result['setting_value'];
    }
    
    return $default;
}

/**
 * Güvenlik olayı kaydet
 */
function logSecurityEvent($pdo, $eventType, $ip = null, $userId = null, $meta = null) {
    $stmt = $pdo->prepare("
        INSERT INTO security_events (event_type, ip_address, user_id, meta)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $eventType,
        $ip,
        $userId,
        $meta ? json_encode($meta) : null
    ]);
}

/**
 * OpenAI kullanımı kaydet
 */
function logOpenAIUsage($pdo, $userId, $inputTokens, $outputTokens) {
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

/**
 * Mesaj sınıflandırma
 */
function classifyMessage($message, $lang = 'tr') {
    $lower = mb_strtolower($message);
    
    // Kapsam dışı
    $outOfScope = ['siyaset', 'politika', 'din', 'futbol', 'maç', 'film', 'dizi', 
                   'politics', 'religion', 'football', 'movie', 'game'];
    foreach ($outOfScope as $kw) {
        if (mb_strpos($lower, $kw) !== false) {
            return 'out_of_scope';
        }
    }
    
    // Rutin - su, hareket, spor, kilo
    $routine = ['su', 'bardak', 'water', 'glass', 'adım', 'step', 'spor', 'workout', 
                'kilo', 'weight', 'tartı'];
    foreach ($routine as $kw) {
        if (mb_strpos($lower, $kw) !== false) {
            return 'routine';
        }
    }
    
    // Motivasyon
    $motivation = ['motivasyon', 'motive', 'başaramıyorum', 'zor', 'vazgeç', 
                   'difficult', 'hard', 'give up'];
    foreach ($motivation as $kw) {
        if (mb_strpos($lower, $kw) !== false) {
            return 'motivation';
        }
    }
    
    // FAQ - soru kalıpları
    $faq = ['ne kadar', 'nedir', 'nasıl', 'how much', 'what is', 'how to'];
    foreach ($faq as $pattern) {
        if (mb_strpos($lower, $pattern) !== false) {
            return 'faq';
        }
    }
    
    return 'personal_complex';
}

/**
 * Su hedefi hesapla
 */
function calculateWaterTarget($weightKg, $hasExercise = false) {
    $mlPerKg = 30; // Admin ayarından alınabilir
    $exerciseBonus = 500;
    
    $base = intval($weightKg * $mlPerKg);
    if ($hasExercise) {
        $base += $exerciseBonus;
    }
    
    return $base;
}

/**
 * Adım hedefi hesapla
 */
function getStepsTarget($goal) {
    $targets = [
        'lose' => ['min' => 8000, 'max' => 11000],
        'maintain' => ['min' => 6000, 'max' => 8500],
        'gain' => ['min' => 5000, 'max' => 7000],
        'healthy' => ['min' => 7000, 'max' => 10000]
    ];
    
    return $targets[$goal] ?? $targets['healthy'];
}

/**
 * XSS koruması
 */
function sanitize($input) {
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

/**
 * CSRF token oluştur/doğrula
 */
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
