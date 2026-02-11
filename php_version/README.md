# Vitalia - Düz PHP + MySQL Versiyonu
### Chat-First Kişisel Sağlıklı Yaşam Danışmanı

Paylaşımlı hosting için hazır, React gerektirmeyen saf PHP çözümü.

## Dosya Yapısı

```
php_version/
├── index.php        # Ana sayfa - Chat arayüzü (HTML/CSS/JS dahil)
├── api.php          # Tüm API endpoint'leri (cache, rate limit dahil)
├── admin.php        # Yönetim paneli (kullanıcılar, ayarlar, istatistikler)
├── workout.php      # Spor planları sayfası (set/tekrar detaylı)
├── food.php         # Dışarıda yemek rehberi (restoran türleri)
├── config.php       # Yapılandırma dosyası
├── helpers.php      # Yardımcı fonksiyonlar (cache, rate limit, güvenlik)
├── languages.php    # 10 dil çevirileri
├── database.sql     # MySQL veritabanı şeması (tüm tablolar)
├── .htaccess        # Apache ayarları
└── README.md        # Bu dosya
```

## Özellikler

### Chat Sistemi
- ✅ OpenAI GPT-4o-mini entegrasyonu
- ✅ Mesaj sınıflandırma (routine/faq/motivation/out_of_scope)
- ✅ FAQ öncelikli yanıtlama (maliyet tasarrufu)
- ✅ Q&A Cache sistemi (180 gün TTL)
- ✅ Rate limiting (dakika/gün bazlı)
- ✅ Kapsam dışı konuların nazik reddi

### Hızlı Eylemler
- ✅ Su takibi (+1 bardak, hedef hesaplama)
- ✅ Hareket seviyesi (az/orta/çok)
- ✅ Spor kaydı (konfeti efekti ile)
- ✅ Kilo girişi

### Spor Planları
- ✅ Başlangıç ve Orta seviye programlar
- ✅ Ev ve Salon alternatifleri
- ✅ Set/Tekrar/Dinlenme detayları
- ✅ 3-4 günlük haftalık programlar

### Dışarıda Yemek Rehberi
- ✅ Fast Food, Pizza, Kebapçı, Uzak Doğu, Kafe, Kahvaltıcı
- ✅ En iyi seçimler (max 6)
- ✅ Kaçınılması gerekenler
- ✅ İpuçları

### 10 Dil Desteği
🇹🇷 Türkçe | 🇬🇧 English | 🇩🇪 Deutsch | 🇫🇷 Français | 🇪🇸 Español
🇮🇹 Italiano | 🇧🇷 Português | 🇷🇺 Русский | 🇸🇦 العربية | 🇨🇳 中文

### Admin Panel
- ✅ Dashboard (kullanıcı, mesaj, AI istatistikleri)
- ✅ Kullanıcı yönetimi (liste, ban/unban)
- ✅ Sistem ayarları (OpenAI, limitler, sağlık hesaplamaları)

### Güvenlik
- ✅ Rate limiting (IP ve kullanıcı bazlı)
- ✅ Günlük mesaj limitleri
- ✅ XSS koruması
- ✅ Prepared statements (SQL injection koruması)
- ✅ Güvenlik olayları logu

## Kurulum (5 Dakika)

### 1. Dosyaları Yükle
Tüm dosyaları FTP ile hosting'e yükleyin.

### 2. Veritabanı Oluştur
```bash
# cPanel > MySQL Databases'den yeni veritabanı oluşturun
# phpMyAdmin'den database.sql dosyasını import edin
```

### 3. config.php Düzenle
```php
// Veritabanı
define('DB_HOST', 'localhost');
define('DB_NAME', 'kullanici_vitalia');
define('DB_USER', 'kullanici_dbuser');
define('DB_PASS', 'sifreniz');

// OpenAI
define('OPENAI_API_KEY', 'sk-...');
define('OPENAI_MODEL', 'gpt-4o-mini');
define('OPENAI_MAX_TOKENS', 500);
define('OPENAI_TEMPERATURE', 0.7);

// Google OAuth (opsiyonel)
define('GOOGLE_CLIENT_ID', '...');
define('GOOGLE_CLIENT_SECRET', '...');
```

### 4. Test Et
- Ana sayfa: `https://siteniz.com/`
- Spor planları: `https://siteniz.com/workout.php`
- Dışarıda yemek: `https://siteniz.com/food.php`
- Admin panel: `https://siteniz.com/admin.php`

## API Endpoint'leri

### Chat
| Action | Method | Açıklama |
|--------|--------|----------|
| chat_send | POST | Mesaj gönder (cache + rate limit dahil) |
| chat_history | GET | Chat geçmişi |

### Quick Actions
| Action | Method | Açıklama |
|--------|--------|----------|
| quick_action | POST | Su/adım/spor/kilo kaydet |
| log_today | GET | Bugünkü veriler |
| log_history | GET | Geçmiş veriler |

### Profile
| Action | Method | Açıklama |
|--------|--------|----------|
| profile_get | GET | Profil bilgileri |
| profile_save | POST | Profil güncelle |

### FAQ
| Action | Method | Açıklama |
|--------|--------|----------|
| faq_list | GET | FAQ listesi |

## Veritabanı Tabloları

- `users` - Kullanıcılar
- `profiles` - Profil bilgileri
- `user_sessions` - Oturum bilgileri
- `chat_messages` - Chat mesajları
- `daily_logs` - Günlük loglar
- `weight_logs` - Kilo logları
- `qa_faq` - Hazır FAQ cevapları
- `qa_cache` - Dinamik cache
- `openai_usage_daily` - AI kullanım takibi
- `admin_settings` - Sistem ayarları
- `security_events` - Güvenlik olayları
- `rate_limit_events` - Rate limit takibi
- `workout_plans` - Spor planları
- `workout_plan_days` - Plan günleri
- `workout_exercises` - Egzersizler
- `outside_food_categories` - Dışarıda yemek kategorileri
- `outside_food_items` - Yemek önerileri
- `motivation_templates` - Motivasyon şablonları

## Gereksinimler

- PHP 7.4+ (8.x önerilir)
- MySQL 5.7+ / MariaDB 10.3+
- PHP Extensions: curl, json, pdo_mysql, mbstring
- Apache mod_rewrite (opsiyonel)

## Notlar

1. **OpenAI API anahtarı zorunludur** - https://platform.openai.com/api-keys
2. **Admin güvenliği**: Production'da `admin.php`'deki admin kontrolünü aktifleştirin
3. **HTTPS önerilir**: SSL sertifikası kullanın
4. **Yedekleme**: Veritabanını düzenli yedekleyin
