# AŞİROĞLU YÖRESEL — Gereksinim Belgesi

## 1. Proje Özeti
- Proje adı: AŞİROĞLU YÖRESEL
- Amaç: Türkiye’de yöresel gıda ürünleri satan tek satıcılı e-ticaret sitesi
- Teknoloji: PHP 8.2+ Custom MVC, PDO, Bootstrap 5, Vanilla JS, Fetch API
- Ürün tipi: Fiziksel yöresel gıda ürünleri
- Kargo: Manuel takip
- Ödeme: Kredi/banka kartı, havale/EFT, kapıda ödeme
- MVP: Ürün, sepet, üyelik, sipariş, admin panel

## 2. Roller
- Ziyaretçi: Ürün görüntüleme, arama, filtreleme, sepete ekleme
- Müşteri: Kayıt, giriş, profil, adres, sipariş, sipariş takibi
- Yönetici: Ürün, kategori, marka, stok, sipariş, kullanıcı, kupon yönetimi
- Süper Admin: Rol, izin, yönetici ve sistem ayarları

## 3. Ürün Yönetimi
- FR-001: Ana sayfada öne çıkan, yeni ve indirimli ürünler gösterilecek
- FR-002: Kategori, marka, fiyat, stok ve sıralama filtreleri olacak
- FR-003: Ürün arama ürün adı, açıklama ve SKU üzerinden yapılacak
- FR-004: Ürün listelemede sayfalama olacak
- FR-005: Ürün kartında görsel, ad, fiyat, indirim oranı, stok durumu ve sepete ekleme olacak
- FR-010: Yönetici ürün ekleyecek, düzenleyecek, pasifleştirecek ve silecek
- FR-011: Ürün alanları: ad, slug, SKU, kısa açıklama, açıklama, kategori, marka, KDV, fiyat, indirimli fiyat, stok, kritik stok, durum, SEO alanları
- FR-012: Ürüne birden fazla görsel yüklenecek; ilk görsel kapak olacak
- FR-013: Görseller sıralanabilecek
- FR-014: Ürün detayında galeri, fiyat, stok, açıklama, teslimat bilgisi ve benzer ürünler olacak
- FR-015: Ürün slug benzersiz ve SEO dostu olacak

## 4. Varyant ve Stok
- FR-020: Ürüne varyant tanımlanacak; örn. 500 Gr, 1 Kg, 2 Kg
- FR-021: Her varyant için SKU, fiyat farkı, stok ve kritik stok tanımlanacak
- FR-022: Varyant seçildiğinde fiyat ve stok anlık güncellenecek
- FR-023: Sipariş onaylandığında stok düşülecek
- FR-024: Kritik stok eşiği aşıldığında yönetici panelinde uyarı görünecek
- FR-025: Stok 0 olduğunda ürün/varyant satışa kapanacak
- FR-026: Yönetici manuel stok girişi, çıkışı ve düzeltmesi yapabilecek

## 5. Kategori ve Marka
- FR-030: Hiyerarşik kategori yönetimi olacak
- FR-031: Kategori; ad, slug, açıklama, görsel, üst kategori ve aktiflik alanlarına sahip olacak
- FR-032: Marka ekleme, düzenleme ve pasifleştirme olacak
- FR-033: Pasif kategori/markadaki ürünler vitrinde listelenmeyecek

## 6. Sepet
- FR-040: Ziyaretçi sepete ürün ekleyebilecek
- FR-041: Giriş yapıldığında misafir sepeti üye sepetiyle birleştirilecek
- FR-042: Adet güncelleme, ürün çıkarma ve toplam görüntüleme olacak
- FR-043: Sepet işlemleri Fetch API ile yapılacak
- FR-044: Stok kontrolü sepete ekleme sırasında yapılacak
- FR-045: Ara toplam, indirim, kargo, KDV ve genel toplam gösterilecek
- FR-046: Ücretsiz kargo eşiğine kalan tutar gösterilecek

## 7. Üyelik
- FR-050: Kullanıcı ad, e-posta, telefon ve şifre ile kayıt olacak
- FR-051: E-posta doğrulaması yapılacak
- FR-052: Şifre sıfırlama akışı olacak
- FR-053: Profil bilgileri güncellenebilecek
- FR-054: Birden fazla teslimat adresi yönetilebilecek
- FR-055: Sipariş geçmişi ve durumları görülebilecek
- FR-056: Hesap kapatma talebi KVKK uyumlu şekilde işlenecek

## 8. Sipariş
- FR-060: Checkout adımları: adres, kargo, ödeme, özet, onay
- FR-061: Misafir siparişi desteklenecek
- FR-062: Sipariş özetinde tüm tutar kalemleri gösterilecek
- FR-063: Benzersiz sipariş numarası üretilecek
- FR-064: Sipariş onay e-postası gönderilecek
- FR-065: Sipariş durumları: Beklemede, Ödeme Bekliyor, Ödendi, Hazırlanıyor, Kargoya Verildi, Teslim Edildi, İptal Edildi, İade Sürecinde, İade Edildi
- FR-066: Yönetici manuel kargo firması, takip numarası ve tarih girecek
- FR-067: Takip numarası girildiğinde müşteriye e-posta gönderilecek
- FR-068: Müşteri kargo bilgisini sipariş detayında görebilecek

## 9. Ödeme
- FR-070: Kredi/banka kartı, havale/EFT ve kapıda ödeme desteklenecek
- FR-071: Kart verileri veritabanında saklanmayacak
- FR-072: Havale/EFT için banka bilgileri ve reference kodu gösterilecek
- FR-073: Havale/EFT ödemesi yönetici tarafından onaylanacak
- FR-074: Kapıda ödeme siparişi “Beklemede” durumunda oluşturulacak
- FR-075: Ödeme webhook imzası doğrulanacak
- FR-076: Başarılı ödemede sipariş “Ödendi” olacak ve stok düşecek
- FR-077: Başarısız ödemede kullanıcı bilgilendirilecek

## 10. Kampanya
- FR-080: Ürüne indirimli fiyat tanımlanabilecek
- FR-081: Yüzde veya sabit tutarlı kupon oluşturulabilecek
- FR-082: Kupon; kod, tip, değer, minimum sepet, tarih, limit ve aktiflik alanlarına sahip olacak
- FR-083: Kupon koşulları doğrulanacak
- FR-084: Ücretsiz kargo eşiği tanımlanabilecek
- FR-085: Kargo ücreti panelden yönetilecek

## 11. Admin Paneli
- FR-090: Panel yalnızca yetkili rollerle erişilecek
- FR-091: Ürün, kategori, marka, varyant, stok, sipariş, müşteri, kupon ve ayar yönetimi olacak
- FR-092: Sipariş durumu güncellenebilecek ve sipariş notu eklenebilecek
- FR-093: Kargo bilgisi girilebilecek
- FR-094: Satış, sipariş, en çok satan ürün, düşük stok ve müşteri raporları olacak
- FR-095: Site ayarları yönetilebilecek
- FR-096: Rol ve yetki yönetimi olacak
- FR-097: Kritik stok uyarıları ana panelde listelenecek

## 12. Veri Modeli
- users, roles, permissions, role_permissions
- addresses
- categories, brands
- products, product_images, product_variants
- stock_movements
- carts, cart_items
- coupons, coupon_usages
- shipping_methods
- orders, order_items, order_addresses, order_shipments
- payments
- settings, activity_logs

## 13. Kabul Kriterleri
- Kullanıcı ürünü sepete ekleyip sipariş verebilmeli
- Varyant seçiminde doğru fiyat ve stok gösterilmeli
- Sipariş sonrası stok doğru düşmeli
- Kritik stok uyarısı çalışmalı
- Yönetici tüm temel verileri yönetebilmeli
- Manuel kargo takibi çalışmalı
- CSRF, XSS, SQL injection ve yetkisiz erişim korumaları test edilmeli
- Site mobil uyumlu olmalı