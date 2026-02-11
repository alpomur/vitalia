<?php
/**
 * Vitalia - API Router
 * Main entry point for all API requests
 */

require_once __DIR__ . '/config.php';

// Get the request path
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Remove leading/trailing slashes and 'api/' prefix
$path = trim($path, '/');
$path = preg_replace('/^api\//', '', $path);

// Route to appropriate handler
switch (true) {
    // Chat endpoints
    case $path === 'chat' || $path === 'chat/send':
        $_GET['action'] = 'send';
        require __DIR__ . '/api/chat.php';
        break;
        
    case $path === 'chat/welcome':
        header('Content-Type: application/json; charset=utf-8');
        $language = $_GET['language'] ?? 'tr';
        $welcomeMessages = [
            'tr' => 'Merhaba! 🌿 Ben Vitalia, senin kişisel sağlık danışmanın. Bugün kendini nasıl hissediyorsun?',
            'en' => 'Hello! 🌿 I\'m Vitalia, your personal health advisor. How are you feeling today?',
            'de' => 'Hallo! 🌿 Ich bin Vitalia, deine persönliche Gesundheitsberaterin. Wie fühlst du dich heute?',
            'fr' => 'Bonjour! 🌿 Je suis Vitalia, ta conseillère santé personnelle. Comment te sens-tu aujourd\'hui?',
            'es' => '¡Hola! 🌿 Soy Vitalia, tu asesora de salud personal. ¿Cómo te sientes hoy?',
            'it' => 'Ciao! 🌿 Sono Vitalia, la tua consulente di salute personale. Come ti senti oggi?',
            'pt' => 'Olá! 🌿 Sou Vitalia, sua consultora de saúde pessoal. Como você está se sentindo hoje?',
            'ru' => 'Привет! 🌿 Я Vitalia, твой персональный консультант по здоровью. Как ты себя чувствуешь сегодня?',
            'ar' => 'مرحباً! 🌿 أنا فيتاليا، مستشارتك الصحية الشخصية. كيف تشعر اليوم؟',
            'zh' => '你好！🌿 我是Vitalia，你的个人健康顾问。你今天感觉怎么样？'
        ];
        echo json_encode(['message' => $welcomeMessages[$language] ?? $welcomeMessages['en']]);
        break;
        
    case $path === 'chat/history':
        $_GET['action'] = 'history';
        require __DIR__ . '/api/chat.php';
        break;
        
    // Log endpoints
    case $path === 'log/quick-action':
        $_GET['action'] = 'quick-action';
        require __DIR__ . '/api/log.php';
        break;
        
    case $path === 'log/today':
        $_GET['action'] = 'today';
        require __DIR__ . '/api/log.php';
        break;
        
    case $path === 'log/history':
        $_GET['action'] = 'history';
        require __DIR__ . '/api/log.php';
        break;
        
    // Auth endpoints
    case $path === 'auth/session':
        $_GET['action'] = 'session';
        require __DIR__ . '/api/auth.php';
        break;
        
    case $path === 'auth/me':
        $_GET['action'] = 'me';
        require __DIR__ . '/api/auth.php';
        break;
        
    case $path === 'auth/logout':
        $_GET['action'] = 'logout';
        require __DIR__ . '/api/auth.php';
        break;
        
    case $path === 'auth/google':
        $_GET['action'] = 'google';
        require __DIR__ . '/api/auth.php';
        break;
        
    case $path === 'auth/google/callback':
        $_GET['action'] = 'google/callback';
        require __DIR__ . '/api/auth.php';
        break;
        
    // Profile endpoint
    case $path === 'profile':
        require __DIR__ . '/api/profile.php';
        break;
        
    // Admin endpoints
    case $path === 'admin/dashboard':
        $_GET['action'] = 'dashboard';
        require __DIR__ . '/api/admin.php';
        break;
        
    case $path === 'admin/users':
        $_GET['action'] = 'users';
        require __DIR__ . '/api/admin.php';
        break;
        
    case preg_match('/^admin\/users\/([^\/]+)\/ban$/', $path, $matches):
        $_GET['action'] = 'users/ban';
        $_GET['user_id'] = $matches[1];
        require __DIR__ . '/api/admin.php';
        break;
        
    case $path === 'admin/settings':
        $_GET['action'] = 'settings';
        require __DIR__ . '/api/admin.php';
        break;
        
    case $path === 'admin/faq':
        $_GET['action'] = 'faq';
        require __DIR__ . '/api/admin.php';
        break;
        
    // Health check
    case $path === '' || $path === 'health':
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'message' => 'Vitalia API - Personal Health Advisor',
            'status' => 'healthy',
            'timestamp' => date('c')
        ]);
        break;
        
    default:
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Endpoint not found', 'path' => $path]);
}
