# Test Senaryoları

## Kullanıcı Girişi

1. Geçerli e-posta ve şifre ile giriş yap.
2. Dashboard’a yönlendirildiğini kontrol et.
3. Yanlış şifre ile giriş yapmayı dene.
4. Hata mesajı görüntülendiğini kontrol et.

## Ürün Ekleme

1. Admin olarak giriş yap.
2. Yeni ürün ekle.
3. Barkod, kategori, birim, alış fiyatı, satış fiyatı ve stok gir.
4. Ürünün listeye eklendiğini kontrol et.
5. Barkodla arama yap.

## Satış Testi

1. Kasiyer olarak giriş yap.
2. Barkod okuyucu ile ürün okut.
3. Ürünün sepete eklendiğini kontrol et.
4. Adedi 2 yap.
5. Nakit ödeme seç.
6. Satışı tamamla.
7. `sales`, `sale_items`, `payments` ve `stock_movements` tablolarını kontrol et.
8. Ürün stoğunun 2 azaldığını doğrula.

## İade Testi

1. Yönetici olarak giriş yap.
2. Daha önce yapılan satışı bul.
3. İade işlemi başlat.
4. İade nedenini yaz.
5. İadeyi onayla.
6. Stoğun arttığını kontrol et.
7. `stock_movements` tablosunda `return` kaydı oluştuğunu doğrula.

## Kritik Stok Testi

1. Bir ürünün kritik stok değerini yüksek ayarla.
2. Stok miktarını kritik seviyenin altına düşür.
3. Kritik stok uyarısının göründüğünü kontrol et.

## Yetki Testi

1. Kasiyer olarak giriş yap.
2. Kullanıcı yönetimi ekranına erişmeyi dene.
3. Erişimin engellendiğini kontrol et.
4. Yönetici olarak aynı işlemi tekrar dene.
5. Erişimin verildiğini kontrol et.
