# Ekran Akışları

## Satış Ekranı

1. Kasiyer barkod okutur.
2. Sistem ürünü barkodla arar.
3. Ürün bulunursa sepete eklenir.
4. Ürün bulunamazsa uyarı gösterilir.
5. Kasiyer adet veya indirim düzenleyebilir.
6. Ödeme türü seçilir.
7. Satış tamamlanır.
8. Stok azalır.
9. Fiş oluşturulur.

## Ürün Girişi

1. Depocu tedarikçi seçer.
2. Ürün ekler veya barkod okutur.
3. Miktar ve alış fiyatı girilir.
4. Alım kaydedilir.
5. Stok artar.
6. `stock_movements` kaydı oluşur.

## İade

1. Satış fişi bulunur.
2. İade edilecek ürün seçilir.
3. İade nedeni yazılır.
4. İade onaylanır.
5. Stok artar.
6. `return` hareketi oluşur.

## Stok Sayımı

1. Depocu ürün seçer.
2. Sistemdeki stok gösterilir.
3. Gerçek stok girilir.
4. Fark hesaplanır.
5. Sayım onaylanır.
6. `correction` hareketi oluşur.
