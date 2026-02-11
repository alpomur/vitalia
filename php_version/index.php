<?php
/**
 * Vitalia - Ana Sayfa (Chat Interface)
 * Düz PHP + HTML/CSS/JS
 * 10 Dil Desteği
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/languages.php';
session_start();

// Guest ID oluştur
if (!isset($_SESSION['guest_id'])) {
    $_SESSION['guest_id'] = 'guest_' . bin2hex(random_bytes(6));
}

// Dil seçimi
$lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'tr';
$_SESSION['lang'] = $lang;

// Çeviriler (languages.php'den)
$t = $GLOBALS['translations'][$lang] ?? $GLOBALS['translations']['tr'];
$supportedLangs = getSupportedLanguages();
$isRTL = ($t['dir'] ?? 'ltr') === 'rtl';
        'admin' => 'Yönetim'
    ],
    'en' => [
        'title' => 'Vitalia - Health Advisor',
        'welcome' => 'Hello! 🌿 I\'m Vitalia, your personal health advisor. How are you feeling today?',
        'placeholder' => 'Type your message...',
        'send' => 'Send',
        'water' => 'Water',
        'steps' => 'Movement',
        'workout' => 'Workout',
        'weight' => 'Weight',
        'glasses' => 'glasses',
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'done' => 'Done',
        'dashboard' => 'Daily Summary',
        'profile' => 'Profile',
        'login' => 'Log In',
        'logout' => 'Logout',
        'loginPrompt' => 'Want me to remember what I told you?',
        'waterProgress' => 'Water Progress',
        'stepsLevel' => 'Activity Level',
        'workoutStatus' => 'Workout Status',
        'completed' => 'Completed',
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $isRTL ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $t['title'] ?></title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --bg: #F8FAFC;
            --fg: #0F172A;
            --card: #FFFFFF;
            --border: #E2E8F0;
            --primary: #F97316;
            --primary-fg: #FFFFFF;
            --secondary: #0EA5E9;
            --muted: #F1F5F9;
            --muted-fg: #64748B;
            --success: #22C55E;
            --water: #38BDF8;
        }
        
        .dark {
            --bg: #0F172A;
            --fg: #F8FAFC;
            --card: #1E293B;
            --border: #334155;
            --primary: #FB923C;
            --muted: #1E293B;
            --muted-fg: #94A3B8;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--fg);
            min-height: 100vh;
        }
        
        h1, h2, h3 { font-family: 'Outfit', sans-serif; }
        
        .container {
            max-width: 480px;
            margin: 0 auto;
            height: 100vh;
            display: flex;
            flex-direction: column;
            background: var(--bg);
            border-left: 1px solid var(--border);
            border-right: 1px solid var(--border);
        }
        
        /* Header */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px;
            border-bottom: 1px solid var(--border);
            background: var(--card);
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
        }
        
        .logo-text h1 {
            font-size: 18px;
            font-weight: 700;
        }
        
        .logo-text p {
            font-size: 12px;
            color: var(--muted-fg);
        }
        
        .header-actions {
            display: flex;
            gap: 8px;
        }
        
        .icon-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            background: var(--muted);
            color: var(--fg);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .icon-btn:hover { background: var(--border); }
        
        /* Language Selector */
        .lang-select {
            padding: 6px 12px;
            border-radius: 20px;
            border: 1px solid var(--border);
            background: var(--card);
            color: var(--fg);
            font-size: 14px;
            cursor: pointer;
        }
        
        /* Chat Area */
        .chat-area {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
        }
        
        .message {
            margin-bottom: 16px;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .message-content {
            max-width: 85%;
            padding: 14px 18px;
            border-radius: 18px;
            line-height: 1.5;
            font-size: 14px;
        }
        
        .message.user .message-content {
            background: var(--primary);
            color: var(--primary-fg);
            margin-left: auto;
            border-bottom-right-radius: 4px;
        }
        
        .message.assistant .message-content {
            background: var(--card);
            border: 1px solid var(--border);
            border-bottom-left-radius: 4px;
        }
        
        .typing {
            display: flex;
            gap: 4px;
            padding: 14px 18px;
        }
        
        .typing span {
            width: 8px;
            height: 8px;
            background: var(--muted-fg);
            border-radius: 50%;
            animation: bounce 1.4s infinite ease-in-out;
        }
        
        .typing span:nth-child(1) { animation-delay: -0.32s; }
        .typing span:nth-child(2) { animation-delay: -0.16s; }
        
        @keyframes bounce {
            0%, 80%, 100% { transform: scale(0); }
            40% { transform: scale(1); }
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            padding: 12px 16px;
            background: var(--muted);
            border-radius: 16px;
            margin: 0 16px;
        }
        
        .quick-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            padding: 12px 8px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .quick-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .quick-btn:active { transform: scale(0.95); }
        
        .quick-btn.active {
            background: rgba(249, 115, 22, 0.1);
            border-color: var(--primary);
        }
        
        .quick-btn i {
            font-size: 20px;
        }
        
        .quick-btn .value {
            font-size: 14px;
            font-weight: 600;
        }
        
        .quick-btn .label {
            font-size: 10px;
            color: var(--muted-fg);
        }
        
        .water-icon { color: var(--water); }
        .steps-icon { color: var(--success); }
        .workout-icon { color: var(--primary); }
        .weight-icon { color: var(--secondary); }
        
        /* Input Area */
        .input-area {
            padding: 16px;
            background: var(--card);
            border-top: 1px solid var(--border);
        }
        
        .input-row {
            display: flex;
            gap: 12px;
        }
        
        .chat-input {
            flex: 1;
            padding: 14px 20px;
            border-radius: 25px;
            border: none;
            background: var(--muted);
            color: var(--fg);
            font-size: 14px;
            outline: none;
        }
        
        .chat-input:focus {
            box-shadow: 0 0 0 2px rgba(249, 115, 22, 0.3);
        }
        
        .send-btn {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            border: none;
            background: var(--primary);
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .send-btn:hover { background: #ea580c; }
        .send-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 100;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active { display: flex; }
        
        .modal-content {
            background: var(--card);
            border-radius: 16px;
            padding: 24px;
            width: 90%;
            max-width: 400px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-header h2 {
            font-size: 20px;
        }
        
        .close-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: none;
            background: var(--muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Dashboard Styles */
        .stat-card {
            background: var(--muted);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .stat-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: var(--muted-fg);
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
        }
        
        .progress-bar {
            height: 8px;
            background: var(--border);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 8px;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--water);
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--bg);
            color: var(--fg);
            font-size: 14px;
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover { background: #ea580c; }
        
        /* Steps Modal */
        .steps-options {
            display: flex;
            gap: 8px;
            margin-top: 16px;
        }
        
        .step-option {
            flex: 1;
            padding: 16px;
            border: 2px solid var(--border);
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .step-option:hover,
        .step-option.selected {
            border-color: var(--primary);
            background: rgba(249, 115, 22, 0.1);
        }
        
        /* Login Prompt */
        .login-prompt {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
            margin: 0 16px 16px;
            text-align: center;
        }
        
        .login-prompt p {
            margin-bottom: 16px;
            color: var(--muted-fg);
        }
        
        .google-btn {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 12px 24px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .google-btn:hover { background: #f8f8f8; }
        
        /* Confetti */
        .confetti {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1000;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="logo">
                <div class="logo-icon">V</div>
                <div class="logo-text">
                    <h1>Vitalia</h1>
                    <p>Health Advisor</p>
                </div>
            </div>
            <div class="header-actions">
                <select class="lang-select" onchange="changeLanguage(this.value)">
                    <?php foreach ($supportedLangs as $l): ?>
                    <option value="<?= $l['code'] ?>" <?= $lang === $l['code'] ? 'selected' : '' ?>>
                        <?= $l['flag'] ?> <?= strtoupper($l['code']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button class="icon-btn" onclick="toggleTheme()" title="Tema">
                    <i class="fas fa-moon"></i>
                </button>
                <a href="workout.php?lang=<?= $lang ?>" class="icon-btn" title="<?= $t['workoutPlans'] ?? 'Spor Planları' ?>">
                    <i class="fas fa-dumbbell"></i>
                </a>
                <a href="food.php?lang=<?= $lang ?>" class="icon-btn" title="<?= $t['outsideFood'] ?? 'Dışarıda Yemek' ?>">
                    <i class="fas fa-utensils"></i>
                </a>
                <button class="icon-btn" onclick="openModal('dashboard')" title="<?= $t['dashboard'] ?>">
                    <i class="fas fa-chart-bar"></i>
                </button>
                <button class="icon-btn" onclick="openModal('profile')" title="<?= $t['profile'] ?>">
                    <i class="fas fa-user"></i>
                </button>
                <a href="admin.php" class="icon-btn" title="<?= $t['admin'] ?? 'Admin' ?>">
                    <i class="fas fa-cog"></i>
                </a>
            </div>
        </header>
        
        <!-- Chat Area -->
        <div class="chat-area" id="chatArea">
            <div class="message assistant">
                <div class="message-content"><?= $t['welcome'] ?></div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <button class="quick-btn" onclick="addWater()">
                <i class="fas fa-droplet water-icon"></i>
                <span class="value" id="waterCount">0</span>
                <span class="label"><?= $t['water'] ?></span>
            </button>
            <button class="quick-btn" onclick="openModal('steps')">
                <i class="fas fa-walking steps-icon"></i>
                <span class="value" id="stepsLevel">-</span>
                <span class="label"><?= $t['steps'] ?></span>
            </button>
            <button class="quick-btn" id="workoutBtn" onclick="toggleWorkout()">
                <i class="fas fa-dumbbell workout-icon"></i>
                <span class="value" id="workoutStatus">-</span>
                <span class="label"><?= $t['workout'] ?></span>
            </button>
            <button class="quick-btn" onclick="openModal('weight')">
                <i class="fas fa-weight-scale weight-icon"></i>
                <span class="value">-</span>
                <span class="label"><?= $t['weight'] ?></span>
            </button>
        </div>
        
        <!-- Input Area -->
        <div class="input-area">
            <div class="input-row">
                <input type="text" class="chat-input" id="chatInput" 
                       placeholder="<?= $t['placeholder'] ?>" 
                       onkeypress="handleKeyPress(event)">
                <button class="send-btn" onclick="sendMessage()" id="sendBtn">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Dashboard Modal -->
    <div class="modal" id="dashboardModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><?= $t['dashboard'] ?></h2>
                <button class="close-btn" onclick="closeModal('dashboard')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title"><i class="fas fa-droplet water-icon"></i> <?= $t['waterProgress'] ?></span>
                    <span class="stat-value" id="dashWater">0/8</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" id="waterProgress" style="width: 0%"></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title"><i class="fas fa-walking steps-icon"></i> <?= $t['stepsLevel'] ?></span>
                    <span class="stat-value" id="dashSteps">-</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title"><i class="fas fa-dumbbell workout-icon"></i> <?= $t['workoutStatus'] ?></span>
                    <span class="stat-value" id="dashWorkout"><?= $t['notCompleted'] ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Steps Modal -->
    <div class="modal" id="stepsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><?= $t['steps'] ?></h2>
                <button class="close-btn" onclick="closeModal('steps')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <p>Bugün ne kadar hareket ettin?</p>
            <div class="steps-options">
                <div class="step-option" onclick="setSteps('low')"><?= $t['low'] ?></div>
                <div class="step-option" onclick="setSteps('medium')"><?= $t['medium'] ?></div>
                <div class="step-option" onclick="setSteps('high')"><?= $t['high'] ?></div>
            </div>
        </div>
    </div>
    
    <!-- Weight Modal -->
    <div class="modal" id="weightModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><?= $t['weight'] ?></h2>
                <button class="close-btn" onclick="closeModal('weight')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="form-group">
                <label><?= $t['enterWeight'] ?></label>
                <input type="number" step="0.1" id="weightInput" placeholder="70.5">
            </div>
            <button class="btn btn-primary" onclick="saveWeight()"><?= $t['save'] ?></button>
        </div>
    </div>
    
    <!-- Profile Modal -->
    <div class="modal" id="profileModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><?= $t['profile'] ?></h2>
                <button class="close-btn" onclick="closeModal('profile')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="form-group">
                <label><?= $t['age'] ?></label>
                <input type="number" id="ageInput" placeholder="25">
            </div>
            <div class="form-group">
                <label><?= $t['height'] ?></label>
                <input type="number" id="heightInput" placeholder="175">
            </div>
            <div class="form-group">
                <label><?= $t['weight'] ?></label>
                <input type="number" step="0.1" id="profileWeightInput" placeholder="70.5">
            </div>
            <div class="form-group">
                <label><?= $t['goal'] ?></label>
                <select id="goalInput">
                    <option value="">Seçiniz</option>
                    <?php foreach ($t['goals'] as $key => $val): ?>
                    <option value="<?= $key ?>"><?= $val ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary" onclick="saveProfile()"><?= $t['save'] ?></button>
        </div>
    </div>
    
    <canvas class="confetti" id="confetti"></canvas>
    
    <script>
        // State
        let waterGlasses = 0;
        let stepsLevel = null;
        let workoutDone = false;
        let isLoading = false;
        const lang = '<?= $lang ?>';
        const t = <?= json_encode($t) ?>;
        
        // Load today's data on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadTodayData();
            loadChatHistory();
        });
        
        // API calls
        async function apiCall(endpoint, method = 'GET', data = null) {
            const options = {
                method: method,
                headers: { 'Content-Type': 'application/json' }
            };
            if (data) options.body = JSON.stringify(data);
            
            const response = await fetch('api.php?action=' + endpoint, options);
            return response.json();
        }
        
        async function loadTodayData() {
            try {
                const data = await apiCall('log_today');
                if (data.log) {
                    waterGlasses = Math.floor((data.log.water_ml || 0) / 250);
                    stepsLevel = data.log.steps_level;
                    workoutDone = data.log.workout_done;
                    updateUI();
                }
            } catch (e) {
                console.error('Failed to load today data:', e);
            }
        }
        
        async function loadChatHistory() {
            try {
                const data = await apiCall('chat_history');
                if (data.messages && data.messages.length > 0) {
                    const chatArea = document.getElementById('chatArea');
                    chatArea.innerHTML = '';
                    data.messages.forEach(msg => {
                        addMessageToChat(msg.message_text, msg.role);
                    });
                }
            } catch (e) {
                console.error('Failed to load chat history:', e);
            }
        }
        
        function updateUI() {
            document.getElementById('waterCount').textContent = waterGlasses;
            document.getElementById('stepsLevel').textContent = stepsLevel || '-';
            document.getElementById('workoutStatus').textContent = workoutDone ? '✓' : '-';
            
            const workoutBtn = document.getElementById('workoutBtn');
            if (workoutDone) {
                workoutBtn.classList.add('active');
            } else {
                workoutBtn.classList.remove('active');
            }
            
            // Dashboard update
            document.getElementById('dashWater').textContent = waterGlasses + '/8';
            document.getElementById('waterProgress').style.width = Math.min(waterGlasses / 8 * 100, 100) + '%';
            document.getElementById('dashSteps').textContent = stepsLevel || '-';
            document.getElementById('dashWorkout').textContent = workoutDone ? t.completed : t.notCompleted;
        }
        
        // Chat functions
        function handleKeyPress(e) {
            if (e.key === 'Enter') sendMessage();
        }
        
        async function sendMessage() {
            const input = document.getElementById('chatInput');
            const message = input.value.trim();
            if (!message || isLoading) return;
            
            input.value = '';
            addMessageToChat(message, 'user');
            showTyping();
            isLoading = true;
            
            try {
                const data = await apiCall('chat_send', 'POST', { message: message, language: lang });
                hideTyping();
                addMessageToChat(data.response, 'assistant');
            } catch (e) {
                hideTyping();
                addMessageToChat('Bir hata oluştu, lütfen tekrar deneyin.', 'assistant');
            }
            
            isLoading = false;
        }
        
        function addMessageToChat(text, role) {
            const chatArea = document.getElementById('chatArea');
            const div = document.createElement('div');
            div.className = 'message ' + role;
            div.innerHTML = '<div class="message-content">' + escapeHtml(text) + '</div>';
            chatArea.appendChild(div);
            chatArea.scrollTop = chatArea.scrollHeight;
        }
        
        function showTyping() {
            const chatArea = document.getElementById('chatArea');
            const div = document.createElement('div');
            div.id = 'typingIndicator';
            div.className = 'message assistant';
            div.innerHTML = '<div class="message-content typing"><span></span><span></span><span></span></div>';
            chatArea.appendChild(div);
            chatArea.scrollTop = chatArea.scrollHeight;
        }
        
        function hideTyping() {
            const typing = document.getElementById('typingIndicator');
            if (typing) typing.remove();
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Quick actions
        async function addWater() {
            try {
                const data = await apiCall('quick_action', 'POST', { action_type: 'water', value: 250 });
                if (data.success) {
                    waterGlasses = Math.floor(data.total_water_ml / 250);
                    updateUI();
                    addMessageToChat('💧 ' + waterGlasses + '/8 ' + t.glasses + ' - ' + t.dashboard, 'assistant');
                    
                    if (waterGlasses >= 8) {
                        triggerConfetti();
                    }
                }
            } catch (e) {
                console.error('Water action failed:', e);
            }
        }
        
        async function setSteps(level) {
            try {
                const data = await apiCall('quick_action', 'POST', { action_type: 'steps', value: level });
                if (data.success) {
                    stepsLevel = level;
                    updateUI();
                    closeModal('steps');
                    addMessageToChat('🚶 ' + t.steps + ': ' + level, 'assistant');
                }
            } catch (e) {
                console.error('Steps action failed:', e);
            }
        }
        
        async function toggleWorkout() {
            const newValue = !workoutDone;
            try {
                const data = await apiCall('quick_action', 'POST', { action_type: 'workout', value: newValue });
                if (data.success) {
                    workoutDone = data.workout_done;
                    updateUI();
                    
                    if (workoutDone) {
                        addMessageToChat('🏋️ ' + t.completed + '! 💪', 'assistant');
                        triggerConfetti();
                    }
                }
            } catch (e) {
                console.error('Workout action failed:', e);
            }
        }
        
        async function saveWeight() {
            const weight = parseFloat(document.getElementById('weightInput').value);
            if (!weight || weight <= 0) return;
            
            try {
                const data = await apiCall('quick_action', 'POST', { action_type: 'weight', value: weight });
                if (data.success) {
                    closeModal('weight');
                    addMessageToChat('⚖️ Kilo kaydedildi: ' + weight + ' kg', 'assistant');
                }
            } catch (e) {
                console.error('Weight action failed:', e);
            }
        }
        
        async function saveProfile() {
            const profile = {
                age: parseInt(document.getElementById('ageInput').value) || null,
                height_cm: parseInt(document.getElementById('heightInput').value) || null,
                weight_kg: parseFloat(document.getElementById('profileWeightInput').value) || null,
                goal: document.getElementById('goalInput').value || null
            };
            
            try {
                const data = await apiCall('profile_save', 'POST', profile);
                if (data.success) {
                    closeModal('profile');
                    addMessageToChat('✅ Profil kaydedildi!', 'assistant');
                }
            } catch (e) {
                console.error('Profile save failed:', e);
            }
        }
        
        // Modal functions
        function openModal(id) {
            document.getElementById(id + 'Modal').classList.add('active');
        }
        
        function closeModal(id) {
            document.getElementById(id + 'Modal').classList.remove('active');
        }
        
        // Theme toggle
        function toggleTheme() {
            document.body.classList.toggle('dark');
            localStorage.setItem('theme', document.body.classList.contains('dark') ? 'dark' : 'light');
        }
        
        // Load saved theme
        if (localStorage.getItem('theme') === 'dark') {
            document.body.classList.add('dark');
        }
        
        // Language change
        function changeLanguage(lang) {
            window.location.href = '?lang=' + lang;
        }
        
        // Confetti effect
        function triggerConfetti() {
            const canvas = document.getElementById('confetti');
            const ctx = canvas.getContext('2d');
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            
            const particles = [];
            const colors = ['#F97316', '#0EA5E9', '#22C55E', '#EAB308'];
            
            for (let i = 0; i < 100; i++) {
                particles.push({
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height - canvas.height,
                    r: Math.random() * 6 + 4,
                    color: colors[Math.floor(Math.random() * colors.length)],
                    vy: Math.random() * 3 + 2,
                    vx: Math.random() * 2 - 1
                });
            }
            
            function animate() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                let active = false;
                
                particles.forEach(p => {
                    if (p.y < canvas.height) {
                        active = true;
                        p.y += p.vy;
                        p.x += p.vx;
                        ctx.beginPath();
                        ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
                        ctx.fillStyle = p.color;
                        ctx.fill();
                    }
                });
                
                if (active) {
                    requestAnimationFrame(animate);
                } else {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                }
            }
            
            animate();
        }
    </script>
</body>
</html>
