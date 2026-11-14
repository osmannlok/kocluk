# 17-sozlesme-ozeti

Oturum: `[17-Güvenlik denetimi]`. Kaynak: yazılan GERÇEK dosyalar `scripts/security_audit.php`, `docs/pentest-checklist.md`, `docs/yarisa-testleri.md`. Uygulama kodu değiştirilmedi.

## (1) Dosya yolu, sınıf, public metot imzaları
- Sınıf/metot YOK. Çıktılar bir CLI script'i ve iki markdown dosyasıdır.
- `scripts/security_audit.php`: CLI statik güvenlik tarayıcısı; giriş `sa_main(): int`. Yardımcı fonksiyonlar: `sa_files()`, `sa_add()`, `sa_short()`, `sa_should_skip()`, `sa_sql_string()`, `sa_sql_risk()`, `sa_strip_strings()`, `sa_has_safe_wrapper()`, `sa_outer_parens()`, `sa_ternary_positions()`, `sa_static_value()`, `sa_static_ternary()`, `sa_output_safe()`, `sa_echo_expressions()`, `sa_is_safe_partial()`, `sa_is_image_upload()`, `sa_is_email_template()`, `sa_common_checks()`, `sa_view_checks()`, `sa_scan_file()`, `sa_sort()`, `sa_render()`. Görev: SQL birleştirme, superglobal, tehlikeli fonksiyon, upload, para, XSS/CSP ve güvenli çıktı ihlallerini `dosya:satır | önem | kural | mesaj` biçiminde raporlamak; `Kritik`/`Yüksek` varsa çıkış kodu 1.
- `docs/pentest-checklist.md`: manuel pentest kontrol listesi; 17 bölüm (SQLi, XSS, CSRF, IDOR, yetki yükseltme, brute force, oturum, fiyat/adet, kupon, stok yarışı, PayTR, upload, açık yönlendirme, başlık/CSP, hata sızıntısı, dizin listeleme, iş mantığı). Görev: denetçinin adım adım doğrulama yapmasını sağlamak.
- `docs/yarisa-testleri.md`: dört eşzamanlılık testi; son stok, global kupon limiti, kullanıcı başı kupon limiti, PayTR callback tekrar/yanlış tutar. Görev: bash+curl ile paralel istek üretip beklenen DB durumunu doğrulamak.
- `scripts/security_audit.php` kullanımı: `php scripts/security_audit.php [temel_dizin]`; varsayılan temel dizin proje köküdür (`dirname(__DIR__)`); geçersiz dizinde çıkış kodu 2.

## (2) Tablolar ve permission kodları
- Yeni tablo YOK; migration YOK. Tarayıcı yalnızca dosya okur.
- Test dokümanının doğrulama sorgularında kullandığı mevcut tablolar: `product_variants`, `stock_movements`, `orders`, `order_items`, `payments`, `payment_callbacks`, `coupons`, `coupon_usages`, `order_status_history`, `security_events`.
- Yeni permission kodu YOK; mevcut RBAC kodu değiştirilmedi. Test müşterileri mevcut `customer` rolüyle çalışır.
- Ödeme durumu: `payments.status ENUM('initiated','pending','success','failed','refunded')`; başarı için `success` kullanılır, `paid` geçersizdir.
- PayTR callback sonuçları: `processed`, `duplicate`, `amount_mismatch`, `bad_hash`; ayrıca hata durumunda `error` loglanabilir. Başarılı callback yanıtı düz `OK`'tir.

## (3) JSON uç noktaları
- Yeni JSON uç noktası YOK. Tarayıcı çıktısı düz metindir; JSON değildir.
- Testler mevcut sepet ve checkout uç noktalarını kullanır; bunlar JSON veya form gövdesi kabul edebilir. `/sepet/ekle` ve `/sepet/kupon` için JSON alternatifi vardır; doküman form-urlencoded kullanır.

## (4) ROUTE SATIRLARI
- `GET /giris` — giriş formu ve CSRF meta token'ı.
- `POST /giris` — test müşterisi oturumu açar; throttle `10,60`.
- `GET /sepet` — sepet sayfası ve CSRF meta token'ı.
- `POST /sepet/ekle` — `variant_id`, `quantity`; throttle `60,60`.
- `POST /sepet/kupon` — `code`; throttle `10,60`.
- `GET /odeme` — checkout formu; `nonce` burada üretilir.
- `POST /odeme/siparis-ver` — `_token`, `nonce`, `shipping_address_id`, `billing_type`, `payment_method`, `customer_note`, `accept_distance_sales=1`, `accept_kvkk=1`; throttle `10,60`.
- `GET /odeme/kart/{orderNumber}` — kart ödemesi yönlendirmesi; PayTR token üretimi.
- `GET /siparis/tamamlandi/{orderNumber}` — havale/EFT ve kapıda ödeme sonrası tamamlanma.
- `GET /eposta-dogrula` — e-posta doğrulama uyarısı.
- `POST /odeme/paytr/bildirim` — PayTR callback; CSRF istisnası, hash doğrulamalı, idempotent, düz `OK` yanıtlı.
- `POST /hesabim/adresler` — test adresi oluşturmak için kullanılabilir; doküman adresin hazır olduğunu varsayar.
- Yönlendirmeler: `/sepet` (stok hatası), `/odeme` (nonce/domain hatası), `/eposta-dogrula` (doğrulanmamış e-posta), `/odeme/kart/{orderNumber}` veya `/siparis/tamamlandi/{orderNumber}` (başarılı sipariş).

## (5) .env / settings anahtarları
- Uygulama için yeni `.env` anahtarı YOK; mevcut anahtarlar değiştirilmedi.
- Test komutları şu ortam değişkenlerini okur: `BASE_URL`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `VARIANT_ID`, `ADDRESS_A`, `ADDRESS_B`, `COUPON_GLOBAL`, `COUPON_USER`, `EMAIL_A`, `PASS_A`, `EMAIL_B`, `PASS_B`, `EMAIL_SAME`, `PASS_SAME`.
- PayTR callback testi ayrıca `PAYTR_MERCHANT_KEY` ve `PAYTR_MERCHANT_SALT` gerektirir; bu değerler `.env`'den gelir ve dokümana yazılmaz.
- Tarayıcı hiçbir `.env` veya `settings` anahtarını okumaz.
- Doküman mevcut `settings` anahtarlarına atıfta bulunur: `card.enabled`, `bank_transfer.enabled`, `cod.enabled`, `cod.fee`, `order.card_expiry_minutes`, `order.bank_expiry_hours`, `order.min_amount`; yeni anahtar eklenmedi.

## Uyarılar (sonraki oturumlar için)
- `scripts/security_audit.php` temiz çıkmıyor: son çalıştırmada 147 satır çıktı, 145 bulgu (36 Kritik, 15 Yüksek, 2 Orta, 92 Düşük) ve çıkış kodu 1. Bulgular triyaj edilmeden "denetim geçti" denemez.
- SQL bulgularının bir kısmı `app/Models/*` içindeki dinamik tablo/kolon birleştirmeleridir; 17.1 kuralı gereği `Kritik` raporlanır, ancak manuel doğrulama gerekir.
- `file_put_contents` yasağı `app/Services/MailService.php` ve `app/Core/Logger.php` dosyalarını da işaretler; bu, 17.1'in birebir kural uygulamasıdır ve upload dışı kullanım olduğu checklist'te açıklanmalıdır.
- `resources/views/partials/pagination.php` artık sorguyu `Request::capture()->query()` üzerinden okur; doğrudan `$_GET` INPUT bulgusu kapandı ve mevcut sorgu dizesi korunur.
- Kalan Yüksek XSS bulguları int cast edilmiş kimlik/sayılar veya `e()`/HTMLPurifier ile korunan çıktılardır; SQL bulguları ise beyaz listeli dinamik identifier'lardır. Bunlar doğrulanmış sömürülebilir açık değildir, ancak manuel triyaj gerekir.
- `payments.status` için `paid` değil `success` kullanılmalıdır; `orders.payment_status` ise `paid` değerini alır.
- Yarış testleri yıkıcı SQL içerir; yalnızca test ortamında çalıştırılmalıdır. Üretimde çalıştırılmamalıdır.
- PayTR yanlış tutar testi, aynı ödeme `success` olduktan sonra çalıştırılırsa idempotency nedeniyle `duplicate` döner; ayrı bir `pending` ödeme kullanılmalıdır.
- Aynı kullanıcı için tek sepet bulunduğundan kullanıcı başı kupon yarışında ikinci checkout boş sepet/nonce nedeniyle reddedilebilir; kritik doğrulama kupon kullanımının 1'de kalmasıdır.
- `docs/yarisa-testleri.md` içindeki komutlar `mysql` CLI ve `php` CLI gerektirir; test ortamında bu araçlar yoksa doküman çalıştırılamaz.
