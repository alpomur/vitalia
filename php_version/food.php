<?php
/**
 * Vitalia - Dışarıda Yemek Rehberi
 * Restoran türüne göre sağlıklı seçimler
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/languages.php';
session_start();

$lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'tr';
$_SESSION['lang'] = $lang;
$t = $GLOBALS['translations'][$lang] ?? $GLOBALS['translations']['tr'];

// Dışarıda Yemek Rehberi
$restaurants = [
    'fast_food' => [
        'name' => 'Fast Food',
        'icon' => 'fa-burger',
        'best_choices' => [
            'Izgara tavuk sandviç (sossuz)',
            'Salata (sosunu kenarda iste)',
            'Izgara tavuk wrap',
            'Küçük boy menü',
            'Su veya ayran',
            'Meyve dilimi (varsa)'
        ],
        'avoid' => [
            'Kızartılmış ürünler',
            'Büyük boy içecekler',
            'Ekstra sos ve peynir',
            'Tatlılar'
        ],
        'tips' => [
            'Menü yerine tek ürün al',
            'Kızarmış yerine ızgara tercih et',
            'İçecek olarak su seç',
            'Sosları kenarda iste'
        ]
    ],
    'pizza' => [
        'name' => 'Pizza',
        'icon' => 'fa-pizza-slice',
        'best_choices' => [
            'İnce hamurlu pizza',
            'Sebzeli pizza',
            'Ton balıklı pizza',
            'Mantar ve sebze garnitürlü',
            'Yanında yeşil salata',
            '1-2 dilim ile sınırla'
        ],
        'avoid' => [
            'Kalın hamur / dolgulu kenar',
            'Ekstra peynir',
            'Sucuk ve salam',
            'Kremalı soslar'
        ],
        'tips' => [
            'Paylaşarak ye',
            'Salata ile başla',
            'Yağlı kağıtla fazla yağı em',
            'Su tüketimini artır'
        ]
    ],
    'kebap' => [
        'name' => 'Kebapçı',
        'icon' => 'fa-utensils',
        'best_choices' => [
            'Izgara köfte',
            'Tavuk şiş',
            'Adana kebap (1 porsiyon)',
            'Salata ve söğüş',
            'Ayran',
            'Közlenmiş sebzeler'
        ],
        'avoid' => [
            'Lahmacun + pide kombinasyonu',
            'Aşırı ekmek tüketimi',
            'Yağlı etler',
            'Şekerli içecekler'
        ],
        'tips' => [
            'Ekmek miktarını sınırla',
            'Söğüş ve salata ekle',
            'Izgara seçenekleri tercih et',
            'Pide yerine kebap seç'
        ]
    ],
    'asian' => [
        'name' => 'Uzak Doğu',
        'icon' => 'fa-bowl-rice',
        'best_choices' => [
            'Buğulanmış sebzeli yemekler',
            'Edamame',
            'Sashimi',
            'Sebzeli sushi roll',
            'Miso çorbası',
            'Teriyaki tavuk (az soslu)'
        ],
        'avoid' => [
            'Kızarmış pirinç',
            'Tempura',
            'Tatlı soslu yemekler',
            'Kızarmış noodle'
        ],
        'tips' => [
            'Buğulanmış > Kızarmış',
            'Soya sosunu sınırlı kullan',
            'Çubukla ye (daha yavaş yersin)',
            'Yeşil çay iç'
        ]
    ],
    'cafe' => [
        'name' => 'Kafe / Salata Bar',
        'icon' => 'fa-leaf',
        'best_choices' => [
            'Izgara tavuklu salata',
            'Ton balıklı salata',
            'Avokado toast (tam buğday)',
            'Yumurtalı kahvaltı tabağı',
            'Filtre kahve',
            'Meyve tabağı'
        ],
        'avoid' => [
            'Kremalı soslar',
            'Şekerli kahve içecekleri',
            'Pasta ve kurabiye',
            'Kızartılmış aperatifler'
        ],
        'tips' => [
            'Sosu kenarda iste',
            'Protein ekle (tavuk, yumurta)',
            'Kahvene şeker ekleme',
            'Smoothie yerine meyve ye'
        ]
    ],
    'breakfast' => [
        'name' => 'Kahvaltıcı',
        'icon' => 'fa-egg',
        'best_choices' => [
            'Haşlanmış yumurta',
            'Beyaz peynir',
            'Domates, salatalık',
            'Zeytin (5-6 adet)',
            'Tam buğday ekmek',
            'Bal + tahin (az miktar)'
        ],
        'avoid' => [
            'Sucuk, sosis',
            'Aşırı ekmek',
            'Reçel, nutella',
            'Hazır meyve suları'
        ],
        'tips' => [
            'Serpme yerine porsiyon al',
            'Sebzelere öncelik ver',
            'Ekmek miktarını kontrol et',
            'Çay yanında su iç'
        ]
    ]
];

$selectedType = $_GET['type'] ?? null;
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $t['dir'] ?? 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $t['outsideFood'] ?> - Vitalia</title>
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
        
        .container { max-width: 800px; margin: 0 auto; }
        
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
        
        .restaurant-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 30px;
        }
        
        .restaurant-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            color: inherit;
            border: 2px solid transparent;
        }
        
        .restaurant-card:hover, .restaurant-card.active {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            border-color: #F97316;
        }
        
        .restaurant-card i {
            font-size: 32px;
            color: #F97316;
            margin-bottom: 12px;
        }
        
        .restaurant-card h3 {
            font-size: 14px;
            font-weight: 600;
        }
        
        .detail-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .detail-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #E2E8F0;
        }
        
        .detail-header i {
            font-size: 40px;
            color: #F97316;
        }
        
        .detail-header h2 {
            font-size: 24px;
        }
        
        .section {
            margin-bottom: 24px;
        }
        
        .section h3 {
            font-size: 16px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .section h3 i { font-size: 16px; }
        
        .best-choices h3 i { color: #22C55E; }
        .avoid h3 i { color: #EF4444; }
        .tips h3 i { color: #0EA5E9; }
        
        .list {
            list-style: none;
            display: grid;
            gap: 8px;
        }
        
        .list li {
            padding: 10px 14px;
            background: #F8FAFC;
            border-radius: 8px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .best-choices .list li::before {
            content: '✓';
            color: #22C55E;
            font-weight: bold;
        }
        
        .avoid .list li::before {
            content: '✗';
            color: #EF4444;
            font-weight: bold;
        }
        
        .tips .list li::before {
            content: '💡';
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <i class="fas fa-utensils" style="color:#F97316"></i>
                <?= $t['outsideFood'] ?>
            </h1>
            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Geri
            </a>
        </div>
        
        <!-- Restoran Türleri -->
        <div class="restaurant-grid">
            <?php foreach ($restaurants as $key => $rest): ?>
            <a href="?type=<?= $key ?>" class="restaurant-card <?= $selectedType === $key ? 'active' : '' ?>">
                <i class="fas <?= $rest['icon'] ?>"></i>
                <h3><?= htmlspecialchars($rest['name']) ?></h3>
            </a>
            <?php endforeach; ?>
        </div>
        
        <?php if ($selectedType && isset($restaurants[$selectedType])): 
            $rest = $restaurants[$selectedType];
        ?>
        <!-- Detay Kartı -->
        <div class="detail-card">
            <div class="detail-header">
                <i class="fas <?= $rest['icon'] ?>"></i>
                <h2><?= htmlspecialchars($rest['name']) ?> Rehberi</h2>
            </div>
            
            <div class="section best-choices">
                <h3><i class="fas fa-check-circle"></i> En İyi Seçimler</h3>
                <ul class="list">
                    <?php foreach ($rest['best_choices'] as $item): ?>
                    <li><?= htmlspecialchars($item) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <div class="section avoid">
                <h3><i class="fas fa-times-circle"></i> Kaçınılması Gerekenler</h3>
                <ul class="list">
                    <?php foreach ($rest['avoid'] as $item): ?>
                    <li><?= htmlspecialchars($item) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <div class="section tips">
                <h3><i class="fas fa-lightbulb"></i> İpuçları</h3>
                <ul class="list">
                    <?php foreach ($rest['tips'] as $item): ?>
                    <li><?= htmlspecialchars($item) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php else: ?>
        <div class="detail-card" style="text-align:center; padding:40px;">
            <i class="fas fa-hand-pointer" style="font-size:48px; color:#E2E8F0; margin-bottom:16px;"></i>
            <p style="color:#64748B;">Rehberi görmek için yukarıdan bir restoran türü seçin</p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
