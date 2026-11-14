# Eğitim Oyun Portalı

Çocuklara yönelik eğitici oyunlar ve öğrenme içerikleri sunan, PHP + MySQL tabanlı bir web portalı.

## Proje Amacı

- Çocukların güvenli ve öğretici oyunlarla öğrenmesini sağlamak.
- Oyun, içerik ve kullanıcı sistemini tek platformda toplamak.
- Hızlı, SEO uyumlu ve ölçeklenebilir bir yapı kurmak.

## Özellikler

- Ana sayfa, kategori sayfaları ve oyun detayları.
- Oyun katalogu.
- Öğrenme bölümü.
- Blog sistemi.
- Kayıt ve giriş sistemi.
- Skor, favori ve rozet altyapısı.
- Admin panel için uygun mimari.
- Reklam ve premium üyelik için genişletilebilir yapı.

## Teknoloji Yığını

- PHP 8+
- MySQL / MariaDB
- PDO
- HTML5, CSS3, JavaScript
- Redis cache (opsiyonel)

## Klasör Yapısı

```txt
/oyun-portali
├── /app
│   ├── /Controllers
│   ├── /Models
│   ├── /Helpers
│   └── /Services
├── /config
│   ├── database.php
│   ├── app.php
│   └── routes.php
├── /public
│   ├── index.php
│   ├── .htaccess
│   ├── /assets
│   │   ├── /css
│   │   ├── /js
│   │   ├── /images
│   │   └── /fonts
│   └── /uploads
├── /views
│   ├── /layouts
│   ├── /partials
│   ├── /home
│   ├── /games
│   ├── /blog
│   ├── /auth
│   └── /errors
├── /storage
│   ├── /logs
│   ├── /cache
│   └── /exports
├── /database
│   ├── schema.sql
│   └── seed.sql
├── /tests
├── /docs
├── .env
├── .gitignore
├── composer.json
└── README.md
```

## Kurulum

1. Depoyu bilgisayarına indir.
2. `.env` dosyasını `.env.example` üzerinden oluştur ve veritabanı bilgilerini gir.
3. `database/schema.sql` dosyasını MySQL'e içe aktar.
4. `public/` klasörünü web kökü olarak ayarla.
5. Gerekli izinleri `storage/` ve `public/uploads/` için ver.
6. `composer install` çalıştır.
7. Siteyi aç ve ilk kurulum ekranını kontrol et.

## Ortam Değişkenleri

Örnek `.env`:

```env
APP_NAME="Egitim Oyun Portali"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost
APP_KEY=

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=oyun_portali
DB_USERNAME=root
DB_PASSWORD=
DB_CHARSET=utf8mb4

SESSION_NAME=oyun_portali_session
SESSION_LIFETIME=120
SESSION_SECURE=false

CACHE_DRIVER=file
CACHE_TTL=3600
```

## Veritabanı Modülleri

- users
- roles
- categories
- games
- game_categories
- blog_posts
- comments
- favorites
- scores
- badges
- user_badges
- ads_slots
- settings
- page_views
- site_pages

## İlk Geliştirme Sırası

1. Layout ve ana sayfa.
2. Oyun listesi ve detay sayfası.
3. Kayıt ve giriş sistemi.
4. Blog altyapısı.
5. Admin panel iskeleti.
6. Cache ve performans iyileştirmeleri.
7. SEO ve sitemap üretimi.

## Performans Notları

- Görselleri WebP olarak kullan.
- CSS ve JS dosyalarını birleştir ve minify et.
- Gereksiz sorguları azalt.
- Sık okunan verileri cache'le.
- Oyun dosyalarını sayfa bazlı yükle.

## Güvenlik Notları

- Prepared statement kullan.
- Şifreleri hash ile sakla.
- CSRF koruması ekle.
- Dosya yüklemelerini sınırlandır.
- Admin erişimini koru.

## Lisans

Bu proje sahibi tarafından özelleştirilmek üzere hazırlanmıştır.