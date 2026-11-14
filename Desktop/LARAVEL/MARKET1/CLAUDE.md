# CLAUDE.md — Market Stok/Satış Sistemi

## Proje

Bu proje, Kahramanmaraş’taki küçük bir yöresel market için geliştirilen **Laravel + MySQL tabanlı barkodlu stok/satış yönetim sistemidir**.

Projenin temel amacı; ürünlerin barkodla satılması, stokların otomatik takip edilmesi, satış ve kâr raporlarının alınması, tedarikçi/müşteri işlemlerinin yönetilmesi ve market operasyonlarının dijitalleştirilmesidir.

## Teknoloji Kuralları

- Laravel 12 kullan.
- Veritabanı olarak MySQL kullan.
- Frontend için Blade kullan.
- CSS için Tailwind CSS veya Bootstrap kullan.
- Kimlik doğrulama için Laravel Breeze kullan.
- API geliştirmesi gerektiğinde Laravel Sanctum kullan.
- Grafik ve rapor ekranlarında Chart.js kullan.
- Tüm arayüz metinleri Türkçe olmalıdır.
- Kod yorumları Türkçe yazılabilir, ancak değişken ve fonksiyon adları İngilizce olmalıdır.

## Genel Geliştirme Kuralları

- Kodu her zaman küçük, test edilebilir ve bağımsız parçalara böl.
- Her görevde önce değiştirilecek veya oluşturulacak dosyanın tam yolunu yaz.
- Ardından dosyanın tamamını ver; eksik kod bırakma.
- Yeni bir özellik eklerken önce mevcut proje yapısına uygun olup olmadığını kontrol et.
- Gereksiz paket kurma; standart Laravel özelliklerini tercih et.
- Controller içinde karmaşık iş mantığı yazma; Service sınıflarını kullan.
- Model ilişkilerini Eloquent relationship metotlarıyla tanımla.
- Raw SQL yerine Query Builder veya Eloquent kullan.
- Kullanıcıdan gelen tüm verileri Form Request veya Laravel validation ile doğrula.
- Silme işlemlerinde kullanıcıdan onay iste.
- Her önemli değişiklikten sonra test edilmesi gereken adımları kısaca listele.

## Veritabanı Kuralları

- Migration kullan; elle SQL tablosu oluşturma.
- Tüm tablolarda `id`, `timestamps` alanları bulunmalıdır.
- Silinebilir kayıtlarda `softDeletes` kullan.
- Foreign key tanımlarken `foreignId()->constrained()` kullan.
- Parent tablo, child tablodan önce oluşturulmalıdır.
- Her migration dosyasında çalışan bir `down()` metni bulunmalıdır.
- Migration dosyalarını sırayla ve tek tek oluştur.
- Yeni migration eklemeden önce mevcut migration dosyalarını kontrol et.

### Alan Tipleri

- Para alanları için `decimal('field', 12, 2)` kullan.
- Miktar alanları için `decimal('field', 12, 3)` kullan.
- Yüzde veya KDV oranı için `decimal('field', 5, 2)` kullan.
- Metin aramalarında kullanılacak alanlara index ekle.
- Foreign key alanlarına index ekle.
- Tarih bazlı raporlamada kullanılacak tarih alanlarına index ekle.

### Zorunlu Tablolar

- users
- categories
- suppliers
- products
- product_barcodes
- purchases
- purchase_items
- customers
- sales
- sale_items
- payments
- stock_movements
- settings
- activity_logs

## Ürün ve Barkod Kuralları

- Ürünler `products` tablosunda tutulur.
- Barkodlar `product_barcodes` tablosunda tutulur.
- Bir ürüne birden fazla barkod eklenebilmelidir.
- Barkod alanı `unique` olmalıdır.
- Ürün silme yerine `softDeletes` kullanılmalıdır.
- Ürün pasife alındığında satışta seçilememeli, ancak geçmiş satışlar etkilenmemelidir.
- Ürün birimi `adet`, `kg`, `gram`, `litre` veya `paket` olabilir.
- Tartılı ürünler için `is_weighed` alanı kullanılmalıdır.
- Her ürünün `critical_stock` değeri olmalıdır.
- Stok, kritik stok seviyesinin altına düştüğünde uyarı gösterilmelidir.

## Stok Kuralları

- Her stok değişimi `stock_movements` tablosuna kaydedilmelidir.
- `movement_type` değerleri şunlar olabilir:
  - `purchase`: Ürün girişi
  - `sale`: Satış nedeniyle çıkış
  - `return`: İade nedeniyle giriş
  - `damage`: Fire veya bozulma
  - `correction`: Stok sayım düzeltmesi
  - `initial`: Başlangıç stok girişi

- Satış, iade, fire ve sayım işlemleri transaction içinde yapılmalıdır.
- Stok düşerken `stock_before` ve `stock_after` değerleri kaydedilmelidir.
- Stok eksiye düşmemelidir; stok yetersizse kullanıcı uyarılmalıdır.
- Stok hareketi silinemez; yalnızca düzeltme kaydı oluşturulabilir.

## Satış Kuralları

- Satışlar `sales` tablosunda, satış kalemleri `sale_items` tablosunda tutulur.
- Her satış için benzersiz bir `sale_no` üretilmelidir.
- Satış tarih alanı `sale_date` olmalıdır.
- Satışta ürün adedi, birim fiyat, indirim, KDV ve toplam tutar saklanmalıdır.
- Satış anındaki ürün maliyeti `sale_items.purchase_price` alanına yazılmalıdır.
- Kâr şu formülle hesaplanır:

```text
Kâr = Miktar x (Satış Fiyatı - İndirim - Alış Fiyatı)
```

- Bir satışta birden fazla ödeme türü kullanılabilmelidir.
- Ödemeler `payments` tablosunda tutulmalıdır.
- Ödeme türleri:
  - `cash`: Nakit
  - `card`: Kart
  - `transfer`: Havale/EFT
  - `credit`: Veresiye
  - `refund`: İade

- Satış iptal edildiğinde stok geri yüklenmelidir.
- İade işleminde stok artmalı ve `stock_movements` tablosuna `return` kaydı eklenmelidir.

## Kullanıcı ve Yetki Kuralları

- Roller:
  - `admin`: Yönetici
  - `cashier`: Kasiyer
  - `warehouse`: Depocu
  - `accountant`: Muhasebe

- Yönetici tüm modüllere erişebilmelidir.
- Kasiyer yalnızca satış, ürün arama ve kendi satış raporlarına erişebilmelidir.
- Depocu ürün girişi, stok sayımı, fire ve iade işlemlerine erişebilmelidir.
- Muhasebe rapor, ödeme ve tedarikçi borç ekranlarına erişebilmelidir.
- Şifreler Laravel’in varsayılan hashing sistemiyle saklanmalıdır.
- Kullanıcı işlemleri `activity_logs` tablosuna kaydedilmelidir.

## Raporlama Kuralları

- Raporlar günlük, haftalık, aylık ve özel tarih aralığına göre hazırlanmalıdır.
- Raporlarda en az şu veriler bulunmalıdır:
  - Toplam ciro
  - Nakit, kart, havale ve veresiye kırılımı
  - Ürün bazlı satış adedi
  - Ürün bazlı ciro
  - Ürün bazlı kâr
  - En çok satan ürünler
  - Kritik stokta olan ürünler
  - Fire ve iade toplamları
  - Kasiyer bazlı satış performansı

- Rapor verileri gerektiğinde CSV veya Excel olarak dışa aktarılabilmelidir.
- Grafiklerde Chart.js kullanılabilir.

## Dosya ve Klasör Yapısı

- Controller’lar `app/Http/Controllers` altında gruplandırılmalıdır.
- Admin controller’ları `app/Http/Controllers/Admin` altında olmalıdır.
- Kasiyer controller’ları `app/Http/Controllers/Cashier` altında olmalıdır.
- API controller’ları `app/Http/Controllers/Api` altında olmalıdır.
- Form Request sınıfları `app/Http/Requests` altında olmalıdır.
- Modeller `app/Models` altında olmalıdır.
- İş mantığı `app/Services` altında olmalıdır.
- Blade dosyaları `resources/views` altında modüle göre gruplandırılmalıdır.
- Route’lar `routes/web.php` ve `routes/api.php` içinde düzenli tutulmalıdır.

## Önerilen Servis Sınıfları

- `SaleService`: Satış oluşturma, iptal, iade ve ödeme işlemleri
- `StockService`: Stok giriş, çıkış, fire, sayım ve stok kontrolü
- `PurchaseService`: Tedarikçi alımları ve ürün girişleri
- `ReportService`: Satış, stok, kâr ve performans raporları
- `SettingService`: Market ve sistem ayarları

## Güvenlik Kuralları

- `.env` dosyası asla Git repository’sine eklenmemelidir.
- Production ortamında `APP_DEBUG=false` olmalıdır.
- Mass assignment için Model’lerde `$fillable` alanları dikkatli tanımlanmalıdır.
- Kullanıcı girdileri mutlaka doğrulanmalıdır.
- CSRF koruması devre dışı bırakılmamalıdır.
- Yetkisiz kullanıcılar admin işlemlerine erişememelidir.
- Silme, güncelleme ve satış işlemleri yetki kontrolünden geçmelidir.
- Veritabanı yedekleri düzenli alınmalıdır.

## Çalışma Yöntemi

1. Önce mevcut dosya yapısını ve ilgili modelleri incele.
2. Tek bir küçük görev seç.
3. Gerekli dosya yolunu belirt.
4. Tam ve çalışır kodu üret.
5. Gerekli migration, model, controller, route ve Blade dosyalarını sırayla ver.
6. Test edilmesi gereken adımları listele.
7. Kullanıcı hata bildirirse yalnızca ilgili dosyayı düzelt.

## İlk Geliştirme Sırası

1. Laravel projesini kur.
2. MySQL bağlantısını ayarla.
3. Kullanıcı girişini kur.
4. `categories`, `suppliers`, `products`, `product_barcodes` tablolarını oluştur.
5. İlgili modelleri ve ilişkileri oluştur.
6. Ürün ekleme, düzenleme, listeleme ekranını yap.
7. Barkod ekleme ve barkodla arama özelliğini ekle.
8. Stok girişi ve `stock_movements` tablosunu devreye al.
9. Barkod okutarak satış ekranını geliştir.
10. Nakit/kart ödeme ve satış tamamlama akışını kur.
11. Günlük satış ve kâr raporlarını ekle.
12. Kritik stok uyarısını ekle.
13. Tedarikçi, müşteri ve veresiye modüllerini ekle.
14. Fiş yazdırma ve etiket yazdırma özelliklerini ekle.
15. Test, yedekleme ve canlıya alma süreçlerini tamamla.

## Önemli Not

Bu sistem ilk aşamada tek market ve tek kasa için tasarlanmıştır. Çok şube, çok depo, online satış ve mobil uygulama özellikleri sonraki sürümlerde eklenmelidir.

Kod üretirken mevcut yapıyı bozma; küçük, geri alınabilir ve test edilebilir adımlarla ilerle.
