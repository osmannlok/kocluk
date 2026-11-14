# Proje Kuralları

## Klasör Yapısı

- Controller’lar `app/Http/Controllers` altında gruplandırılır.
- Admin controller’ları `app/Http/Controllers/Admin` altında olur.
- Kasiyer controller’ları `app/Http/Controllers/Cashier` altında olur.
- API controller’ları `app/Http/Controllers/Api` altında olur.
- Form Request sınıfları `app/Http/Requests` altında olur.
- Modeller `app/Models` altında olur.
- İş mantığı `app/Services` altında olur.
- Blade dosyaları `resources/views` altında modüle göre gruplandırılır.

## Sorumluluklar

- Controller yalnızca HTTP isteğini alır ve Service metodunu çağırır.
- İş mantığı Service sınıfında yazılır.
- Veritabanı işlemleri Service içinde transaction ile yapılır.
- Model yalnızca veri yapısı, ilişkiler ve basit scope’ları içerir.
- Form Request sınıfları doğrulama ve yetki kontrolünü yapar.

## İsimlendirme

- Tablo adları çoğul ve snake_case: `sale_items`
- Model adları tekil ve PascalCase: `SaleItem`
- Controller adları PascalCase: `SaleController`
- Route adları kebab-case: `sales.create`
- Blade klasörleri kebab-case: `sale-items`

## Git Kuralları

- Her özellik ayrı branch’te geliştirilir.
- Commit mesajları Türkçe veya İngilizce olabilir, ancak kısa ve açık olmalıdır.
- Örnek: `feat: barkodla ürün arama eklendi`
- `.env` dosyası asla repository’ye eklenmez.
- Migration dosyaları çalıştırıldıktan sonra düzenlenmemelidir; değişiklik için yeni migration oluşturulur.

## Test Kuralları

- Her yeni modül için en az bir manuel test senaryosu yazılır.
- Satış, iade, fire ve stok işlemleri transaction içinde test edilir.
- Kritik stok uyarısı test edilir.
- Yetki kontrolleri test edilir.
