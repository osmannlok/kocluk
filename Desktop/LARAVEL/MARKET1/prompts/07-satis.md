# Görev 7: Kasa ve Satış Modülü

## Kapsam

- Barkod okutarak sepete ürün ekleme
- Manuel ürün arama
- Adet değiştirme
- Satır indirimi
- Genel indirim
- Nakit, kart, havale ve veresiye ödeme
- Para üstü hesaplama
- Satış tamamlama
- Satış iptali
- İade

## Kullanılacak Tablolar

- products
- product_barcodes
- customers
- sales
- sale_items
- payments
- stock_movements

## Kurallar

- Satış işlemi `DB::transaction` içinde yapılmalıdır.
- Satış anındaki ürün maliyeti `sale_items.purchase_price` alanına yazılmalıdır.
- Satış sonrası stok azaltılmalıdır.
- Her stok değişimi `stock_movements` tablosuna yazılmalıdır.
- Stok 0 olan ürün satılamaz.
- Kasiyer en fazla %10 indirim yapabilir.
- Veresiye satışında müşteri seçimi zorunludur.

## Kabul Kriterleri

- Barkod okutunca ürün sepete eklenmeli.
- Satış tamamlanınca sales, sale_items, payments ve stock_movements kayıtları oluşmalı.
- Stok doğru azalmalı.
- İade işleminde stok artmalı.
