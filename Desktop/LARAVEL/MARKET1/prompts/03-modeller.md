# Görev 3: Eloquent Modelleri ve İlişkiler

## Kapsam

Aşağıdaki modelleri oluştur:

- Category
- Supplier
- Product
- ProductBarcode
- Purchase
- PurchaseItem
- Customer
- Sale
- SaleItem
- Payment
- StockMovement
- Setting
- ActivityLog

## Kurallar

- Her modelde `$fillable` alanlarını tanımla.
- İlişkileri Eloquent relationship metotlarıyla yaz.
- `Product`, `Category`, `Supplier`, `Purchase`, `Sale`, `Customer` modellerinde `SoftDeletes` kullan.
- `Product` modelinde `scopeLowStock` ekle.
- `Sale` modelinde `scopeBetweenDates` ekle.

## Kabul Kriterleri

- İlişkiler doğru çalışmalı.
- `php artisan tinker` ile ilişkiler test edilebilmelidir.
