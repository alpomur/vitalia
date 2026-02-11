<?php
/**
 * Vitalia - Spor Planları Sayfası
 * Detaylı egzersiz programları (set/tekrar)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/languages.php';
session_start();

$lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'tr';
$_SESSION['lang'] = $lang;
$t = $GLOBALS['translations'][$lang] ?? $GLOBALS['translations']['tr'];

// Spor Planları Veritabanı
$workoutPlans = [
    'beginner' => [
        'home' => [
            'name' => 'Başlangıç - Ev',
            'days' => 3,
            'schedule' => [
                1 => [
                    'title' => 'Tam Vücut A',
                    'exercises' => [
                        ['name' => 'Squat', 'sets' => 3, 'reps' => '10-12', 'rest' => 60],
                        ['name' => 'Push-up (Dizden)', 'sets' => 3, 'reps' => '8-10', 'rest' => 60],
                        ['name' => 'Plank', 'sets' => 3, 'reps' => '20-30 sn', 'rest' => 45],
                        ['name' => 'Glute Bridge', 'sets' => 3, 'reps' => '12-15', 'rest' => 45],
                        ['name' => 'Superman', 'sets' => 3, 'reps' => '10-12', 'rest' => 45]
                    ]
                ],
                2 => [
                    'title' => 'Kardiyo + Core',
                    'exercises' => [
                        ['name' => 'Jumping Jack', 'sets' => 3, 'reps' => '30 sn', 'rest' => 30],
                        ['name' => 'High Knees', 'sets' => 3, 'reps' => '20 sn', 'rest' => 30],
                        ['name' => 'Mountain Climber', 'sets' => 3, 'reps' => '20', 'rest' => 45],
                        ['name' => 'Bicycle Crunch', 'sets' => 3, 'reps' => '15', 'rest' => 45],
                        ['name' => 'Dead Bug', 'sets' => 3, 'reps' => '10', 'rest' => 45]
                    ]
                ],
                3 => [
                    'title' => 'Tam Vücut B',
                    'exercises' => [
                        ['name' => 'Lunge', 'sets' => 3, 'reps' => '10 (her bacak)', 'rest' => 60],
                        ['name' => 'Incline Push-up', 'sets' => 3, 'reps' => '10-12', 'rest' => 60],
                        ['name' => 'Side Plank', 'sets' => 2, 'reps' => '20 sn (her taraf)', 'rest' => 45],
                        ['name' => 'Bird Dog', 'sets' => 3, 'reps' => '8 (her taraf)', 'rest' => 45],
                        ['name' => 'Wall Sit', 'sets' => 3, 'reps' => '30 sn', 'rest' => 60]
                    ]
                ]
            ]
        ],
        'gym' => [
            'name' => 'Başlangıç - Salon',
            'days' => 3,
            'schedule' => [
                1 => [
                    'title' => 'Üst Vücut',
                    'exercises' => [
                        ['name' => 'Lat Pulldown', 'sets' => 3, 'reps' => '10-12', 'rest' => 60],
                        ['name' => 'Chest Press (Makine)', 'sets' => 3, 'reps' => '10-12', 'rest' => 60],
                        ['name' => 'Seated Row', 'sets' => 3, 'reps' => '10-12', 'rest' => 60],
                        ['name' => 'Shoulder Press (Makine)', 'sets' => 3, 'reps' => '10-12', 'rest' => 60],
                        ['name' => 'Bicep Curl', 'sets' => 2, 'reps' => '12-15', 'rest' => 45]
                    ]
                ],
                2 => [
                    'title' => 'Alt Vücut',
                    'exercises' => [
                        ['name' => 'Leg Press', 'sets' => 3, 'reps' => '10-12', 'rest' => 90],
                        ['name' => 'Leg Curl', 'sets' => 3, 'reps' => '10-12', 'rest' => 60],
                        ['name' => 'Leg Extension', 'sets' => 3, 'reps' => '10-12', 'rest' => 60],
                        ['name' => 'Calf Raise', 'sets' => 3, 'reps' => '15-20', 'rest' => 45],
                        ['name' => 'Hip Abductor', 'sets' => 3, 'reps' => '12-15', 'rest' => 45]
                    ]
                ],
                3 => [
                    'title' => 'Tam Vücut',
                    'exercises' => [
                        ['name' => 'Goblet Squat', 'sets' => 3, 'reps' => '10-12', 'rest' => 60],
                        ['name' => 'Dumbbell Row', 'sets' => 3, 'reps' => '10 (her kol)', 'rest' => 60],
                        ['name' => 'Dumbbell Bench Press', 'sets' => 3, 'reps' => '10-12', 'rest' => 60],
                        ['name' => 'Romanian Deadlift', 'sets' => 3, 'reps' => '10-12', 'rest' => 60],
                        ['name' => 'Plank', 'sets' => 3, 'reps' => '30 sn', 'rest' => 45]
                    ]
                ]
            ]
        ]
    ],
    'intermediate' => [
        'home' => [
            'name' => 'Orta Seviye - Ev',
            'days' => 4,
            'schedule' => [
                1 => [
                    'title' => 'Üst Vücut',
                    'exercises' => [
                        ['name' => 'Push-up', 'sets' => 4, 'reps' => '12-15', 'rest' => 60],
                        ['name' => 'Pike Push-up', 'sets' => 3, 'reps' => '8-10', 'rest' => 60],
                        ['name' => 'Diamond Push-up', 'sets' => 3, 'reps' => '8-10', 'rest' => 60],
                        ['name' => 'Dips (Sandalye)', 'sets' => 3, 'reps' => '10-12', 'rest' => 60],
                        ['name' => 'Isometric Hold', 'sets' => 3, 'reps' => '20 sn', 'rest' => 45]
                    ]
                ],
                2 => [
                    'title' => 'Alt Vücut',
                    'exercises' => [
                        ['name' => 'Bulgarian Split Squat', 'sets' => 3, 'reps' => '10 (her bacak)', 'rest' => 60],
                        ['name' => 'Single Leg Glute Bridge', 'sets' => 3, 'reps' => '12 (her bacak)', 'rest' => 45],
                        ['name' => 'Jump Squat', 'sets' => 3, 'reps' => '12', 'rest' => 60],
                        ['name' => 'Curtsy Lunge', 'sets' => 3, 'reps' => '10 (her bacak)', 'rest' => 45],
                        ['name' => 'Calf Raise', 'sets' => 4, 'reps' => '20', 'rest' => 30]
                    ]
                ],
                3 => [
                    'title' => 'Core & Kardiyo',
                    'exercises' => [
                        ['name' => 'Burpee', 'sets' => 4, 'reps' => '10', 'rest' => 60],
                        ['name' => 'Russian Twist', 'sets' => 3, 'reps' => '20', 'rest' => 45],
                        ['name' => 'Leg Raise', 'sets' => 3, 'reps' => '12', 'rest' => 45],
                        ['name' => 'V-Up', 'sets' => 3, 'reps' => '10', 'rest' => 45],
                        ['name' => 'Plank to Push-up', 'sets' => 3, 'reps' => '10', 'rest' => 60]
                    ]
                ],
                4 => [
                    'title' => 'Tam Vücut HIIT',
                    'exercises' => [
                        ['name' => 'Squat Jump', 'sets' => 4, 'reps' => '15', 'rest' => 45],
                        ['name' => 'Push-up', 'sets' => 4, 'reps' => '12', 'rest' => 45],
                        ['name' => 'Lunge Jump', 'sets' => 3, 'reps' => '10 (her bacak)', 'rest' => 45],
                        ['name' => 'Mountain Climber', 'sets' => 4, 'reps' => '30 sn', 'rest' => 30],
                        ['name' => 'Plank', 'sets' => 3, 'reps' => '45 sn', 'rest' => 30]
                    ]
                ]
            ]
        ],
        'gym' => [
            'name' => 'Orta Seviye - Salon',
            'days' => 4,
            'schedule' => [
                1 => [
                    'title' => 'Göğüs & Triceps',
                    'exercises' => [
                        ['name' => 'Bench Press', 'sets' => 4, 'reps' => '8-10', 'rest' => 90],
                        ['name' => 'Incline Dumbbell Press', 'sets' => 3, 'reps' => '10-12', 'rest' => 60],
                        ['name' => 'Cable Fly', 'sets' => 3, 'reps' => '12-15', 'rest' => 60],
                        ['name' => 'Tricep Pushdown', 'sets' => 3, 'reps' => '12-15', 'rest' => 45],
                        ['name' => 'Overhead Tricep Extension', 'sets' => 3, 'reps' => '12', 'rest' => 45]
                    ]
                ],
                2 => [
                    'title' => 'Sırt & Biceps',
                    'exercises' => [
                        ['name' => 'Deadlift', 'sets' => 4, 'reps' => '6-8', 'rest' => 120],
                        ['name' => 'Pull-up / Lat Pulldown', 'sets' => 4, 'reps' => '8-10', 'rest' => 90],
                        ['name' => 'Barbell Row', 'sets' => 3, 'reps' => '10-12', 'rest' => 60],
                        ['name' => 'Face Pull', 'sets' => 3, 'reps' => '15', 'rest' => 45],
                        ['name' => 'Bicep Curl', 'sets' => 3, 'reps' => '12-15', 'rest' => 45]
                    ]
                ],
                3 => [
                    'title' => 'Bacak',
                    'exercises' => [
                        ['name' => 'Squat', 'sets' => 4, 'reps' => '8-10', 'rest' => 120],
                        ['name' => 'Romanian Deadlift', 'sets' => 3, 'reps' => '10-12', 'rest' => 90],
                        ['name' => 'Leg Press', 'sets' => 3, 'reps' => '12-15', 'rest' => 90],
                        ['name' => 'Leg Curl', 'sets' => 3, 'reps' => '12-15', 'rest' => 60],
                        ['name' => 'Standing Calf Raise', 'sets' => 4, 'reps' => '15-20', 'rest' => 45]
                    ]
                ],
                4 => [
                    'title' => 'Omuz & Core',
                    'exercises' => [
                        ['name' => 'Overhead Press', 'sets' => 4, 'reps' => '8-10', 'rest' => 90],
                        ['name' => 'Lateral Raise', 'sets' => 3, 'reps' => '12-15', 'rest' => 45],
                        ['name' => 'Rear Delt Fly', 'sets' => 3, 'reps' => '12-15', 'rest' => 45],
                        ['name' => 'Cable Crunch', 'sets' => 3, 'reps' => '15', 'rest' => 45],
                        ['name' => 'Hanging Leg Raise', 'sets' => 3, 'reps' => '10-12', 'rest' => 60]
                    ]
                ]
            ]
        ]
    ]
];

$selectedLevel = $_GET['level'] ?? 'beginner';
$selectedLocation = $_GET['location'] ?? 'home';
$plan = $workoutPlans[$selectedLevel][$selectedLocation] ?? $workoutPlans['beginner']['home'];
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $t['dir'] ?? 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $t['workoutPlans'] ?> - Vitalia</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'DM Sans', sans-serif;
            background: #F8FAFC;
            color: #0F172A;
            min-height: 100vh;
            padding: 20px;
        }
        h1, h2, h3 { font-family: 'Outfit', sans-serif; }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
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
        
        .back-link {
            color: #64748B;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .back-link:hover { color: #F97316; }
        
        .filters {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            gap: 8px;
        }
        
        .filter-btn {
            padding: 10px 20px;
            background: white;
            border: 2px solid #E2E8F0;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
            color: #0F172A;
        }
        
        .filter-btn:hover, .filter-btn.active {
            border-color: #F97316;
            background: rgba(249,115,22,0.1);
            color: #F97316;
        }
        
        .plan-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .plan-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #E2E8F0;
        }
        
        .plan-title {
            font-size: 18px;
            font-weight: 700;
        }
        
        .day-badge {
            background: #F97316;
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .exercise-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .exercise-table th {
            text-align: left;
            padding: 12px;
            background: #F8FAFC;
            font-size: 12px;
            font-weight: 600;
            color: #64748B;
            text-transform: uppercase;
        }
        
        .exercise-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #F1F5F9;
        }
        
        .exercise-table tr:last-child td {
            border-bottom: none;
        }
        
        .exercise-name {
            font-weight: 600;
        }
        
        .exercise-detail {
            color: #64748B;
            font-size: 14px;
        }
        
        .stats {
            display: flex;
            gap: 16px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #E2E8F0;
        }
        
        .stat {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #64748B;
        }
        
        .stat i { color: #F97316; }
        
        @media (max-width: 600px) {
            .exercise-table th:nth-child(4),
            .exercise-table td:nth-child(4) {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <i class="fas fa-dumbbell" style="color:#F97316"></i>
                <?= $t['workoutPlans'] ?>
            </h1>
            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Geri
            </a>
        </div>
        
        <!-- Filtreler -->
        <div class="filters">
            <div class="filter-group">
                <a href="?level=beginner&location=<?= $selectedLocation ?>" 
                   class="filter-btn <?= $selectedLevel === 'beginner' ? 'active' : '' ?>">
                    <?= $t['beginner'] ?>
                </a>
                <a href="?level=intermediate&location=<?= $selectedLocation ?>" 
                   class="filter-btn <?= $selectedLevel === 'intermediate' ? 'active' : '' ?>">
                    <?= $t['intermediate'] ?>
                </a>
            </div>
            <div class="filter-group">
                <a href="?level=<?= $selectedLevel ?>&location=home" 
                   class="filter-btn <?= $selectedLocation === 'home' ? 'active' : '' ?>">
                    <i class="fas fa-home"></i> <?= $t['home'] ?>
                </a>
                <a href="?level=<?= $selectedLevel ?>&location=gym" 
                   class="filter-btn <?= $selectedLocation === 'gym' ? 'active' : '' ?>">
                    <i class="fas fa-dumbbell"></i> <?= $t['gym'] ?>
                </a>
            </div>
        </div>
        
        <!-- Plan Bilgisi -->
        <div class="plan-card">
            <h2><?= htmlspecialchars($plan['name']) ?></h2>
            <div class="stats">
                <span class="stat"><i class="fas fa-calendar"></i> <?= $plan['days'] ?> <?= $t['day'] ?>/hafta</span>
            </div>
        </div>
        
        <!-- Günlük Programlar -->
        <?php foreach ($plan['schedule'] as $dayNum => $day): ?>
        <div class="plan-card">
            <div class="plan-header">
                <span class="plan-title"><?= htmlspecialchars($day['title']) ?></span>
                <span class="day-badge"><?= $t['day'] ?> <?= $dayNum ?></span>
            </div>
            
            <table class="exercise-table">
                <thead>
                    <tr>
                        <th>Egzersiz</th>
                        <th><?= $t['sets'] ?></th>
                        <th><?= $t['reps'] ?></th>
                        <th><?= $t['rest'] ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($day['exercises'] as $exercise): ?>
                    <tr>
                        <td class="exercise-name"><?= htmlspecialchars($exercise['name']) ?></td>
                        <td><?= $exercise['sets'] ?></td>
                        <td><?= $exercise['reps'] ?></td>
                        <td class="exercise-detail"><?= $exercise['rest'] ?> <?= $t['seconds'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
