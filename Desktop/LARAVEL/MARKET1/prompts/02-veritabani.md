# Görev 2: Veritabanı Migration’ları

## Kapsam

`DATABASE_SCHEMA.md` dosyasındaki tüm tablolar için migration oluştur.

## Tablolar

1. categories
2. suppliers
3. products
4. product_barcodes
5. purchases
6. purchase_items
7. customers
8. sales
9. sale_items
10. payments
11. stock_movements
12. settings
13. activity_logs

## Kurallar

- Migration sırasına uy.
- Her migration’da `down()` metni olsun.
- Foreign key’ler `foreignId()->constrained()` ile tanımlansın.
- Para alanları `DECIMAL(12,2)` olsun.
- Miktar alanları `DECIMAL(12,3)` olsun.
- Gerekli index’leri ekle.

## Kabul Kriterleri

- `php artisan migrate` hatasız çalışmalı.
- `php artisan migrate:rollback` çalışmalı.
- Tüm tablolar MySQL’de oluşmalı.
