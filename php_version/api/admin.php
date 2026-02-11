<?php
/**
 * Vitalia - Admin API Handler
 * Admin panel endpoints for PHP version
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();

/**
 * Get current user from session
 */
function getCurrentUser($pdo) {
    $sessionToken = $_COOKIE['session_token'] ?? null;
    
    if (!$sessionToken) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $sessionToken = $matches[1];
        }
    }
    
    if (!$sessionToken) {
        return null;
    }
    
    $stmt = $pdo->prepare("
        SELECT * FROM user_sessions 
        WHERE session_token = ? AND expires_at > NOW()
    ");
    $stmt->execute([$sessionToken]);
    $session = $stmt->fetch();
    
    if (!$session) {
        return null;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$session['user_id']]);
    return $stmt->fetch();
}

/**
 * Require admin access
 */
function requireAdmin($pdo) {
    $user = getCurrentUser($pdo);
    
    // For production, uncomment below:
    // if (!$user || $user['role'] !== 'admin') {
    //     http_response_code(403);
    //     echo json_encode(['error' => 'Admin access required']);
    //     exit;
    // }
    
    // For demo, allow any user
    return $user ?: ['user_id' => 'demo_admin', 'role' => 'admin'];
}

/**
 * Get default settings
 */
function getDefaultSettings() {
    return [
        'openai_model' => 'gpt-4o-mini',
        'openai_max_tokens' => 500,
        'openai_temperature' => 0.7,
        'water_ml_per_kg' => 30,
        'water_exercise_bonus_ml' => 500,
        'glass_ml' => 250,
        'steps_goal_lose' => ['min' => 8000, 'max' => 11000],
        'steps_goal_maintain' => ['min' => 6000, 'max' => 8500],
        'steps_goal_gain' => ['min' => 5000, 'max' => 7000],
        'weight_checkin_days' => 7,
        'daily_message_limit_guest' => 10,
        'daily_message_limit_user' => 50,
        'daily_token_limit_guest' => 2000,
        'daily_token_limit_user' => 10000,
        'rate_limit_per_minute' => 10,
        'cache_ttl_days' => 180,
        'max_suggestions' => 6,
        'anon_message_threshold' => 8
    ];
}

try {
    $pdo = getDbConnection();
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'dashboard':
            requireAdmin($pdo);
            
            $today = date('Y-m-d');
            $weekAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
            $monthAgo = date('Y-m-d H:i:s', strtotime('-30 days'));
            
            // User stats
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
            $totalUsers = $stmt->fetch()['total'];
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE created_at >= ?");
            $stmt->execute([$weekAgo]);
            $newUsers7d = $stmt->fetch()['count'];
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE created_at >= ?");
            $stmt->execute([$monthAgo]);
            $newUsers30d = $stmt->fetch()['count'];
            
            // Active users
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE DATE(last_active_at) = ?");
            $stmt->execute([$today]);
            $dau = $stmt->fetch()['count'];
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE last_active_at >= ?");
            $stmt->execute([$weekAgo]);
            $wau = $stmt->fetch()['count'];
            
            // Message stats
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM chat_messages");
            $totalMessages = $stmt->fetch()['total'];
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM chat_messages WHERE DATE(created_at) = ?");
            $stmt->execute([$today]);
            $messagesToday = $stmt->fetch()['count'];
            
            // OpenAI usage
            $stmt = $pdo->prepare("
                SELECT SUM(calls_count) as calls, SUM(input_tokens) as input, SUM(output_tokens) as output 
                FROM openai_usage_daily WHERE usage_date = ?
            ");
            $stmt->execute([$today]);
            $usageToday = $stmt->fetch();
            
            // Cache stats
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM qa_cache");
            $totalCached = $stmt->fetch()['total'];
            
            $stmt = $pdo->query("SELECT SUM(hit_count) as hits FROM qa_cache");
            $cacheHits = $stmt->fetch()['hits'] ?? 0;
            
            echo json_encode([
                'users' => [
                    'total' => $totalUsers,
                    'new_7d' => $newUsers7d,
                    'new_30d' => $newUsers30d,
                    'dau' => $dau,
                    'wau' => $wau
                ],
                'messages' => [
                    'total' => $totalMessages,
                    'today' => $messagesToday
                ],
                'openai' => [
                    'calls_count' => $usageToday['calls'] ?? 0,
                    'input_tokens' => $usageToday['input'] ?? 0,
                    'output_tokens' => $usageToday['output'] ?? 0
                ],
                'cache' => [
                    'total_entries' => $totalCached,
                    'total_hits' => $cacheHits
                ]
            ]);
            break;
            
        case 'users':
            requireAdmin($pdo);
            
            $skip = isset($_GET['skip']) ? intval($_GET['skip']) : 0;
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
            
            $stmt = $pdo->prepare("
                SELECT user_id, email, name, picture, role, auth_provider, status, 
                       preferred_language, created_at, last_login_at, last_active_at 
                FROM users 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$limit, $skip]);
            $users = $stmt->fetchAll();
            
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
            $total = $stmt->fetch()['total'];
            
            echo json_encode([
                'users' => $users,
                'total' => $total,
                'skip' => $skip,
                'limit' => $limit
            ]);
            break;
            
        case 'users/ban':
            requireAdmin($pdo);
            
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            
            // Get user_id from URL path
            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            preg_match('/users\/([^\/]+)\/ban/', $path, $matches);
            $userId = $matches[1] ?? $_GET['user_id'] ?? null;
            
            if (!$userId) {
                http_response_code(400);
                echo json_encode(['error' => 'user_id required']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $ban = $input['ban'] ?? true;
            $status = $ban ? 'banned' : 'active';
            
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE user_id = ?");
            $result = $stmt->execute([$status, $userId]);
            
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'User not found']);
                exit;
            }
            
            echo json_encode([
                'success' => true,
                'user_id' => $userId,
                'status' => $status
            ]);
            break;
            
        case 'settings':
            requireAdmin($pdo);
            
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // Get settings
                $stmt = $pdo->query("SELECT setting_key, setting_value FROM admin_settings");
                $settings = $stmt->fetchAll();
                
                $defaults = getDefaultSettings();
                $current = [];
                
                foreach ($settings as $s) {
                    $value = json_decode($s['setting_value'], true);
                    $current[$s['setting_key']] = $value !== null ? $value : $s['setting_value'];
                }
                
                // Merge with defaults
                foreach ($defaults as $key => $value) {
                    if (!isset($current[$key])) {
                        $current[$key] = $value;
                    }
                }
                
                echo json_encode(['settings' => $current]);
                
            } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Update settings
                $input = json_decode(file_get_contents('php://input'), true);
                
                foreach ($input as $key => $value) {
                    $jsonValue = json_encode($value);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO admin_settings (setting_key, setting_value) 
                        VALUES (?, ?)
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
                    ");
                    $stmt->execute([$key, $jsonValue]);
                }
                
                echo json_encode(['success' => true]);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
            
        case 'faq':
            requireAdmin($pdo);
            
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $stmt = $pdo->query("SELECT * FROM qa_faq ORDER BY created_at DESC");
                $faqs = $stmt->fetchAll();
                
                echo json_encode(['faqs' => $faqs]);
                
            } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                
                $faqId = 'faq_' . bin2hex(random_bytes(6));
                $topic = $input['topic'] ?? 'general';
                $tag = $input['tag'] ?? '';
                $questionExample = $input['question_example'] ?? '';
                $answerText = $input['answer_text'] ?? '';
                $isActive = $input['is_active'] ?? true;
                
                $stmt = $pdo->prepare("
                    INSERT INTO qa_faq (topic, tag, question_example, answer_text, is_active)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$topic, $tag, $questionExample, $answerText, $isActive ? 1 : 0]);
                
                echo json_encode([
                    'success' => true,
                    'faq' => [
                        'id' => $pdo->lastInsertId(),
                        'topic' => $topic,
                        'tag' => $tag,
                        'question_example' => $questionExample,
                        'answer_text' => $answerText,
                        'is_active' => $isActive
                    ]
                ]);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
}
