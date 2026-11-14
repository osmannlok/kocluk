# 05-sozlesme-ozeti

Oturum: [1-VERİTABANI]. Kaynak: yazılan GERÇEK dosyalar database/01..06 SQL ve 04-sema-ozeti.md. Bu oturumda PHP kodu yazılmadı.

## (1) Dosya yolu, sınıf, public metot imzaları
- Sınıf/metot YOK. Bu oturumun çıktısı yalnızca SQL ve markdown'dır.
- database/01_auth_rbac.sql: CREATE DATABASE yoresel_market; roles, permissions, role_permissions, users, addresses, login_attempts, password_resets, email_verifications; roller ve yetkiler seed edilir.
- database/02_catalog_stock.sql: categories, regions, products, product_variants, product_images, stock_movements; VIEW v_low_stock.
- database/03_cart_orders_payments.sql: carts, cart_items, coupons, shipping_rates, orders, order_items, order_status_history, payments, payment_callbacks, shipments, coupon_usages.
- database/04_cms_marketing.sql: banners, menus, menu_items, pages, settings, announcements, contact_messages.
- database/05_security_logs.sql: audit_logs, security_events, rate_limits, sessions; yorumlu DB kullanıcısı bloğu (#DBUSER ... #DBUSER-BITIS).
- database/06_seed.sql: settings, shipping_rates, regions, categories, menus/menu_items, pages, announcements, banners, DEMO ürün/varyant/görsel/stok hareketi.
- Not: 04-sema-ozeti.md tablo/kolon/FK/ENUM ayrıntısını taşır; burada tekrarlanmaz.

## (2) Tablolar ve permission kodları
Tablolar (36): roles, permissions, role_permissions, users, addresses, login_attempts, password_resets, email_verifications, categories, regions, products, product_variants, product_images, stock_movements, carts, cart_items, coupons, shipping_rates, orders, order_items, order_status_history, payments, payment_callbacks, shipments, coupon_usages, banners, menus, menu_items, pages, settings, announcements, contact_messages, audit_logs, security_events, rate_limits, sessions. VIEW: v_low_stock (ayrıca, tablo sayısına dahil değil).
EKLEMELİ (yalnızca INSERT): audit_logs, security_events, payment_callbacks, stock_movements, order_status_history.
Soft delete (deleted_at) olanlar: users, addresses, categories, products, product_variants, coupons, banners, pages.
Permission kodları (23, 01_auth_rbac.sql):
- dashboard.view | product.view, product.create, product.update, product.delete | category.manage | stock.manage
- order.view, order.update_status, order.cancel, order.refund | payment.confirm | shipment.manage | customer.view
- coupon.manage, banner.manage, menu.manage, page.manage | setting.manage, user.manage, role.manage, audit.view | report.view
Roller: admin = 23 yetkinin tümü; manager = dashboard.view, product.view/create/update, category.manage, stock.manage, order.view, order.update_status, shipment.manage, customer.view, report.view (11); customer = yok.
Sabitlenen ad kuralları: roles.name = admin | manager | customer; menus.location = header | footer_1 | footer_2 | mobile; pages.system_key = about, kvkk, distance_sales, returns, privacy, cookies.

## (3) JSON uç noktaları
- YOK (bu oturumda HTTP/JSON uç noktası yazılmadı).
- Sonraki oturumlar için sözleşme: pages.content temizlenmiş HTML; orders.shipping_address / billing_address, audit_logs.old_values/new_values, payment_callbacks.payload, security_events.detail JSON metnidir (LONGTEXT). IP alanları VARBINARY(16) (IPv4/IPv6).

## (4) ROUTE SATIRLARI
- YOK (route/Controller yazılmadı).
- menu_items.link_value içindeki URL kalıpları (seed): /urunler, /urunler?indirimli=1, /iletisim, /kategori/{slug}; page tipi link_value = pages.slug; region tipi link_value = regions.slug; category tipi link_value = categories.slug.
- Banner/duyuru link_url değerleri: /urunler, /kategori/bal-ve-ari-urunleri.
- Placeholder görsel yolları: /assets/img/banner-1.jpg, /assets/img/banner-1-mobile.jpg, /assets/img/banner-2.jpg, /assets/img/banner-2-mobile.jpg, /assets/img/demo/{urun-slug}-{n}.jpg.

## (5) .env / settings anahtarları
settings (06_seed.sql; gerçek anahtarlar, tip; * = is_public=1):
- site.name*, site.tagline* | contact.email*, contact.phone*, contact.whatsapp*, contact.address* | social.instagram*, social.facebook*
- shipping.free_threshold* (decimal, 750.00) | cod.enabled* (bool), cod.fee* (decimal, 15.00) | card.enabled* (bool)
- bank_transfer.enabled* (bool); bank_transfer.bank_name, bank_transfer.account_holder, bank_transfer.iban, bank_transfer.note (gizli)
- order.card_expiry_minutes (int, 30), order.bank_expiry_hours (int, 48), order.min_amount* (decimal, 0)
- stock.default_low_threshold (int, 5) | mail.from_name | seo.default_title*, seo.default_description*
- legal.company_title*, legal.tax_office*, legal.tax_number*, legal.mersis* | tax.prices_include_vat* (bool, 1)
settings value_type: string, int, decimal, bool, json. Gizli anahtarlar (PayTR vb.) settings'e YAZILMAZ, .env'de tutulur.
.env: dosyalarda yalnızca "PayTR gizli anahtarları .env içindedir" notu var; değişken ADI belirlenmedi (sonraki oturumda 02-guvenlik-kurallari.md ve 00-gereksinimler.md'ye göre belirlenecek).
DB kullanıcıları: ym_app (üretim; yorumlu GRANT bloğu), ym_dev (geliştirme). Veritabanı: yoresel_market, utf8mb4_unicode_ci, MariaDB (carts CHECK notu).

## Uyarılar (sonraki oturumlar için)
- Fiyatlar KDV dahil DECIMAL(12,2); sepet fiyat saklamaz; order_items fiyat/ad anlık kopyadır.
- products.tax_rate varsayılanı 10.00 geçicidir (muhasebeci netleştirecek).
- Seed'deki banka/IBAN/vergi/MERSİS/iletişim değerleri ve kargo tarifesi ÖRNEKTİR; yasal sayfa metinleri TASLAKTIR.
- Admin kullanıcısı seed edilmedi; Argon2id hash'li scripts/create_admin.php ile eklenecek.
- orders.order_number NULL olabilir (kayıt sonrası üretilir); ödeme eşleştirmesi payments.merchant_oid (yalnızca harf+rakam) ile yapılır.