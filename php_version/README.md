# Vitalia - PHP + MySQL Version
# Chat-First Personal Health Advisor

Bu klasör, Vitalia uygulamasının paylaşımlı Linux hosting için PHP + MySQL versiyonunu içerir.

## Dosya Yapısı

```
php_version/
├── config.php          # Veritabanı ve API yapılandırması
├── database.sql        # MySQL veritabanı şeması
├── api/
│   └── chat.php        # Ana chat API endpoint'i
└── README.md           # Bu dosya
```

## Kurulum Adımları

### 1. Veritabanı Kurulumu

1. phpMyAdmin veya MySQL komut satırından `database.sql` dosyasını import edin:
```sql
mysql -u kullanici -p vitalia_db < database.sql
```

2. Admin kullanıcı email'ini güncelleyin:
```sql
UPDATE users SET email = 'sizin@email.com' WHERE user_id = 'admin_001';
```

### 2. Yapılandırma

`config.php` dosyasındaki aşağıdaki değerleri güncelleyin:

```php
// Veritabanı
define('DB_HOST', 'localhost');
define('DB_NAME', 'vitalia_db');
define('DB_USER', 'veritabani_kullanici');
define('DB_PASS', 'veritabani_sifre');

// OpenAI
define('OPENAI_API_KEY', 'sk-...');

// Google OAuth
define('GOOGLE_CLIENT_ID', '...');
define('GOOGLE_CLIENT_SECRET', '...');
define('GOOGLE_REDIRECT_URI', 'https://siteniz.com/auth/google/callback');

// Uygulama
define('APP_URL', 'https://siteniz.com');
```

### 3. Frontend Dosyaları

React frontend'i build edin ve `public` klasörüne kopyalayın:
```bash
cd frontend
yarn build
cp -r build/* /path/to/public_html/
```

### 4. .htaccess Ayarları

Ana dizine `.htaccess` dosyası ekleyin:
```apache
RewriteEngine On
RewriteBase /

# API yönlendirmesi
RewriteRule ^api/chat$ api/chat.php [L]
RewriteRule ^api/log/(.*)$ api/log.php?action=$1 [L]
RewriteRule ^api/auth/(.*)$ api/auth.php?action=$1 [L]
RewriteRule ^api/admin/(.*)$ api/admin.php?action=$1 [L]

# React SPA için
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.html [L]
```

## API Endpoints

### Chat
- `POST /api/chat` - Mesaj gönder

### Logging
- `POST /api/log/quick-action` - Hızlı eylem (su, adım, spor, kilo)
- `GET /api/log/today` - Bugünkü log
- `GET /api/log/history` - Geçmiş loglar

### Auth
- `POST /api/auth/session` - Google OAuth sonrası session oluştur
- `GET /api/auth/me` - Mevcut kullanıcı
- `POST /api/auth/logout` - Çıkış

### Admin
- `GET /api/admin/dashboard` - Dashboard istatistikleri
- `GET /api/admin/users` - Kullanıcı listesi
- `GET /api/admin/settings` - Ayarlar
- `POST /api/admin/settings` - Ayarları güncelle

## Güvenlik Notları

1. `config.php` dosyasını web erişimine kapatın
2. SSL sertifikası kullanın
3. Rate limiting aktif tutun
4. Düzenli veritabanı yedeği alın

## Destek

Herhangi bir sorunuz için GitHub Issues kullanabilirsiniz.
