# Ortam, Klasör Yapısı ve URL Haritası

ORTAM: Linux, terminal. Apache 2.4 + mod_php, MariaDB, PHP 8.2+. Kök /home/osman/Desktop/eticaret, DocumentRoot kök/public, host eticaret.local, DB yoresel_market. Test: sudo mysql yoresel_market -e "..."; zamanlanmış görev cron. Windows/XAMPP/phpMyAdmin YOK.

eticaret/
  app/Core/            Config, Database, Router, Request, Response, Session, DbSessionHandler, Csrf, Validator, View, Auth, RateLimiter, Logger, ErrorHandler, HttpException, ValidationException
  app/Middleware/      MiddlewareInterface, SecurityHeaders, Csrf, Auth, Guest, Permission, Throttle
  app/Models/          BaseModel + tablo başına model
  app/Services/        iş mantığı; Services/Payment/ ödeme
  app/Controllers/     BaseController; Front/ ; Admin/
  app/Helpers/         functions.php, Money.php
  config/              app, database, middleware, security, paytr, carriers
  database/            01..06 .sql
  public/              index.php, .htaccess, assets/{css,js,img,vendor}, uploads/
  resources/views/     layouts, partials, front, account, admin, emails, errors
  routes/              web.php, admin.php
  scripts/             yalnızca CLI betikleri
  storage/             logs, cache, mail, backups
  docs/  .env  .env.example  composer.json

Ad alanı App\ -> app/. View adı noktalı: view('front.cart.index') -> resources/views/front/cart/index.php.

## URL HARİTASI (tek kaynak)
Vitrin: / | /urunler | /urun/{slug} | /kategori/{slug} | /yore/{slug} | /ara | /ara/oneri | /sayfa/{slug} | /iletisim | /sitemap.xml
Üyelik: /giris | /kayit | /cikis | /eposta-dogrula | /eposta-dogrula/{token} | /eposta-dogrula/tekrar | /sifremi-unuttum | /sifre-sifirla | /sifre-sifirla/{token}
Sepet: /sepet | /sepet/ozet | /sepet/ekle | /sepet/guncelle | /sepet/sil | /sepet/kupon | /sepet/kupon/kaldir
Sipariş/ödeme: /odeme | /odeme/ozet | /odeme/siparis-ver | /siparis/tamamlandi/{orderNumber} | /odeme/kart/{orderNumber} | /odeme/paytr/bildirim | /odeme/basarili/{orderNumber} | /odeme/basarisiz/{orderNumber}
Hesap: /hesabim | /hesabim/siparisler | /hesabim/siparisler/{orderNumber} | /hesabim/siparisler/{orderNumber}/iptal | /hesabim/adresler | /hesabim/adresler/liste | /hesabim/adresler/{id}/guncelle | /hesabim/adresler/{id}/sil | /hesabim/adresler/{id}/varsayilan | /hesabim/profil | /hesabim/sifre
Admin (hepsi /yonetim altında): / | /urunler | /kategoriler | /yoreler | /stok | /siparisler | /musteriler | /kuponlar | /bannerlar | /duyurular | /menuler | /sayfalar | /ayarlar | /mesajlar | /kullanicilar | /roller | /denetim | /guvenlik-olaylari