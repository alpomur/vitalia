<?php
/**
 * Vitalia - Admin Panel
 * Düz PHP ile yönetim paneli
 */

require_once __DIR__ . '/config.php';
session_start();

// Admin kontrolü (production'da aktif edin)
// if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
//     header('Location: index.php');
//     exit;
// }

$pdo = getDbConnection();

// İstatistikler
$today = date('Y-m-d');
$weekAgo = date('Y-m-d', strtotime('-7 days'));

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$totalUsers = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE DATE(created_at) >= ?");
$stmt->execute([$weekAgo]);
$newUsers = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM chat_messages");
$totalMessages = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM chat_messages WHERE DATE(created_at) = ?");
$stmt->execute([$today]);
$todayMessages = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT SUM(calls_count) as calls FROM openai_usage_daily WHERE usage_date = ?");
$stmt->execute([$today]);
$aiCalls = $stmt->fetch()['calls'] ?? 0;

// Kullanıcı listesi
$stmt = $pdo->query("SELECT user_id, email, name, auth_provider, status, created_at, last_active_at FROM users ORDER BY created_at DESC LIMIT 50");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ayarlar
$stmt = $pdo->query("SELECT setting_key, setting_value FROM admin_settings");
$settingsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
$settings = [];
foreach ($settingsRaw as $s) {
    $settings[$s['setting_key']] = json_decode($s['setting_value'], true) ?? $s['setting_value'];
}

// Ayar güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $updateSettings = [
        'openai_model' => $_POST['openai_model'] ?? 'gpt-4o-mini',
        'openai_max_tokens' => intval($_POST['openai_max_tokens'] ?? 500),
        'daily_message_limit_guest' => intval($_POST['daily_message_limit_guest'] ?? 10),
        'daily_message_limit_user' => intval($_POST['daily_message_limit_user'] ?? 50),
        'water_ml_per_kg' => intval($_POST['water_ml_per_kg'] ?? 30),
        'glass_ml' => intval($_POST['glass_ml'] ?? 250)
    ];
    
    foreach ($updateSettings as $key => $value) {
        $stmt = $pdo->prepare("INSERT INTO admin_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $jsonValue = json_encode($value);
        $stmt->execute([$key, $jsonValue, $jsonValue]);
    }
    
    header('Location: admin.php?saved=1');
    exit;
}

// Ban/Unban
if (isset($_GET['ban'])) {
    $uid = $_GET['ban'];
    $pdo->prepare("UPDATE users SET status = 'banned' WHERE user_id = ?")->execute([$uid]);
    header('Location: admin.php');
    exit;
}

if (isset($_GET['unban'])) {
    $uid = $_GET['unban'];
    $pdo->prepare("UPDATE users SET status = 'active' WHERE user_id = ?")->execute([$uid]);
    header('Location: admin.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vitalia Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'DM Sans', sans-serif;
            background: #F8FAFC;
            color: #0F172A;
            min-height: 100vh;
        }
        h1, h2, h3 { font-family: 'Outfit', sans-serif; }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .header h1 {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 24px;
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #F97316, #0EA5E9);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .back-link {
            color: #64748B;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .back-link:hover { color: #F97316; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .stat-card h3 {
            font-size: 14px;
            color: #64748B;
            margin-bottom: 8px;
        }
        
        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            color: #0F172A;
        }
        
        .stat-card .sub {
            font-size: 12px;
            color: #22C55E;
            margin-top: 4px;
        }
        
        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 12px 24px;
            background: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: #64748B;
            transition: all 0.2s;
        }
        
        .tab:hover, .tab.active {
            background: #F97316;
            color: white;
        }
        
        .panel {
            display: none;
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .panel.active { display: block; }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            text-align: left;
            padding: 12px;
            border-bottom: 1px solid #E2E8F0;
        }
        
        th {
            font-weight: 600;
            color: #64748B;
            font-size: 12px;
            text-transform: uppercase;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-active { background: #DCFCE7; color: #166534; }
        .badge-banned { background: #FEE2E2; color: #991B1B; }
        .badge-guest { background: #F1F5F9; color: #475569; }
        .badge-google { background: #DBEAFE; color: #1E40AF; }
        
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
        }
        
        .btn-ban { background: #FEE2E2; color: #991B1B; }
        .btn-unban { background: #DCFCE7; color: #166534; }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            max-width: 300px;
            padding: 12px 16px;
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            font-size: 14px;
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #F97316;
        }
        
        .btn-save {
            padding: 14px 32px;
            background: #F97316;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-save:hover { background: #EA580C; }
        
        .alert {
            padding: 12px 20px;
            background: #DCFCE7;
            color: #166534;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <div class="logo-icon">V</div>
                Vitalia Admin
            </h1>
            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Siteye Dön
            </a>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><i class="fas fa-users"></i> Toplam Kullanıcı</h3>
                <div class="value"><?= $totalUsers ?></div>
                <div class="sub">+<?= $newUsers ?> bu hafta</div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-comments"></i> Toplam Mesaj</h3>
                <div class="value"><?= $totalMessages ?></div>
                <div class="sub"><?= $todayMessages ?> bugün</div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-robot"></i> AI Çağrıları</h3>
                <div class="value"><?= $aiCalls ?></div>
                <div class="sub">bugün</div>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="showPanel('users')">
                <i class="fas fa-users"></i> Kullanıcılar
            </button>
            <button class="tab" onclick="showPanel('settings')">
                <i class="fas fa-cog"></i> Ayarlar
            </button>
        </div>
        
        <!-- Users Panel -->
        <div class="panel active" id="usersPanel">
            <table>
                <thead>
                    <tr>
                        <th>Kullanıcı</th>
                        <th>Sağlayıcı</th>
                        <th>Durum</th>
                        <th>Kayıt</th>
                        <th>Son Aktif</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($user['name'] ?: 'Anonim') ?></strong><br>
                            <small style="color:#64748B"><?= htmlspecialchars($user['email'] ?: $user['user_id']) ?></small>
                        </td>
                        <td>
                            <span class="badge badge-<?= $user['auth_provider'] ?>">
                                <?= $user['auth_provider'] ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-<?= $user['status'] ?>">
                                <?= $user['status'] ?>
                            </span>
                        </td>
                        <td><?= date('d.m.Y', strtotime($user['created_at'])) ?></td>
                        <td><?= $user['last_active_at'] ? date('d.m.Y H:i', strtotime($user['last_active_at'])) : '-' ?></td>
                        <td>
                            <?php if ($user['status'] === 'active'): ?>
                            <a href="?ban=<?= $user['user_id'] ?>" class="action-btn btn-ban" onclick="return confirm('Bu kullanıcıyı banlamak istediğinize emin misiniz?')">
                                <i class="fas fa-ban"></i> Ban
                            </a>
                            <?php else: ?>
                            <a href="?unban=<?= $user['user_id'] ?>" class="action-btn btn-unban">
                                <i class="fas fa-check"></i> Unban
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Settings Panel -->
        <div class="panel" id="settingsPanel">
            <?php if (isset($_GET['saved'])): ?>
            <div class="alert">
                <i class="fas fa-check-circle"></i> Ayarlar başarıyla kaydedildi!
            </div>
            <?php endif; ?>
            
            <form method="POST">
                <h3 style="margin-bottom:20px">OpenAI Ayarları</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Model</label>
                        <input type="text" name="openai_model" value="<?= htmlspecialchars($settings['openai_model'] ?? 'gpt-4o-mini') ?>">
                    </div>
                    <div class="form-group">
                        <label>Max Tokens</label>
                        <input type="number" name="openai_max_tokens" value="<?= $settings['openai_max_tokens'] ?? 500 ?>">
                    </div>
                </div>
                
                <h3 style="margin-bottom:20px; margin-top:30px">Limitler</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Günlük Mesaj (Anonim)</label>
                        <input type="number" name="daily_message_limit_guest" value="<?= $settings['daily_message_limit_guest'] ?? 10 ?>">
                    </div>
                    <div class="form-group">
                        <label>Günlük Mesaj (Üye)</label>
                        <input type="number" name="daily_message_limit_user" value="<?= $settings['daily_message_limit_user'] ?? 50 ?>">
                    </div>
                </div>
                
                <h3 style="margin-bottom:20px; margin-top:30px">Sağlık Hesaplamaları</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Su (ml/kg)</label>
                        <input type="number" name="water_ml_per_kg" value="<?= $settings['water_ml_per_kg'] ?? 30 ?>">
                    </div>
                    <div class="form-group">
                        <label>Bardak (ml)</label>
                        <input type="number" name="glass_ml" value="<?= $settings['glass_ml'] ?? 250 ?>">
                    </div>
                </div>
                
                <button type="submit" name="save_settings" class="btn-save">
                    <i class="fas fa-save"></i> Kaydet
                </button>
            </form>
        </div>
    </div>
    
    <script>
        function showPanel(name) {
            document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.getElementById(name + 'Panel').classList.add('active');
            event.target.closest('.tab').classList.add('active');
        }
    </script>
</body>
</html>
