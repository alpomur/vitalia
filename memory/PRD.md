# Vitalia - Chat-First Kişisel Sağlıklı Yaşam Danışmanı

## Proje Özeti
Chat-first yaklaşımıyla kişisel sağlık danışmanlığı sunan web uygulaması. Beslenme, hareket, spor, su tüketimi ve kilo yönetimi konularında AI destekli rehberlik.

## Implementasyon Tarihi
- **MVP Tamamlandı**: 11 Şubat 2026

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

## Implementasyon Durumu

### ✅ Tamamlanan Özellikler

#### Chat Sistemi
- [x] GPT-4o-mini ile AI sohbet
- [x] Mesaj sınıflandırma (routine/faq/motivation/out_of_scope)
- [x] Çok dilli sistem promptları
- [x] Kapsam dışı konularda nazik reddetme
- [x] Chat geçmişi

#### Hızlı Eylemler
- [x] Su takibi (+1 bardak)
- [x] Hareket seviyesi (az/orta/çok)
- [x] Spor kayıt
- [x] Kilo girişi

#### Dashboard
- [x] Günlük su ilerlemesi
- [x] Aktivite seviyesi
- [x] Workout durumu
- [x] Kilo trendi
- [x] Haftalık ilerleme

#### Çoklu Dil
- [x] 10 dil desteği (TR, EN, DE, FR, ES, IT, PT, RU, AR, ZH)
- [x] Türkçe karakter desteği (ş, ğ, ü, ö, ç, ı)
- [x] RTL desteği (Arapça)

#### UI/UX
- [x] Dark/Light mode
- [x] Mobil öncelikli responsive tasarım
- [x] Konfeti efekti (hedef tamamlandığında)
- [x] Enerjik turuncu/mavi renk teması
- [x] Glass-morphism efektleri

#### Auth
- [x] Google OAuth (Emergent managed)
- [x] Anonim kullanıcı desteği
- [x] Guest data merge (login sonrası)

#### Admin Panel
- [x] Dashboard (kullanıcı, mesaj, AI istatistikleri)
- [x] Kullanıcı yönetimi (liste, ban/unban)
- [x] Sistem ayarları (OpenAI, limits, health calculations)
- [x] Cache performans metrikleri

#### Maliyet Kontrolü
- [x] Rate limiting
- [x] Günlük mesaj limitleri (guest/user)
- [x] Q&A cache sistemi
- [x] Token kullanım takibi

### 📋 Backlog (P0/P1/P2)

#### P0 - Kritik
- [ ] Production için admin role kontrolü aktifleştirme
- [ ] Kendi OpenAI API anahtarı ekleme

#### P1 - Önemli
- [ ] Spor plan şablonları (set/tekrar detaylı)
- [ ] Dışarıda yemek rehberi (restoran türleri)
- [ ] FAQ hazır cevap yönetimi (admin)
- [ ] Motivasyon şablonları yönetimi
- [ ] Kilo check-in hatırlatıcısı

#### P2 - İyileştirme
- [ ] Google Analytics entegrasyonu
- [ ] Kullanıcı başarı/uyum skorları
- [ ] Apple OAuth
- [ ] Detaylı kullanıcı profil sayfası
- [ ] Haftalık/aylık rapor

## Teknik Stack

### Mevcut (Development)
- **Backend**: FastAPI + Python
- **Frontend**: React + Tailwind CSS
- **Database**: MongoDB
- **AI**: OpenAI GPT-4o-mini (Emergent LLM Key)
- **Auth**: Emergent Google OAuth

### PHP Versiyonu (Paylaşımlı Hosting için)
- `/app/php_version/` klasöründe hazır
- `database.sql` - MySQL şeması
- `config.php` - Yapılandırma
- `api/chat.php` - Chat endpoint

## Sonraki Adımlar

1. **Kendi API Anahtarı**: `config.php` veya `.env` dosyasında OpenAI anahtarını değiştir
2. **Admin Güvenliği**: Production'da `require_admin` fonksiyonundaki role kontrolünü aktifleştir
3. **PHP Deployment**: Paylaşımlı hosting için PHP versiyonunu kullan
4. **Spor Planları**: Detaylı egzersiz şablonları ekle

## API Endpoints

| Endpoint | Method | Açıklama |
|----------|--------|----------|
| /api/chat/send | POST | Mesaj gönder |
| /api/chat/welcome | GET | Hoşgeldin mesajı |
| /api/chat/history | GET | Chat geçmişi |
| /api/log/quick-action | POST | Hızlı eylem |
| /api/log/today | GET | Bugünkü log |
| /api/log/history | GET | Geçmiş loglar |
| /api/auth/session | POST | OAuth session |
| /api/auth/me | GET | Mevcut kullanıcı |
| /api/auth/logout | POST | Çıkış |
| /api/admin/dashboard | GET | Admin istatistikleri |
| /api/admin/users | GET | Kullanıcı listesi |
| /api/admin/settings | GET/POST | Ayarlar |
