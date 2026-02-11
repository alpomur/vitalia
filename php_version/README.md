# Vitalia - Düz PHP + MySQL Versiyonu

Paylaşımlı hosting için hazır, React gerektirmeyen saf PHP çözümü.

## Dosyalar

```
php_version/
├── index.php       # Ana sayfa (Chat arayüzü - HTML/CSS/JS içerir)
├── api.php         # Tüm API endpoint'leri
├── admin.php       # Yönetim paneli
├── config.php      # Yapılandırma dosyası
├── database.sql    # MySQL veritabanı şeması
├── .htaccess       # Apache ayarları
└── README.md       # Bu dosya
```

## Kurulum (5 Dakika)

### 1. Dosyaları Yükle
Tüm dosyaları FTP ile hosting'e yükleyin.

### 2. Veritabanı Oluştur
cPanel > MySQL Databases'den yeni veritabanı oluşturun.
phpMyAdmin'den `database.sql` dosyasını import edin.

### 3. config.php Düzenle
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'kullanici_vitalia');  // cPanel'den
define('DB_USER', 'kullanici_dbuser');    // cPanel'den
define('DB_PASS', 'sifreniz');

define('OPENAI_API_KEY', 'sk-...');  // OpenAI API anahtarınız
```

### 4. Test Et
- `https://siteniz.com/` - Chat sayfası
- `https://siteniz.com/admin.php` - Yönetim paneli

## Özellikler

✅ Chat arayüzü (AI destekli)
✅ Su takibi
✅ Hareket seviyesi
✅ Spor kaydı
✅ Kilo girişi
✅ Profil yönetimi
✅ Dark mode
✅ Türkçe/İngilizce dil desteği
✅ Admin paneli
✅ Kullanıcı yönetimi
✅ Ayarlar yönetimi
✅ Konfeti efekti

## API Endpoint'leri (api.php)

| Action | Method | Açıklama |
|--------|--------|----------|
| chat_send | POST | Mesaj gönder |
| chat_history | GET | Chat geçmişi |
| quick_action | POST | Su/adım/spor/kilo |
| log_today | GET | Bugünkü veriler |
| profile_save | POST | Profil kaydet |

## Notlar

- OpenAI API anahtarı gerekli
- PHP 7.4+ gerekli
- MySQL 5.7+ gerekli
- cURL extension gerekli
