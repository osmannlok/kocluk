# CLAUDE.md

Bu depo, çocuklara yönelik eğitici oyunlar ve öğrenme içerikleri sunan bir web portalı içindir.
Hedef, kısa, öğretici, tekrar oynanabilir oyunlarla öğrenmeyi eğlenceli hale getiren, hızlı, güvenli ve ölçeklenebilir bir platform oluşturmaktır.

## Proje Özeti

- Proje adı: Eğitim Oyun Portalı.
- Teknoloji: PHP + MySQL.
- Mimari: Modüler yapı, public klasörü üzerinden erişim, controller-model-view düzeni.
- Hedef kitle: 6–14 yaş arası çocuklar; ikincil kitle ebeveynler ve öğretmenler.
- İçerik odağı: Matematik, kelime/okuma, mantık/hafıza, dikkat/odaklanma.

## Ana Hedefler

- Çocuklar için güvenli ve öğretici bir oyun deneyimi sunmak.
- Oyunlar ile öğrenme içeriklerini aynı çatı altında toplamak.
- SEO uyumlu, hızlı ve yönetilebilir bir platform kurmak.
- İleride reklam, premium üyelik ve dijital ürün satışına uygun altyapı hazırlamak.

## Zorunlu Teknik Kurallar

- Tüm doğrudan web erişimi yalnızca `public/` klasöründen yapılacak.
- `views/` içinde HTML çıktıları tutulacak.
- Veritabanı işlemleri `app/Models/` altında olacak.
- Sayfa akışı `app/Controllers/` üzerinden yönetilecek.
- Ayarlar `config/` altında toplanacak.
- `storage/` dışarıdan doğrudan erişime kapalı olacak.

## Veritabanı İlkeleri

- `SELECT *` yerine sadece gerekli alanlar çekilecek.
- Döngü içinde sorgu çalıştırılmayacak.
- İlişkili veriler mümkünse `JOIN` ile alınacak.
- Liste sayfalarında sayfalama kullanılacak.
- Sık okunan veriler cache'lenecek.
- Hazırlanmış sorgular kullanılacak.

## Performans Kuralları

- Ana sayfa hafif tutulacak.
- Görseller WebP veya AVIF olacak.
- CSS ve JS dosyaları birleştirilecek ve minify edilecek.
- Kritik olmayan scriptler `defer` ile yüklenecek.
- İlk ekranda sadece gerekli içerik gösterilecek.
- Redis veya dosya cache tercih edilecek.

## Güvenlik Kuralları

- Tüm giriş formlarında prepared statement kullanılacak.
- Şifreler güçlü hash algoritmasıyla saklanacak.
- CSRF koruması olacak.
- Session güvenliği sağlanacak.
- Dosya yüklemelerinde MIME kontrolü yapılacak.
- Admin panel erişimi sınırlandırılacak.

## SEO Kuralları

- Her oyun için dinamik `title` ve `meta description` üretilecek.
- Temiz URL yapısı kullanılacak.
- `robots.txt` ve XML `sitemap` olacak.
- Open Graph etiketleri eklenecek.
- Kategori ve etiket sayfaları SEO dostu olacak.

## Claude İçin Çalışma Talimatı

Claude bu projede:

- Mevcut mimariyi bozmaz.
- Önce basit çalışan sürümü oluşturur.
- Sonra modülleri adım adım ekler.
- Her yeni dosyada mevcut yapı ile uyumu korur.
- Gereksiz karmaşık framework veya ağır bağımlılık önermez.
- Performansı, güvenliği ve ölçeklenebilirliği her zaman dikkate alır.