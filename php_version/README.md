# Vitalia - Chat-First Kişisel Sağlık Danışmanı

PHP + MySQL ile geliştirilmiş, paylaşımlı hosting için optimize edilmiş sağlık danışmanlığı uygulaması.

## Özellikler

- **AI Sohbet**: GPT-4o-mini ile sağlık danışmanlığı
- **10 Dil Desteği**: TR, EN, DE, FR, ES, IT, PT, RU, AR, ZH
- **Hızlı Eylemler**: Su takibi, hareket kaydı, spor ve kilo günlüğü
- **Dashboard**: Günlük özet ve ilerleme takibi
- **Spor Planları**: Seviyeye göre detaylı egzersiz programları
- **Dışarıda Yemek Rehberi**: Restoran türüne göre sağlıklı seçimler
- **Admin Panel**: Kullanıcı yönetimi ve sistem ayarları
- **Cache Sistemi**: API maliyetlerini azaltan akıllı önbellek
- **Rate Limiting**: Kötüye kullanımı engelleyen sınırlamalar

## Dosya Yapısı

```
php_version/
├── index.php       # Ana sayfa (Chat arayüzü)
├── api.php         # API endpoint'leri
├── admin.php       # Admin paneli
├── workout.php     # Spor planları sayfası
├── food.php        # Dışarıda yemek rehberi
├── config.php      # Yapılandırma dosyası
├── helpers.php     # Yardımcı fonksiyonlar
├── languages.php   # Çoklu dil çevirileri
├── database.sql    # MySQL veritabanı şeması
├── .htaccess       # Apache yapılandırması
└── README.md       # Bu dosya
```

## Kurulum

### 1. Veritabanı Oluşturma

1. cPanel veya phpMyAdmin'e giriş yapın
2. Yeni bir MySQL veritabanı oluşturun (örn: `vitalia_db`)
3. `database.sql` dosyasını import edin:
   - phpMyAdmin > Import > Dosya Seç > `database.sql` > Git

### 2. Yapılandırma

`config.php` dosyasını düzenleyin:

```php
// Veritabanı ayarları
define('DB_HOST', 'localhost');
define('DB_NAME', 'vitalia_db');      // Oluşturduğunuz veritabanı adı
define('DB_USER', 'your_db_user');    // MySQL kullanıcı adı
define('DB_PASS', 'your_db_password'); // MySQL şifresi

// OpenAI API anahtarı
define('OPENAI_API_KEY', 'sk-...');    // OpenAI API anahtarınız
```

### 3. Dosyaları Yükleme

Tüm dosyaları FTP veya cPanel File Manager ile sunucuya yükleyin:
- Ana klasöre (public_html) veya alt klasöre

### 4. Erişim Kontrolü

**Önemli**: Admin paneline erişimi production'da kısıtlayın.

`admin.php` dosyasında yorum satırlarını kaldırın:

```php
// Production'da bu satırları aktif edin:
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: index.php');
    exit;
}
```

## API Endpoints

Tüm API istekleri `api.php?action=<action>` formatındadır.

| Action | Method | Açıklama |
|--------|--------|----------|
| `chat_send` | POST | Mesaj gönder (AI yanıtı al) |
| `chat_history` | GET | Sohbet geçmişini getir |
| `quick_action` | POST | Hızlı eylem (su, adım, spor, kilo) |
| `log_today` | GET | Bugünkü kayıtlar |
| `log_history` | GET | Geçmiş kayıtlar |
| `profile_get` | GET | Kullanıcı profilini getir |
| `profile_save` | POST | Profil kaydet |
| `faq_list` | GET | FAQ listesi |

### Örnek İstekler

```javascript
// Mesaj gönder
fetch('api.php?action=chat_send', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ message: 'Günde kaç bardak su içmeliyim?', language: 'tr' })
});

// Su ekle
fetch('api.php?action=quick_action', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action_type: 'water', value: 250 })
});
```

## Desteklenen Diller

| Kod | Dil | RTL |
|-----|-----|-----|
| tr | Türkçe | ❌ |
| en | English | ❌ |
| de | Deutsch | ❌ |
| fr | Français | ❌ |
| es | Español | ❌ |
| it | Italiano | ❌ |
| pt | Português | ❌ |
| ru | Русский | ❌ |
| ar | العربية | ✅ |
| zh | 中文 | ❌ |

## Admin Panel

Admin paneline erişim: `yoursite.com/admin.php`

Özellikler:
- Kullanıcı istatistikleri
- Kullanıcı yönetimi (ban/unban)
- OpenAI ayarları
- Günlük limit ayarları
- Sağlık hesaplama parametreleri

## Güvenlik Notları

1. **config.php**: `.htaccess` ile koruma altındadır
2. **Admin Paneli**: Production'da session kontrolü ekleyin
3. **Rate Limiting**: Varsayılan 10 istek/dakika
4. **XSS Koruması**: Tüm çıktılar sanitize edilir
5. **SQL Injection**: PDO prepared statements kullanılır

## Sistem Gereksinimleri

- PHP 7.4+ (önerilen: PHP 8.0+)
- MySQL 5.7+ veya MariaDB 10.3+
- Apache mod_rewrite (isteğe bağlı)
- cURL PHP eklentisi
- mbstring PHP eklentisi

## Troubleshooting

### "Database connection failed"
- `config.php` içindeki veritabanı bilgilerini kontrol edin
- MySQL kullanıcısının veritabanına erişim yetkisi olduğundan emin olun

### "OpenAI API Error"
- API anahtarınızın geçerli olduğunu kontrol edin
- API kotanızı kontrol edin

### "Session Not Working"
- PHP session ayarlarını kontrol edin
- `session_start()` çağrıldığından emin olun

## Lisans

MIT License

## Destek

Sorularınız için GitHub Issues kullanın.
