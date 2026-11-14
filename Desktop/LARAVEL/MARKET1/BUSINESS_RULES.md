# İşletme Kuralları

## Ürün ve Stok

- Stok 0 olan ürün satılamaz.
- Stok kritik seviyenin altına düştüğünde uyarı gösterilir.
- Ürün silinmez; pasife alınır.
- Ürün stoğu eksiye düşmez.
- Her stok değişimi `stock_movements` tablosuna kaydedilir.
- Ürün girişi, satış, iade, fire ve sayım işlemleri transaction içinde yapılır.

## Satış

- Satışlar barkod okutularak yapılır.
- Barkod okunamadığında ürün manuel aranabilir.
- Kasiyer en fazla %10 indirim yapabilir.
- %10’dan fazla indirim yalnızca yönetici onayıyla yapılabilir.
- Satış iptali yalnızca yönetici tarafından yapılabilir.
- Satış tamamlandığında stok otomatik azalır.
- Satış anındaki ürün maliyeti `sale_items.purchase_price` alanına yazılır.
- Nakit, kart, havale, veresiye ve iade ödeme türleri desteklenir.

## İade

- İade yalnızca yönetici veya yetkili kullanıcı tarafından yapılabilir.
- İade edilen ürün stoğa geri eklenir.
- İade işlemi `stock_movements` tablosunda `return` olarak kaydedilir.
- İade nedeni not alanına yazılabilir.

## Fire ve Sayım

- Fire, bozulma veya kırılma durumunda stok azaltılır.
- Fire işlemi `damage` olarak kaydedilir.
- Stok sayımı sonrası fark varsa `correction` kaydı oluşturulur.
- Sayım farkı gerekçesi not alanına yazılır.

## Veresiye

- Veresiye satışında müşteri seçimi zorunludur.
- Müşteri bakiyesi satış tutarı kadar artar.
- Müşteri ödeme yaptığında bakiye azalır.
- Borçlu müşteriler raporlanabilir.

## Tedarikçi

- Ürün girişinde tedarikçi seçilebilir.
- Alım faturasında toplam, ödenen ve kalan tutar takip edilir.
- Tedarikçi borç/alacak bakiyesi güncellenir.

## Raporlar

- Günlük, haftalık, aylık ve tarih aralığına göre rapor alınabilir.
- Raporlarda ciro, ödeme türü, ürün satışı, kâr, fire ve iade yer alır.
- Kritik stokta olan ürünler ayrı listede gösterilir.
- Raporlar CSV veya Excel olarak dışa aktarılabilir.
