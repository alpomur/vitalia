<?php
/**
 * Vitalia - Profile API Handler
 * User profile endpoints for PHP version
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

try {
    $pdo = getDbConnection();
    
    $user = getCurrentUser($pdo);
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
    
    $userId = $user['user_id'];
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get profile
        $stmt = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ?");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();
        
        // Remove sensitive fields from user
        unset($user['id']);
        
        echo json_encode([
            'user' => $user,
            'profile' => $profile ?: null
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Update profile
        $input = json_decode(file_get_contents('php://input'), true);
        
        $age = isset($input['age']) ? intval($input['age']) : null;
        $heightCm = isset($input['height_cm']) ? intval($input['height_cm']) : null;
        $weightKg = isset($input['weight_kg']) ? floatval($input['weight_kg']) : null;
        $goal = $input['goal'] ?? null;
        
        // Upsert profile
        $stmt = $pdo->prepare("
            INSERT INTO profiles (user_id, age, height_cm, weight_kg, goal)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                age = COALESCE(VALUES(age), age),
                height_cm = COALESCE(VALUES(height_cm), height_cm),
                weight_kg = COALESCE(VALUES(weight_kg), weight_kg),
                goal = COALESCE(VALUES(goal), goal),
                updated_at = NOW()
        ");
        $stmt->execute([$userId, $age, $heightCm, $weightKg, $goal]);
        
        // Update preferred language if provided
        if (isset($input['preferred_language'])) {
            $stmt = $pdo->prepare("UPDATE users SET preferred_language = ? WHERE user_id = ?");
            $stmt->execute([$input['preferred_language'], $userId]);
        }
        
        // Get updated profile
        $stmt = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ?");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();
        
        echo json_encode(['profile' => $profile]);
        
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
}
