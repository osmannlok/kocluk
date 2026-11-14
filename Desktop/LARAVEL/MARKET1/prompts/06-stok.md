# Görev 6: Stok Yönetimi

## Kapsam

- Ürün stok girişi
- Stok hareketi kaydı
- Fire kaydı
- Stok sayımı
- Sayım düzeltmesi
- Kritik stok uyarısı

## Kullanılacak Tablolar

- products
- stock_movements
- purchases
- purchase_items

## Kurallar

- Her stok değişimi `stock_movements` tablosuna yazılmalıdır.
- `stock_before` ve `stock_after` alanları doldurulmalıdır.
- Stok eksiye düşmemelidir.
- Satış, iade, fire ve sayım işlemleri transaction içinde yapılmalıdır.
- Kritik stokta olan ürünler ayrı listede gösterilmelidir.

## Kabul Kriterleri

- Ürün girişi stoğu artırmalı.
- Fire stoğu azaltmalı.
- Sayım düzeltmesi doğru çalışmalı.
- Kritik stok uyarısı görünmeli.
