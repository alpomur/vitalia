# Vitalia - PHP + MySQL Version
# Chat-First Personal Health Advisor

Bu klasör, Vitalia uygulamasının paylaşımlı Linux hosting için PHP + MySQL versiyonunu içerir.

## Dosya Yapısı

```
php_version/
├── config.php          # Veritabanı ve API yapılandırması
├── database.sql        # MySQL veritabanı şeması
├── index.php           # API Router (tüm istekleri yönlendirir)
├── server-config.md    # Apache/Nginx yapılandırması
├── api/
│   ├── chat.php        # Chat API endpoint'leri
│   ├── log.php         # Daily log endpoint'leri  
│   ├── auth.php        # Authentication endpoint'leri
│   ├── admin.php       # Admin panel endpoint'leri
│   └── profile.php     # Profil endpoint'leri
└── README.md           # Bu dosya
```

## API Endpoints

### Chat
| Endpoint | Method | Açıklama |
|----------|--------|----------|
| `/api/chat` | POST | Mesaj gönder |
| `/api/chat/welcome` | GET | Hoşgeldin mesajı |
| `/api/chat/history` | GET | Chat geçmişi |

### Daily Logs
| Endpoint | Method | Açıklama |
|----------|--------|----------|
| `/api/log/quick-action` | POST | Hızlı eylem (su, adım, spor, kilo) |
| `/api/log/today` | GET | Bugünkü log |
| `/api/log/history` | GET | Geçmiş loglar |

### Authentication
| Endpoint | Method | Açıklama |
|----------|--------|----------|
| `/api/auth/session` | POST | Google OAuth sonrası session |
| `/api/auth/me` | GET | Mevcut kullanıcı |
| `/api/auth/logout` | POST | Çıkış |
| `/api/auth/google` | GET | Google OAuth başlat |
| `/api/auth/google/callback` | GET | OAuth callback |

### Profile
| Endpoint | Method | Açıklama |
|----------|--------|----------|
| `/api/profile` | GET | Profil getir |
| `/api/profile` | POST | Profil güncelle |

### Admin
| Endpoint | Method | Açıklama |
|----------|--------|----------|
| `/api/admin/dashboard` | GET | Dashboard istatistikleri |
| `/api/admin/users` | GET | Kullanıcı listesi |
| `/api/admin/users/{id}/ban` | POST | Kullanıcı ban/unban |
| `/api/admin/settings` | GET/POST | Sistem ayarları |
| `/api/admin/faq` | GET/POST | FAQ yönetimi |

## Kurulum Adımları

### 1. Veritabanı Kurulumu

```bash
# phpMyAdmin veya MySQL CLI ile
mysql -u kullanici -p vitalia_db < database.sql
```

### 2. config.php Yapılandırması

```php
// Veritabanı
define('DB_HOST', 'localhost');
define('DB_NAME', 'vitalia_db');
define('DB_USER', 'veritabani_kullanici');
define('DB_PASS', 'veritabani_sifre');

// OpenAI API
define('OPENAI_API_KEY', 'sk-...');

// Google OAuth (opsiyonel)
define('GOOGLE_CLIENT_ID', '...');
define('GOOGLE_CLIENT_SECRET', '...');
define('GOOGLE_REDIRECT_URI', 'https://siteniz.com/api/auth/google/callback');

// Site URL
define('APP_URL', 'https://siteniz.com');
```

### 3. Frontend Build

```bash
cd /app/frontend
yarn build
# build klasörünü public_html'e kopyala
```

### 4. Dosya Yükleme

Paylaşımlı hosting'e yükle:
```
public_html/
├── index.html      # React build
├── index.php       # API router  
├── config.php      # Yapılandırma
├── api/            # API dosyaları
├── static/         # React static
└── .htaccess       # server-config.md'den kopyala
```

### 5. .htaccess Ayarları

`server-config.md` dosyasındaki Apache konfigürasyonunu `.htaccess` olarak kaydet.

## Güvenlik Notları

1. ✅ `config.php` web erişimine kapalı (.htaccess ile)
2. ✅ SSL sertifikası kullanın
3. ✅ Rate limiting aktif
4. ✅ Prepared statements ile SQL injection koruması
5. ✅ XSS koruması (JSON output)

## Test

```bash
# API health check
curl https://siteniz.com/api/health

# Chat test
curl -X POST https://siteniz.com/api/chat \
  -H "Content-Type: application/json" \
  -d '{"message": "Merhaba!", "language": "tr"}'
```

## Gereksinimler

- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- Apache mod_rewrite veya Nginx
- SSL sertifikası
- PHP Extensions: curl, json, pdo_mysql, mbstring

## Destek

Sorunlar için GitHub Issues kullanın.
