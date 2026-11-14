# E-Ticaret Proje Yol Haritası (Sıralama)

Modüller sırasıyla şu aşamada geliştirilecektir. Perplexity, Claude için prompt üretirken bu sırayı takip edecektir:

1. **Aşama 1: Çekirdek Altyapı ve Router**
   - `.htaccess` URL yönlendirmesi
   - `Router.php` sınıfı
   - `Database.php` (Singleton PDO bağlantısı)
   - Temel `Controller` ve `Model` sınıfları

2. **Aşama 2: Ürün ve Kategori Modülü**
   - `ProductController` ve `ProductModel`
   - Ana sayfada ürünlerin listelenmesi (Bootstrap Card yapısı)
   - Ürün detay sayfası

3. **Aşama 3: Kullanıcı Kimlik Doğrulama (Auth)**
   - Kayıt Ol (Register) ve Giriş Yap (Login) sayfaları
   - Session yönetimi ve yetkilendirme middleware kontrolü

4. **Aşama 4: Sepet Sistemi (AJAX)**
   - Veritabanı tabanlı sepet yönetimi
   - Fetch API ile sepete ekle / sepetten çıkar işlemleri

5. **Aşama 5: Sipariş ve Ödeme Ekranı**
   - Checkout formu ve siparişi veritabanına kaydetme (`orders` ve `order_items`)