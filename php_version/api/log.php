<?php
/**
 * Vitalia - Log API Handler
 * Daily logs endpoint for PHP version
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();

/**
 * Get current user from session
 */
function getCurrentUser($pdo) {
    if (!isset($_SESSION['user_id'])) {
        // Check for guest
        if (isset($_SESSION['guest_id'])) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$_SESSION['guest_id']]);
            return $stmt->fetch();
        }
        return null;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Get or create guest user
 */
function getOrCreateGuestUser($pdo) {
    $guestId = $_SESSION['guest_id'] ?? ('guest_' . bin2hex(random_bytes(6)));
    $_SESSION['guest_id'] = $guestId;
    
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
 * Calculate water target
 */
function getWaterTarget($weightKg, $hasExercise = false) {
    $base = intval($weightKg * WATER_ML_PER_KG);
    if ($hasExercise) {
        $base += WATER_EXERCISE_BONUS_ML;
    }
    return $base;
}

try {
    $pdo = getDbConnection();
    $action = $_GET['action'] ?? '';
    
    // Get user
    $user = getCurrentUser($pdo);
    if (!$user) {
        $user = getOrCreateGuestUser($pdo);
    }
    $userId = $user['user_id'];
    $today = date('Y-m-d');
    
    switch ($action) {
        case 'quick-action':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $actionType = $input['action_type'] ?? '';
            $value = $input['value'] ?? null;
            
            switch ($actionType) {
                case 'water':
                    $amount = $value ?? GLASS_ML;
                    
                    // Upsert daily log
                    $stmt = $pdo->prepare("
                        INSERT INTO daily_logs (log_id, user_id, log_date, water_ml)
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE water_ml = water_ml + VALUES(water_ml), updated_at = NOW()
                    ");
                    $logId = 'log_' . bin2hex(random_bytes(6));
                    $stmt->execute([$logId, $userId, $today, $amount]);
                    
                    // Get updated total
                    $stmt = $pdo->prepare("SELECT water_ml FROM daily_logs WHERE user_id = ? AND log_date = ?");
                    $stmt->execute([$userId, $today]);
                    $log = $stmt->fetch();
                    
                    echo json_encode([
                        'success' => true,
                        'action' => 'water',
                        'total_water_ml' => $log['water_ml'] ?? 0
                    ]);
                    break;
                    
                case 'steps':
                    $level = $value ?? 'medium';
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO daily_logs (log_id, user_id, log_date, steps_level)
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE steps_level = VALUES(steps_level), updated_at = NOW()
                    ");
                    $logId = 'log_' . bin2hex(random_bytes(6));
                    $stmt->execute([$logId, $userId, $today, $level]);
                    
                    echo json_encode([
                        'success' => true,
                        'action' => 'steps',
                        'steps_level' => $level
                    ]);
                    break;
                    
                case 'workout':
                    $done = $value !== null ? (bool)$value : true;
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO daily_logs (log_id, user_id, log_date, workout_done)
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE workout_done = VALUES(workout_done), updated_at = NOW()
                    ");
                    $logId = 'log_' . bin2hex(random_bytes(6));
                    $stmt->execute([$logId, $userId, $today, $done ? 1 : 0]);
                    
                    echo json_encode([
                        'success' => true,
                        'action' => 'workout',
                        'workout_done' => $done
                    ]);
                    break;
                    
                case 'weight':
                    if ($value === null) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Weight value required']);
                        exit;
                    }
                    
                    $weightKg = floatval($value);
                    
                    // Save weight log
                    $stmt = $pdo->prepare("
                        INSERT INTO weight_logs (log_id, user_id, log_date, weight_kg)
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE weight_kg = VALUES(weight_kg)
                    ");
                    $logId = 'wlog_' . bin2hex(random_bytes(6));
                    $stmt->execute([$logId, $userId, $today, $weightKg]);
                    
                    // Update profile
                    $stmt = $pdo->prepare("
                        INSERT INTO profiles (user_id, weight_kg)
                        VALUES (?, ?)
                        ON DUPLICATE KEY UPDATE weight_kg = VALUES(weight_kg), updated_at = NOW()
                    ");
                    $stmt->execute([$userId, $weightKg]);
                    
                    echo json_encode([
                        'success' => true,
                        'action' => 'weight',
                        'weight_kg' => $weightKg
                    ]);
                    break;
                    
                default:
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid action type']);
            }
            break;
            
        case 'today':
            // Get today's log
            $stmt = $pdo->prepare("SELECT * FROM daily_logs WHERE user_id = ? AND log_date = ?");
            $stmt->execute([$userId, $today]);
            $log = $stmt->fetch();
            
            // Get profile for water target
            $stmt = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ?");
            $stmt->execute([$userId]);
            $profile = $stmt->fetch();
            
            $waterTarget = 2000;
            if ($profile && $profile['weight_kg']) {
                $waterTarget = getWaterTarget($profile['weight_kg']);
            }
            
            $defaultLog = [
                'water_ml' => 0,
                'steps_level' => null,
                'workout_done' => false,
                'mood' => null
            ];
            
            echo json_encode([
                'log' => $log ?: $defaultLog,
                'targets' => [
                    'water_ml' => $waterTarget,
                    'glass_ml' => GLASS_ML
                ]
            ]);
            break;
            
        case 'history':
            $days = isset($_GET['days']) ? intval($_GET['days']) : 7;
            
            // Get daily logs
            $stmt = $pdo->prepare("
                SELECT * FROM daily_logs 
                WHERE user_id = ? 
                ORDER BY log_date DESC 
                LIMIT ?
            ");
            $stmt->execute([$userId, $days]);
            $dailyLogs = $stmt->fetchAll();
            
            // Get weight logs
            $stmt = $pdo->prepare("
                SELECT * FROM weight_logs 
                WHERE user_id = ? 
                ORDER BY log_date DESC 
                LIMIT ?
            ");
            $stmt->execute([$userId, $days]);
            $weightLogs = $stmt->fetchAll();
            
            echo json_encode([
                'daily_logs' => $dailyLogs,
                'weight_logs' => $weightLogs
            ]);
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
}
