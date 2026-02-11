<?php
/**
 * Vitalia - Auth API Handler
 * Authentication endpoints for PHP version
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
 * Generate unique ID
 */
function generateId($prefix = '') {
    return $prefix . bin2hex(random_bytes(12));
}

/**
 * Get current user from session
 */
function getCurrentUser($pdo) {
    // Check session token in cookie
    $sessionToken = $_COOKIE['session_token'] ?? null;
    
    // Or from Authorization header
    if (!$sessionToken) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $sessionToken = $matches[1];
        }
    }
    
    if (!$sessionToken) {
        return null;
    }
    
    // Find session
    $stmt = $pdo->prepare("
        SELECT * FROM user_sessions 
        WHERE session_token = ? AND expires_at > NOW()
    ");
    $stmt->execute([$sessionToken]);
    $session = $stmt->fetch();
    
    if (!$session) {
        return null;
    }
    
    // Get user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$session['user_id']]);
    return $stmt->fetch();
}

/**
 * Merge guest data to authenticated user
 */
function mergeGuestData($pdo, $guestId, $userId) {
    // Merge chat messages
    $stmt = $pdo->prepare("UPDATE chat_messages SET user_id = ? WHERE user_id = ?");
    $stmt->execute([$userId, $guestId]);
    
    // Merge daily logs
    $stmt = $pdo->prepare("UPDATE daily_logs SET user_id = ? WHERE user_id = ?");
    $stmt->execute([$userId, $guestId]);
    
    // Merge weight logs
    $stmt = $pdo->prepare("UPDATE weight_logs SET user_id = ? WHERE user_id = ?");
    $stmt->execute([$userId, $guestId]);
    
    // Delete guest user
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->execute([$guestId]);
}

try {
    $pdo = getDbConnection();
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'session':
            // Exchange session_id for session_token (after Google OAuth)
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $sessionId = $input['session_id'] ?? null;
            $guestId = $input['guest_id'] ?? $_SESSION['guest_id'] ?? null;
            
            if (!$sessionId) {
                http_response_code(400);
                echo json_encode(['error' => 'session_id required']);
                exit;
            }
            
            // Call Emergent Auth to get user data
            $ch = curl_init('https://demobackend.emergentagent.com/auth/v1/env/oauth/session-data');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'X-Session-ID: ' . $sessionId
                ]
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid session_id']);
                exit;
            }
            
            $authData = json_decode($response, true);
            $email = $authData['email'] ?? null;
            $name = $authData['name'] ?? null;
            $picture = $authData['picture'] ?? null;
            
            if (!$email) {
                http_response_code(401);
                echo json_encode(['error' => 'Email not provided']);
                exit;
            }
            
            // Check if user exists
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $existingUser = $stmt->fetch();
            
            if ($existingUser) {
                $userId = $existingUser['user_id'];
                
                // Update user info
                $stmt = $pdo->prepare("
                    UPDATE users SET 
                        name = ?, 
                        picture = ?, 
                        last_login_at = NOW(), 
                        last_active_at = NOW() 
                    WHERE user_id = ?
                ");
                $stmt->execute([$name, $picture, $userId]);
            } else {
                // Create new user
                $userId = generateId('user_');
                
                $stmt = $pdo->prepare("
                    INSERT INTO users (user_id, email, name, picture, role, auth_provider, status, preferred_language, last_login_at, last_active_at)
                    VALUES (?, ?, ?, ?, 'user', 'google', 'active', 'tr', NOW(), NOW())
                ");
                $stmt->execute([$userId, $email, $name, $picture]);
            }
            
            // Merge guest data if exists
            if ($guestId) {
                mergeGuestData($pdo, $guestId, $userId);
                unset($_SESSION['guest_id']);
            }
            
            // Create session
            $sessionToken = $authData['session_token'] ?? generateId('session_');
            $expiresAt = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
            
            // Delete old sessions for this user
            $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Insert new session
            $stmt = $pdo->prepare("
                INSERT INTO user_sessions (user_id, session_token, expires_at)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$userId, $sessionToken, $expiresAt]);
            
            // Set session in PHP session
            $_SESSION['user_id'] = $userId;
            $_SESSION['session_token'] = $sessionToken;
            
            // Set cookie
            setcookie('session_token', $sessionToken, [
                'expires' => time() + SESSION_LIFETIME,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'None'
            ]);
            
            // Get updated user
            $stmt = $pdo->prepare("SELECT user_id, email, name, picture, role, auth_provider, status, preferred_language, created_at, last_login_at FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            echo json_encode([
                'user' => $user,
                'session_token' => $sessionToken
            ]);
            break;
            
        case 'me':
            // Get current authenticated user
            $user = getCurrentUser($pdo);
            
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Not authenticated']);
                exit;
            }
            
            // Remove sensitive fields
            unset($user['id']);
            
            echo json_encode($user);
            break;
            
        case 'logout':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            
            $sessionToken = $_COOKIE['session_token'] ?? $_SESSION['session_token'] ?? null;
            
            if ($sessionToken) {
                // Delete session from database
                $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE session_token = ?");
                $stmt->execute([$sessionToken]);
            }
            
            // Clear session
            session_destroy();
            
            // Clear cookie
            setcookie('session_token', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'None'
            ]);
            
            echo json_encode(['message' => 'Logged out successfully']);
            break;
            
        case 'google':
            // Redirect to Google OAuth
            $redirectUri = urlencode(GOOGLE_REDIRECT_URI);
            $scope = urlencode('openid email profile');
            
            $authUrl = "https://accounts.google.com/o/oauth2/v2/auth?" .
                "client_id=" . GOOGLE_CLIENT_ID .
                "&redirect_uri=" . $redirectUri .
                "&response_type=code" .
                "&scope=" . $scope .
                "&access_type=offline" .
                "&prompt=consent";
            
            header('Location: ' . $authUrl);
            exit;
            
        case 'google/callback':
            // Handle Google OAuth callback
            $code = $_GET['code'] ?? null;
            
            if (!$code) {
                http_response_code(400);
                echo json_encode(['error' => 'Authorization code required']);
                exit;
            }
            
            // Exchange code for tokens
            $ch = curl_init('https://oauth2.googleapis.com/token');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query([
                    'client_id' => GOOGLE_CLIENT_ID,
                    'client_secret' => GOOGLE_CLIENT_SECRET,
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => GOOGLE_REDIRECT_URI
                ])
            ]);
            $response = curl_exec($ch);
            curl_close($ch);
            
            $tokens = json_decode($response, true);
            $accessToken = $tokens['access_token'] ?? null;
            
            if (!$accessToken) {
                http_response_code(401);
                echo json_encode(['error' => 'Failed to get access token']);
                exit;
            }
            
            // Get user info from Google
            $ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $accessToken
                ]
            ]);
            $response = curl_exec($ch);
            curl_close($ch);
            
            $userInfo = json_decode($response, true);
            $email = $userInfo['email'] ?? null;
            $name = $userInfo['name'] ?? null;
            $picture = $userInfo['picture'] ?? null;
            
            if (!$email) {
                http_response_code(401);
                echo json_encode(['error' => 'Email not provided by Google']);
                exit;
            }
            
            // Check if user exists
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $existingUser = $stmt->fetch();
            
            if ($existingUser) {
                $userId = $existingUser['user_id'];
                
                $stmt = $pdo->prepare("
                    UPDATE users SET name = ?, picture = ?, last_login_at = NOW(), last_active_at = NOW() 
                    WHERE user_id = ?
                ");
                $stmt->execute([$name, $picture, $userId]);
            } else {
                $userId = generateId('user_');
                
                $stmt = $pdo->prepare("
                    INSERT INTO users (user_id, email, name, picture, role, auth_provider, status, preferred_language, last_login_at, last_active_at)
                    VALUES (?, ?, ?, ?, 'user', 'google', 'active', 'tr', NOW(), NOW())
                ");
                $stmt->execute([$userId, $email, $name, $picture]);
            }
            
            // Merge guest data
            $guestId = $_SESSION['guest_id'] ?? null;
            if ($guestId) {
                mergeGuestData($pdo, $guestId, $userId);
                unset($_SESSION['guest_id']);
            }
            
            // Create session
            $sessionToken = generateId('session_');
            $expiresAt = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
            
            $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            $stmt = $pdo->prepare("
                INSERT INTO user_sessions (user_id, session_token, expires_at)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$userId, $sessionToken, $expiresAt]);
            
            $_SESSION['user_id'] = $userId;
            $_SESSION['session_token'] = $sessionToken;
            
            setcookie('session_token', $sessionToken, [
                'expires' => time() + SESSION_LIFETIME,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            
            // Redirect to frontend
            header('Location: ' . APP_URL . '/#session_id=' . $sessionToken);
            exit;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
}
