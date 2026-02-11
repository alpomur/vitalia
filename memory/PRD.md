# Vitalia - Chat-First Kişisel Sağlıklı Yaşam Danışmanı

## Proje Özeti
Chat-first yaklaşımıyla kişisel sağlık danışmanlığı sunan web uygulaması. Beslenme, hareket, spor, su tüketimi ve kilo yönetimi konularında AI destekli rehberlik.

## Implementasyon Tarihi
- **PHP MVP Tamamlandı**: Aralık 2025
- **Son Güncelleme**: Aralık 2025

## Kullanıcı Personaları
1. **Sağlık Meraklısı**: Günlük su, hareket ve spor takibi yapmak isteyen
2. **Kilo Yönetimi**: Kilo verme/alma/koruma hedefi olan
3. **Motivasyon Arayan**: Sağlıklı yaşam için teşvik isteyen

## Temel Gereksinimler (Statik)
- Chat-first arayüz (ana ekran daima sohbet)
- AI ile sağlık konularında rehberlik
- Sağlık dışı konuların reddi
- Yargısız, motive edici dil
- Maksimum 6 öneri kuralı
- 10 dil desteği
- **Düz PHP + MySQL** (Paylaşımlı hosting uyumlu)

## Teknik Stack (PHP Versiyonu)
- **Backend**: PHP 7.4+ / PHP 8.x
- **Database**: MySQL 5.7+ / MariaDB 10.3+
- **Frontend**: Vanilla JavaScript, HTML5, CSS3
- **AI**: OpenAI GPT-4o-mini
- **Auth**: Session-based (Google OAuth opsiyonel)

## Implementasyon Durumu

### ✅ Tamamlanan Özellikler

#### Chat Sistemi
- [x] GPT-4o-mini ile AI sohbet
- [x] Mesaj sınıflandırma (routine/faq/motivation/out_of_scope)
- [x] Çok dilli sistem promptları
- [x] Kapsam dışı konularda nazik reddetme
- [x] Chat geçmişi
- [x] Q&A cache sistemi

#### Hızlı Eylemler
- [x] Su takibi (+1 bardak)
- [x] Hareket seviyesi (az/orta/çok)
- [x] Spor kayıt
- [x] Kilo girişi

#### Dashboard
- [x] Günlük su ilerlemesi
- [x] Aktivite seviyesi
- [x] Workout durumu

#### Çoklu Dil
- [x] 10 dil desteği (TR, EN, DE, FR, ES, IT, PT, RU, AR, ZH)
- [x] Türkçe karakter desteği (ş, ğ, ü, ö, ç, ı)
- [x] RTL desteği (Arapça)

#### UI/UX
- [x] Dark/Light mode
- [x] Mobil öncelikli responsive tasarım
- [x] Konfeti efekti (hedef tamamlandığında)
- [x] Enerjik turuncu/mavi renk teması

#### Ek Sayfalar
- [x] Spor Planları (workout.php) - Seviyeye göre egzersiz programları
- [x] Dışarıda Yemek Rehberi (food.php) - Restoran türlerine göre tavsiyeler

#### Admin Panel
- [x] Dashboard (kullanıcı, mesaj, AI istatistikleri)
- [x] Kullanıcı yönetimi (liste, ban/unban)
- [x] Sistem ayarları (OpenAI, limits, health calculations)

#### Maliyet Kontrolü
- [x] Rate limiting
- [x] Günlük mesaj limitleri (guest/user)
- [x] Q&A cache sistemi
- [x] Token kullanım takibi

### 📋 Backlog (P0/P1/P2)

#### P0 - Kritik
- [ ] Production için admin role kontrolü aktifleştirme
- [ ] Kendi OpenAI API anahtarı ekleme
- [ ] Veritabanı kurulumu ve test

#### P1 - Önemli
- [ ] Google OAuth entegrasyonu
- [ ] FAQ yönetimi (admin)
- [ ] Motivasyon şablonları yönetimi (admin)
- [ ] Gelişmiş spor planları (veritabanından)

#### P2 - İyileştirme
- [ ] Google Analytics entegrasyonu
- [ ] Kullanıcı başarı/uyum skorları
- [ ] Haftalık/aylık rapor
- [ ] Email bildirimleri

## Dosya Yapısı

```
/app/php_version/
├── index.php       # Ana sayfa (Chat arayüzü)
├── api.php         # Tüm API endpoint'leri
├── admin.php       # Admin paneli
├── workout.php     # Spor planları sayfası
├── food.php        # Dışarıda yemek rehberi
├── config.php      # Yapılandırma dosyası
├── helpers.php     # Yardımcı fonksiyonlar
├── languages.php   # Çoklu dil çevirileri (10 dil)
├── database.sql    # MySQL veritabanı şeması
├── .htaccess       # Apache yapılandırması
└── README.md       # Kurulum talimatları
```

## API Endpoints (PHP)

Tüm istekler: `api.php?action=<action>`

| Action | Method | Açıklama |
|--------|--------|----------|
| chat_send | POST | Mesaj gönder |
| chat_history | GET | Sohbet geçmişi |
| quick_action | POST | Hızlı eylem (water/steps/workout/weight) |
| log_today | GET | Bugünkü kayıtlar |
| log_history | GET | Geçmiş kayıtlar |
| profile_get | GET | Profil getir |
| profile_save | POST | Profil kaydet |
| faq_list | GET | FAQ listesi |

## Veritabanı Tabloları

- **users**: Kullanıcı bilgileri
- **profiles**: Kullanıcı profilleri
- **user_sessions**: Oturum bilgileri
- **chat_messages**: Sohbet mesajları
- **daily_logs**: Günlük kayıtlar
- **weight_logs**: Kilo kayıtları
- **qa_faq**: Hazır FAQ cevapları
- **qa_cache**: Dinamik cache
- **admin_settings**: Sistem ayarları
- **openai_usage_daily**: API kullanım takibi
- **security_events**: Güvenlik olayları
- **rate_limit_events**: Rate limit kayıtları
- **workout_plans**: Spor planları
- **workout_plan_days**: Plan günleri
- **workout_exercises**: Egzersizler
- **outside_food_categories**: Yemek kategorileri
- **outside_food_items**: Yemek önerileri
- **motivation_templates**: Motivasyon mesajları

## Kurulum Adımları

1. MySQL veritabanı oluştur
2. `database.sql` dosyasını import et
3. `config.php` içinde veritabanı bilgilerini güncelle
4. OpenAI API anahtarını ekle
5. Dosyaları sunucuya yükle
6. Admin paneli güvenliğini aktifleştir

## Sonraki Adımlar

1. Veritabanını kullanıcı sunucusunda oluştur ve import et
2. `config.php` içindeki ayarları güncelle
3. OpenAI API anahtarı ekle
4. Uygulamayı test et
