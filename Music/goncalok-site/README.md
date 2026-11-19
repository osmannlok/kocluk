# Gonca LÖK — Öğrenci ve Kariyer Koçluğu Web Sitesi + Platform

Native PHP 8.2+ (framework bağımsız) + MySQL 8 + PDO + Bootstrap 5 ile yazılmış, çok organizasyonlu (multi-tenant) koçluk yönetim sistemi.

## İçindekiler

- [Gereksinimler](#gereksinimler)
- [Hızlı Kurulum](#hızlı-kurulum)
- [Çok Organizasyonlu Yapı](#çok-organizasyonlu-yapı)
- [Yönetici Hesabı Yönetimi](#yönetici-hesabı-yönetimi)
- [Yedekleme ve Geri Yükleme](#yedekleme-ve-geri-yükleme)
- [Cron / Zamanlanmış Görevler](#cron--zamanlanmış-görevler)
- [Ortam Değişkenleri](#ortam-değişkenleri)
- [Üretim Ortamına Geçiş Kontrol Listesi](#üretim-ortamına-geçiş-kontrol-listesi)
- [Güvenlik Notları](#güvenlik-notları)
- [KVKK](#kvkk)
- [Test](#test)
- [Dizin Yapısı](#dizin-yapısı)
- [Blog Görsel Standardı](#blog-görsel-standardı)

## Gereksinimler

- PHP 8.2+ (`pdo_mysql`, `mbstring`, `iconv`, `dom`, `fileinfo`, `gd`)
- MySQL 8+ / MariaDB 10.6+
- Apache 2.4+ (önerilir; `.htaccess` korumaları Apache'ye özgüdür) veya Nginx (kurgu size ait)
- Geliştirme: `php -S localhost:8000 router.php`

## Hızlı Kurulum

```bash
# 0) Yerel geliştirme sunucusunu router ile çalıştırın
#    (yerleşik sunucu .htaccess okumaz; .env/database/storage/bin 404 döner)
php -S localhost:8000 router.php

# 1) Depoyu yerleştirin
cd /var/www/goncalok-site

# 2) Ortam dosyasını oluşturun
cp .env.example .env
$EDITOR .env          # DB bilgileri, APP_URL, APP_ENV=production

# 3) Veritabanını oluşturun
mysql -u root -p -e "CREATE DATABASE goncalok CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
                     CREATE USER 'goncalok_app'@'localhost' IDENTIFIED BY 'GUCLU-SIFRE';
                     GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX ON goncalok.* TO 'goncalok_app'@'localhost';
                     FLUSH PRIVILEGES;"

# 4) Şemayı kurun (tüm tabloları idempotent oluşturur)
mysql -u root -p goncalok < database/schema.sql

# 5) Organizasyon ve ilk yönetici kullanıcısını oluşturun
php migrate.php --owner-email=sizin@email.com --owner-name="Adınız"
# rastgele şifre atanır ve ekrana yazılır; sonradan: php bin/reset-admin-password.php --email=sizin@email.com

# 6) Giriş yapın
open http://localhost:8000/admin.php
```

Mevcut (eski) bir kurulumu yükseltmek için: önce `mysqldump` ile yedek alın, sonra `php migrate.php` çalıştırın — tablolara `organization_id` ekler, verileri organizasyon #1'e (`gonca-lok`) bağlar, `users`/`organizations`/`rate_limits`/`schema_migrations` tablolarını oluşturur ve owner kullanıcısını üretir. Script idempotenttir.

## Çok Organizasyonlu Yapı

- Tablolar `organization_id` ile ayrışır; her sorgu oturumdaki organizasyona filtrelenir.
- Public sayfalar (blog, randevu görüntüleme/alma) her zaman `.env`'deki `SITE_ORG_SLUG` organizasyonuna hizmet eder (tek site = tek organizasyon).
- Panel kullanıcıları (`users`) kendi organizasyonlarının verisini görür. Roller: `owner`, `coach`, `admin`.
- Yeni organizasyon eklemek için:
  ```sql
  INSERT INTO organizations (name, slug) VALUES ('Kurum Adı', 'kurum-adi');
  ```
  ve ardından `bin/` tarzı bir CLI veya SQL ile `users` satırı açın; ilk kullanıcıyı `owner` yapın. (İleride küçük bir `bin/create-org.php` eklenebilir.)
- Portal (öğrenci/veli) token tabanlıdır; token çözümlendiğinde portal oturumu o öğrencinin organizasyonuna bağlanır.
- Her sayfada `organization_id` filtresi zorunludur; yeni sorgu eklerken mutlaka `organization_id = :org` ekleyin.

## Yönetici Hesabı Yönetimi

```bash
# Şifre sıfırlama (rastgele şifre üretir ve ekrana yazar)
php bin/reset-admin-password.php --email=sizin@email.com
# veya belirli şifre ile
php bin/reset-admin-password.php --email=sizin@email.com --password=enAz12Karakter
```

Panel içinden **Şifre Değiştir** (Sol menü → 🔑) ile kendi şifrenizi değiştirebilirsiniz (mevcut şifre doğrulaması + minimum 12 karakter).

## Yedekleme ve Geri Yükleme

```bash
# Günlük yedek (cron)
mysqldump -h localhost -u root -p --single-transaction --routines --triggers goncalok \
  | gzip > /backups/goncalok-$(date +%F).sql.gz

# Geri yükleme (DİKKAT: mevcut veriyi ezer)
gunzip -c /backups/goncalok-2026-11-18.sql.gz | mysql -h localhost -u root -p goncalok
```

- Yedekleri şifreli saklayın (örneğin `age`/`gpg`) ve erişimini kısıtlayın.
- Yedekte kişisel veri vardır (öğrenci bilgileri, seans notları); KVKK saklama sürelerine uyun.
- Geri yükleme denemelerini boş bir test veritabanında yapın:
  ```bash
  mysql -e "CREATE DATABASE goncalok_restore_test" && gunzip -c yedek.sql.gz | mysql goncalok_restore_test
  ```

## Cron / Zamanlanmış Görevler

```cron
# Her gün 03:00 — günlük yedek
0 3 * * *  mysqldump ... | gzip > /backups/goncalok-$(date +\%F).sql.gz
# (opsiyonel) haftada bir — süresi dolmuş portal token'larını pasifleştir
0 4 * * 0  mysql goncalok -e "UPDATE portal_tokens SET active = 0 WHERE expires_at < NOW()"
```

## Ortam Değişkenleri

| Değişken | Açıklama | Varsayılan |
|---|---|---|
| `APP_ENV` | `development` = hata detayı gösterilir; `production` = genel 500 sayfası | `production` |
| `APP_TIMEZONE` | PHP saat dilimi (`Europe/Istanbul` önerilir) | `Europe/Istanbul` |
| `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASS` | Veritabanı bağlantısı | `localhost` / `goncalok` / `goncalok` / _boş_ |
| `DB_TIMEZONE` | MySQL oturum saat dilimi | `+03:00` |
| `SITE_ORG_SLUG` | Public sitenin organizasyonu | `gonca-lok` |
| `APP_URL` | Kanonik URL (örn. `https://www.goncalokofficial.com`); boşsa istekten tespit | boş |
| `MAIL_ENABLED` | E-posta gönderimi (`true`/`false`) | `false` |
| `MAIL_FROM` / `MAIL_FROM_NAME` | Gönderen | `goncalokofficial@gmail.com` / `Gonca LÖK` |
| `PORTAL_TOKEN_DAYS` | Portal bağlantısı geçerlilik süresi (gün) | `30` |
| `SESSION_IDLE_SECONDS_ADMIN` | Admin oturum idle timeout (saniye) | `1800` (30 dk) |
| `SESSION_IDLE_SECONDS_PORTAL` | Portal oturum idle timeout (saniye) | `604800` (7 gün) |

## Üretim Ortamına Geçiş Kontrol Listesi

- [ ] `.env` üretimde `APP_ENV=production`, güçlü DB şifresi; `config.php`'de artık sabit şifre yok
- [ ] DB kullanıcısı root **değil**; yalnızca uygulama veritabanında SELECT/INSERT/UPDATE/DELETE (+ geliştirme için ALTER/INDEX)
- [ ] Docroot sadece public dosyalar için ayrıştırılmış; `.env`, `database/`, `storage/`, `bin/` dışarıya kapalı (`.htaccess` RedirectMatch veya vhost kuralı)
- [ ] `assets/images/.htaccess` yerinde (yüklenen görseller çalıştırılamaz)
- [ ] HTTPS zorunlu; HSTS (otomatik, HTTPS'teyken) ve güvenlik başlıkları açık (`config.php` içinde)
- [ ] `php.ini`: `expose_php = Off`, `display_errors = Off`, `log_errors = On`
- [ ] Günlük yedek + geri yükleme denemesi; yedekler şifreli
- [ ] `storage/logs/app.log` yazılabilir ve loglar rotasyonlu (örn. logrotate)
- [ ] Admin şifresi değiştirildi (rastgele, ≥12 karakter); paylaşım yok
- [ ] `bin/reset-admin-password.php` ile düzenli şifre rotasyonu
- [ ] Rate limiting aktif (admin login: 5 deneme/15 dk; public randevu: 8/saat)
- [ ] Apache'de `X-Powered-By` kapalıysa `.htaccess`'teki opsiyonel mod_headers bloğu açıldı
- [ ] Yedekten geri yükleme test edildi
- [ ] `storage/logs/app.log` içine parola/token yazılmıyor (gövdede otomatik maskeleme var)

## Güvenlik Notları

- Tüm sorgular PDO prepared statement; `PDO::ATTR_EMULATE_PREPARES=false`.
- CSRF tokenı tüm durum değiştiren POST isteklerinde zorunludur; admin silme/kaydetme işlemleri POST+token'dır.
- Admin girişi: e-posta+şifre, `password_verify`, IP başına 15 dakikada 5 deneme, girişte `session_regenerate_id(true)`, idle timeout.
- Portal: öğrenci/veli kendi verisini görür; veli `career`/`onay`/`mood` ekranlarına erişemez; `private_note` hiçbir zaman portalda/paylaşılan PDF'te gösterilmez.
- Blog içeriği `sanitize.php` (izin listesi) ile temizlenir; hem kaydederken hem basarken.
- Yüklenen görseller: MIME + boyut + `getimagesize` + rastgele dosya adı; uzantı beyaz listesi.
- Denetim kaydı (`audit_logs`): oluşturan kullanıcı adı/organizasyonu ile yazılır.
- Bildirim loglarında portal token'ları maskelenerek saklanır.

## KVKK

- `kvkk.php` (Aydınlatma Metni) ve `gizlilik.php` (Gizlilik Politikası) sayfaları canlıda mevcut; iletişim formu bu sayfalara bağlar.
- 18 yaş altı öğrenciler için veli rızası: koçluk kaydında `guardian_*` alanları + portal onay kaydı (`consents`, versiyonlu) tutulur.
- Silme/anonimleştirme talebi için: `students` kaydını silmek ilgili `sessions/goals/actions/exams/...` satırlarını manüel temizlemeyi gerektirir; talep üzerine yazılır prosedür uygulayın (ör. `UPDATE students SET first_name='KALDIRILDI', ...` + bağlı satırların not alanlarını `NULL` yapma). Yedekler rotasyonla imha edilmeli.

## Test

Hızlı sağlık kontrolü:

```bash
for p in / /blog.php /iletisim.php /kvkk.php /admin.php; do curl -si -o /dev/null -w "%{http_code}\n" "http://localhost:8000$p"; done
```

Beklenenler: public sayfalar `200`, `/admin.php` anonimde `302 → login`.

- Giriş → 302 (session regenerate , portal token → 302)
- CSRF'siz POST → 403; logout CSRF'siz → 403
- Rate limit: 5 hatalı giriş sonrası "Çok fazla deneme" mesajı
- Public randevu JSON'unda `detail` alanı yok
- Organizasyon izolasyonu: `curl` ile başka org'un id'lerine erişilemez

## Blog Görsel Standardı

Blog görselleri **1600×500 (3.2:1)** olmalıdır; kart ve yazı hero'larında bu oran sabittir.

- **Yükleme akışı otomatik işler:** admin panelinden yüklenen JPG/PNG/WebP görselleri `admin.php` tarafından merkezden kırpılıp 1600×500'e yeniden kodlanır (WebP, kalite 82). GIF desteklenmez.
- **Elle hazırlanan görseller** de 1600×500 olmalıdır; kartlar `object-fit: cover` ile aynı oranı korur.
- Mevcut blog görselleri bu standarta normalize edilmiştir.
- Banner üretimi için marka renklerini kullanın: `#C76F3D` (terracotta), `#3F493C` (adaçayı), `#F5EEE6` (kağıt krem), `#454545` (charcoal). Metin içermeyen görseller tercih edin.

## Dizin Yapısı

```
config.php            # .env yükleme, bootstrap, oturum, güvenlik başlıkları, rate limiter, auth
db.php                # blog + randevu veri katmanı (org-scoped)
platform-db.php       # koçluk platformu veri katmanı (org-scoped)
sanitize.php          # blog HTML beyaz liste temizleyici (stored-XSS koruması)
admin.php             # yönetim paneli (users ile giriş, RBAC, sayfalama)
portal.php            # öğrenci/veli portalı (token tabanlı)
migrate.php           # CLI migration (organizasyon bootstrap'ı; idempotent)
bin/reset-admin-password.php
database/schema.sql   # taze kurulum şeması (mysqldump ile üretilir)
router.php            # php -S yönlendiricisi (hassas yolları 404 yapar)
.htaccess             # docroot korumaları (Apache)
.env / .env.example   # ortam yapılandırması (repoya girmez)
storage/logs/app.log  # uygulama logları (otomatik oluşturulur)
database/, bin/       # web'den erişilemez (router.php & .htaccess ile)
```
