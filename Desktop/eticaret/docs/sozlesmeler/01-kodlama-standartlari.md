# Kodlama Standartları

## Çalışma kuralı
- Her prompt TEK dosya ister. TAM kod ver; "...", "geri kalanı aynı", placeholder YASAK.
- Çıktı: ilk satır yol yorumu + kod + "TEST" (en çok 5 satır, Linux komutları). Açıklama yazma.
- Ekli sözleşmelerdeki sınıf/metot/tablo/sütun/URL adlarını BİREBİR kullan, uydurma. Gerekli bir imza sözleşmede yoksa kod yazmadan önce tek cümleyle sor.
- Düzeltme istenirse yalnızca değişen fonksiyon/blok + dosya yolu ver.

## PHP
- PHP 8.2+, declare(strict_types=1), namespace App\..., PSR-4, PSR-12, tüm parametre/dönüş tipleri.
- Veritabanı: yalnızca PDO prepared statements (ATTR_EMULATE_PREPARES=false, ERRMODE_EXCEPTION). Dinamik kolon/sıralama adı yalnızca beyaz liste ile.
- Çoklu yazma transaction içinde; stok güncelleme atomik UPDATE veya SELECT ... FOR UPDATE.
- Kod/sınıf/tablo/sütun adları İngilizce; yorumlar ve arayüz metinleri Türkçe.

## Para
- DB DECIMAL(12,2) KDV dahil. Kodda App\Helpers\Money ile TAMSAYI KURUŞ hesabı; float ile para hesabı YASAK. Dışarıya "1234.50" string.

## Katmanlar
- Controller: istek al, doğrula, servisi çağır, yanıt dön. SQL ve iş mantığı YOK.
- Service: iş kuralı + transaction. Model: tek tablo erişimi, SQL yalnızca burada. View: sunum, çıktı e() ile.
- Middleware: auth, permission, csrf, throttle, güvenlik başlıkları.

## Yanıt formatı
- AJAX JSON: {"ok":true,"data":{}} veya {"ok":false,"error":{"code":"","message":"","errors":{}}}
- Durum: 422 doğrulama, 401 oturum yok, 403 yetki, 404, 419 CSRF, 429 hız sınırı, 500 (ayrıntı sızdırma, loga yaz).

## Frontend ve CSP
- Satır içi <script>, <style>, style="" ve on*= öznitelikleri YASAK. Dinamik stil: CSS sınıfı veya data-* + JS'te el.style. JSON verisi data-* özniteliğinde.
- Dış CDN yok; Bootstrap, ikonlar, Chart.js public/assets/vendor altında.
- Kullanıcı verisini innerHTML'e yazma; textContent/DOM API kullan.
- Bootstrap 5, mobile-first, erişilebilir (label, aria, odak). JS: Vanilla, fetch + X-CSRF-Token.
- View'larda url('/yol'), çıktı e().