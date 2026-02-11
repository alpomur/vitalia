<?php
/**
 * Vitalia - Chat-First Personal Health Advisor
 * PHP + MySQL Version for Shared Hosting
 * 
 * Database Configuration
 */

// Database connection settings
define('DB_HOST', 'localhost');
define('DB_NAME', 'vitalia_db');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

// OpenAI Configuration
define('OPENAI_API_KEY', 'your_openai_api_key');
define('OPENAI_MODEL', 'gpt-4o-mini');
define('OPENAI_MAX_TOKENS', 500);
define('OPENAI_TEMPERATURE', 0.7);

// Google OAuth Configuration
define('GOOGLE_CLIENT_ID', 'your_google_client_id');
define('GOOGLE_CLIENT_SECRET', 'your_google_client_secret');
define('GOOGLE_REDIRECT_URI', 'https://yoursite.com/auth/google/callback');

// Application Settings
define('APP_URL', 'https://yoursite.com');
define('SESSION_LIFETIME', 7 * 24 * 60 * 60); // 7 days

// Rate Limits
define('RATE_LIMIT_PER_MINUTE', 10);
define('DAILY_MESSAGE_LIMIT_GUEST', 10);
define('DAILY_MESSAGE_LIMIT_USER', 50);
define('DAILY_TOKEN_LIMIT_GUEST', 2000);
define('DAILY_TOKEN_LIMIT_USER', 10000);

// Health Calculation Settings
define('WATER_ML_PER_KG', 30);
define('WATER_EXERCISE_BONUS_ML', 500);
define('GLASS_ML', 250);
define('ANON_MESSAGE_THRESHOLD', 8);
define('CACHE_TTL_DAYS', 180);

// Create PDO connection
function getDbConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Session configuration
session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
