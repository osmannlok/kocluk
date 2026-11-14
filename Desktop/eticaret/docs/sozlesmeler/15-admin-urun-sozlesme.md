# 15-admin-urun-sozlesme.md
Oturum: [12 Admin ürün/kategori/stok]. Kaynak: yazılan GERÇEK kod (12.1-12.15). routes/admin.php bu oturumda yazılmadı. Controller öneki App\Controllers\Admin\, servis öneki App\Services\.

## (1) Dosya, sınıf, public metot imzaları
- app/Services/ImageUploadService.php: final ImageUploadService. store(array $file, string $subdir, array $opts=[]): array{path:string,width:int,height:int} (opts: max_width=1600, thumb=true, thumb_width=400; yol /uploads/{subdir}/YYYY/MM/{hex32}.{jpg|png|webp}, küçük görsel {hex32}_thumb.*; ret = SecurityEvent 'upload_rejected' + ValidationException['file']) | delete(string $path): void (yalnız /uploads/ altı, realpath). Görev: güvenli görsel yükleme (GD ile yeniden encode).
- app/Services/ProductAdminService.php: final ProductAdminService (ctor: ?Product,?ProductVariant,?Category,?Region,?StockService). save(?int $productId, array $data, array $variants, ?int $adminId): int | delete(int $productId, int $adminId): void (soft) | toggleActive(int $productId, int $adminId): bool. Görev: ürün+varyant kaydı, slug, audit; stok yalnız StockService::adjust.
- app/Controllers/Admin/ProductAdminController.php: final, AdminBaseController'dan türer (ctor: ?Product,?ProductVariant,?ProductImage,?Category,?Region,?ProductAdminService,?ImageUploadService). index|create|store(Request): Response | edit|update|destroy|toggle|uploadImage|reorderImages(Request, int $id): Response | deleteImage|setPrimaryImage(Request, int $id, int $imageId): Response. Görev: ürün CRUD + görsel uçları.
- app/Controllers/Admin/CategoryAdminController.php: final (ctor: ?Category,?Product,?ImageUploadService). index|create|store|reorder(Request): Response | edit|update|destroy|toggle(Request, int $id): Response. Görev: kategori ağacı yönetimi.
- app/Controllers/Admin/RegionAdminController.php: final (ctor: ?Region,?Product). index|store(Request): Response | edit|update|destroy(Request, int $id): Response. Görev: yöre CRUD (liste ve form aynı sayfada).
- app/Controllers/Admin/StockAdminController.php: final (ctor: ?ProductVariant,?Product,?StockMovement,?Order,?StockService). index|lowStock|adjust(Request): Response | history(Request, int $variantId): Response. Görev: stok listesi, düzeltme, geçmiş.
- Görünümler (resources/views/admin/): products/index.php ($products,$pager,$filters,$categories,$regions) | products/form.php ($title,$isEdit,$product,$formAction,$variants,$images,$imageLimit,$categories,$regions,$pageScripts) | categories/index.php ($categories: ağaç sırası, depth,child_count,is_first,is_last) | categories/form.php ($title,$isEdit,$category,$formAction,$parentOptions) | regions/index.php ($regions,$isEdit,$region,$formAction) | stock/index.php ($rows,$pager,$filters{q,durum},$lockedLow,$types) | stock/history.php ($variant,$movements,$pager).
- public/assets/js/admin-product-form.js (form[data-dirty-guard]; varyant satırları, slug önerisi, görsel yükleme/sıralama) | public/assets/js/admin-stock.js (#stockModal[data-adjust-url], [data-stock-form]).
- [EK] Product::adminList satırı (06a'ya işlenecek): önceki anahtarlara ek is_active:bool, is_featured:bool, category_id:int, category_name:string, created_at, updated_at, deleted_at, variant_count:int (aktif+silinmemiş, yoksa 0), min_price/max_price:?string "1234.50" (final_price; varyant yoksa NULL), low_stock:bool, stock_total:?int (aktif+silinmemiş+track_stock=1 toplamı; takip edilen varyant yoksa NULL, 0 = takipli ve stok sıfır). Filtreler (q,category_id,region_id,is_active,is_featured,trashed) ve 'p.id DESC' sıralaması değişmedi. Varyant toplamları tek türetilmiş alt sorgudan (GROUP BY product_id).

## (2) Tablolar ve permission kodları
Tablolar: products, product_variants, product_images, categories, regions, stock_movements (yalnız StockService::adjust), orders (okuma: order_number), audit_logs (yazma), security_events (yazma: 'upload_rejected'); VIEW v_low_stock (ProductVariant::lowStock).
Permission: product.view (ürün listesi) | product.create (create,store) | product.update (edit,update,toggle, tüm görsel uçları) | product.delete (ürün destroy; kategori ve yöre destroy'da category.manage ile BİRLİKTE) | category.manage (kategori + yöre) | stock.manage (stok).
Audit action'ları: product.create|update|delete|toggle, product.image.add|delete|primary|reorder, category.create|update|delete|toggle|reorder, region.create|update|delete.

## (3) JSON uç noktaları
Başarı {"ok":true,"data":{...}}, hata 4xx {"ok":false,"error":{"code":"validation","message":str,"errors":{alan:str}}}. Tüm POST'larda X-CSRF-Token başlığı.
- POST /yonetim/urunler/{id}/aktif (JSON istenirse) -> data{id:int,is_active:bool}; aksi halde 302 + flash.
- POST /yonetim/urunler/{id}/gorsel: multipart image (tek dosya) + alt_text? -> data{image{id,path,thumb_path,alt_text,sort_order,is_primary:bool}}; ürün başına en çok 12 görsel.
- POST /yonetim/urunler/{id}/gorsel/{imageId}/ana ve /sil -> data{id}.
- POST /yonetim/urunler/{id}/gorsel/sirala: JSON {order:[imageId,...]} (hepsi bu ürüne ait, tekrarsız) -> data{order:[...]}.
- POST /yonetim/kategoriler/{id}/aktif -> data{id,is_active}. POST /yonetim/kategoriler/sirala: {id:int,direction:"up"|"down"} (JSON veya form) -> data{id,order:[ids]}.
- POST /yonetim/stok/duzelt: JSON {variant_id:int,type:"purchase|manual_adjust|correction",note:str(zorunlu,<=255), delta:int YA DA new_quantity:int>=0} (yalnız biri; purchase için delta>0, new_quantity yok) -> data{variant_id,quantity_change,stock_quantity,low_stock_threshold,status:"untracked|out|low|ok"}.
- Diğer kaydet/sil uçları CSRF'li form POST: başarıda 302 + flash; doğrulama hatasında ValidationException (errors + old).

## (4) ROUTE SATIRLARI
Grup: prefix /yonetim | middleware auth. Her metot ayrıca require() çağırır.
GET /yonetim/urunler -> ProductAdminController@index | auth, perm:product.view
GET /yonetim/urunler/olustur -> ProductAdminController@create | auth, perm:product.create
POST /yonetim/urunler -> ProductAdminController@store | auth, perm:product.create
GET /yonetim/urunler/{id}/duzenle -> ProductAdminController@edit | auth, perm:product.update
POST /yonetim/urunler/{id} -> ProductAdminController@update | auth, perm:product.update
POST /yonetim/urunler/{id}/sil -> ProductAdminController@destroy | auth, perm:product.delete
POST /yonetim/urunler/{id}/aktif -> ProductAdminController@toggle | auth, perm:product.update
POST /yonetim/urunler/{id}/gorsel -> ProductAdminController@uploadImage | auth, perm:product.update
POST /yonetim/urunler/{id}/gorsel/sirala -> ProductAdminController@reorderImages | auth, perm:product.update
POST /yonetim/urunler/{id}/gorsel/{imageId}/ana -> ProductAdminController@setPrimaryImage | auth, perm:product.update
POST /yonetim/urunler/{id}/gorsel/{imageId}/sil -> ProductAdminController@deleteImage | auth, perm:product.update
GET /yonetim/kategoriler -> CategoryAdminController@index | auth, perm:category.manage
GET /yonetim/kategoriler/olustur -> CategoryAdminController@create | auth, perm:category.manage
POST /yonetim/kategoriler -> CategoryAdminController@store | auth, perm:category.manage
POST /yonetim/kategoriler/sirala -> CategoryAdminController@reorder | auth, perm:category.manage
GET /yonetim/kategoriler/{id}/duzenle -> CategoryAdminController@edit | auth, perm:category.manage
POST /yonetim/kategoriler/{id} -> CategoryAdminController@update | auth, perm:category.manage
POST /yonetim/kategoriler/{id}/aktif -> CategoryAdminController@toggle | auth, perm:category.manage
POST /yonetim/kategoriler/{id}/sil -> CategoryAdminController@destroy | auth, perm:category.manage, perm:product.delete
GET /yonetim/yoreler -> RegionAdminController@index | auth, perm:category.manage
GET /yonetim/yoreler/{id}/duzenle -> RegionAdminController@edit | auth, perm:category.manage
POST /yonetim/yoreler -> RegionAdminController@store | auth, perm:category.manage
POST /yonetim/yoreler/{id} -> RegionAdminController@update | auth, perm:category.manage
POST /yonetim/yoreler/{id}/sil -> RegionAdminController@destroy | auth, perm:category.manage, perm:product.delete
GET /yonetim/stok -> StockAdminController@index | auth, perm:stock.manage
GET /yonetim/stok/kritik -> StockAdminController@lowStock | auth, perm:stock.manage
POST /yonetim/stok/duzelt -> StockAdminController@adjust | auth, perm:stock.manage
GET /yonetim/stok/{variantId}/hareketler -> StockAdminController@history | auth, perm:stock.manage
Kayıt sırası: /olustur ve POST /kategoriler/sirala, {id} rotalarından ÖNCE tanımlanmalı (ya da {id} \d+ ile kısıtlanmalı). Stok listesi ?durum=kritik|stoksuz&q=; dashboard bağlantıları ?low=1 ve ?out=1 de desteklenir.

## (5) .env / settings anahtarları
Config: app.upload.max_mb (varsayılan 5), app.upload.allowed_mimes (liste; boşsa yalnız dahili beyaz liste). settings tablosu kullanılmadı (kritik eşik formda varsayılan 5; stock.default_low_threshold bağlanmadı). .env anahtarı eklenmedi. Yükleme klasörleri: public/uploads/products/YYYY/MM ve public/uploads/categories/YYYY/MM.

## SAPMA ve doğrulanacaklar
- Ürün kaydında stok hareketleri, ürün transaction'ı commit edildikten SONRA StockService::adjust ile uygulanır (adjust kendi transaction'ını açar); stok adımı başarısız olursa ürün kayıtlı kalır, DomainException mesajı flash edilir.
- Kategori/yöre silme için ayrı permission yok: category.manage + product.delete (manager silemez). regions'ta deleted_at yok: yöre silme kalıcıdır (ürün bağlıysa engel, audit'e yazılır).
- Ürün listesinde stok süzgeci ve 'trashed' arayüzü yok; stok süzme /yonetim/stok'ta, ürün listesinde yalnız "Kritik stok" bağlantısı var.
- Çoklu görsel yükleme istemcide ardışık tek dosya isteğiyle yapılır; admin-stock.js, controller pageScripts geçirmediği için stock/index.php sonunda <script src> ile yüklenir.
- Gerçek kodla doğrulanmadı: money(), errors(string), old(string,$default), ip_to_bin() yardımcıları; Request::post|query|file|json|wantsJson|ip|userAgent ve BaseController ok|fail|redirect|flash|view imzaları; ProductImage::create|find|forceDelete (BaseModel), Category::allFlat satırında parent_id anahtarı; orders için reference_type değerinin 'order' olması.