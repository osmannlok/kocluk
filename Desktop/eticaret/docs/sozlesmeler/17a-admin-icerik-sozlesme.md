# 17a-admin-icerik-sozlesme.md
Oturum 14a: Admin kupon, banner, duyuru, menü. Kaynak: yazılan GERÇEK kod. Tüm controller'lar `final`, `AdminBaseController`'dan türer. Modeller instance çağrılır. İşlemler `$this->audit()` ile audit_logs'a yazılır.

## (1) Dosya, sınıf, public metot imzaları
- app/Controllers/Admin/CouponAdminController.php | CouponAdminController | index(Request): Response | create(Request): Response (?uret=1 sunucuda rastgele kod üretir) | store(Request): Response | edit(Request, int $id): Response | update(Request, int $id): Response | toggle(Request, int $id): Response | destroy(Request, int $id): Response. Görev: kupon CRUD, kullanım istatistiği (used_count, kayıt sayısı, toplam indirim), soft delete.
- app/Controllers/Admin/BannerAdminController.php | BannerAdminController | index(Request) | create(Request) | store(Request) | edit(Request, int $id) | update(Request, int $id) | toggle(Request, int $id) | reorder(Request): Response (JSON) | destroy(Request, int $id). Hepsi Response döner. Görev: banner CRUD, ImageUploadService ile görsel, oran uyarısı, soft delete (dosyalar silinmez).
- app/Controllers/Admin/AnnouncementAdminController.php | AnnouncementAdminController | index(Request) (liste + form; düzenleme ?duzenle={id}) | store(Request) | update(Request, int $id) | toggle(Request, int $id) | destroy(Request, int $id). Hepsi Response döner. Görev: duyuru CRUD (silme KALICI).
- app/Controllers/Admin/MenuAdminController.php | MenuAdminController | index(Request) | show(Request, int $id) | storeItem(Request, int $id) | updateItem(Request, int $id, int $itemId) | toggleItem(Request, int $id, int $itemId) | destroyItem(Request, int $id, int $itemId) | reorder(Request, int $id): Response (JSON). Görev: menü ağacı yönetimi (silme KALICI, alt öğeler FK CASCADE).
- resources/views/admin/coupons/index.php: $title,$coupons,$pager,$q,$status,$now. form.php: $title,$coupon,$isEdit,$stats,$formAction.
- resources/views/admin/banners/index.php: $title,$groups(konum=>satırlar),$positions,$now. form.php: $title,$banner,$isEdit,$positions,$formAction (multipart; image_desktop, image_mobile, remove_mobile).
- resources/views/admin/announcements/index.php: $title,$announcements,$pager,$announcement,$isEdit,$formAction,$now.
- resources/views/admin/menus/index.php: $title,$menus. edit.php: $title,$menu,$tree,$linkTypes,$maxDepth,$categories,$pages,$regions,$reorderUrl,$pageScripts.
- public/assets/js/admin-menu.js: IIFE (window.Admin.fetchJson/toast gerekir). Öznitelikler: [data-menu-tree][data-reorder-url][data-max-depth], li[data-item-id], [data-menu-row], ul[data-menu-list], [data-menu-move=up|down|indent|outdent], [data-drag-handle], select[data-link-type], [data-target-group], [data-menu-status]. Sürükle-bırak ve klavye düğmeleri; hata olursa DOM eski yerine döner.
- Kullanılan servis çağrıları: ImageUploadService::store(array $file, string $subdir, array $opts): array{path,width,height} (opts: max_width 1920 masaüstü / 900 mobil, thumb=false; subdir 'banners') ve delete(string $path): void. Controller dosya sistemine dokunmaz.

## Davranış notları
- Kupon: kod [A-Z0-9_-] 3-40 ve benzersiz (codeExists); percent 1-100; fixed > 0; max_discount_amount yalnızca percent; limitler >= 1; toplam limit used_count'tan küçük olamaz; ends_at > starts_at. Tutarlar Money ile kuruşa çevrilip "1234.50" string yazılır.
- Banner: link_url yalnızca "/yol" (// değil) veya https://; javascript:, data:, http:// reddedilir. Masaüstü görseli yeni kayıtta zorunlu. Oran sapması %15'in üzerindeyse flash('warning') verir, kaydı engellemez. Yeni görsel DB yazımından ÖNCE yüklenir, DB hatasında yeni dosya silinir; eski dosya DB başarılı olduktan sonra silinir. Konum değişince sıra sona alınır.
- Banner reorder: liste o konumdaki tüm silinmemiş bannerları birebir içermeli, aksi halde 422.
- Duyuru: link_url için banner ile aynı kural; sort_order 0-9999.
- Menü: link_type url|category|page|region|products; hedef varlığı kontrol edilir (kategori/yöre aktif, sayfa aktif); products için link_value NULL. Öğeler menü kimliğiyle eşleştirilir (başka menünün öğesi 404). En çok 3 seviye, döngü engeli, liste menünün tüm öğelerini içermeli. Sıra her üst öğe altında 1'den yeniden numaralanır. Düzenleme parent_id/sıra değiştirmez.
- Doğrulama hatası ValidationException (errors+old); form alanı hata adı = alan adı. Görsel hatası ilgili alan anahtarıyla (image_desktop/image_mobile).

## (2) Tablolar ve permission kodları
- Tablolar (yalnızca model üzerinden): coupons, coupon_usages (okuma), banners, announcements, menus, menu_items, categories, pages, regions (okuma), audit_logs (yazma), security_events (permission_denied).
- Permission: coupon.manage (kupon) | banner.manage (banner VE duyuru) | menu.manage (menü). Her metot ayrıca require() çağırır.

## (3) JSON uç noktaları (başlık X-CSRF-Token; hata: {"ok":false,"error":{code,message,errors}})
- POST /yonetim/bannerlar/sirala | istek {"position":"home_hero|home_secondary|category_top","ids":[int,...]} | yanıt {"ok":true,"data":{"count":int}} | 422 validation (position/ids)
- POST /yonetim/menuler/{id}/sirala | istek {"items":[{"id":int,"parent_id":int|null},...]} (görsel sırayla düz liste) | yanıt {"ok":true,"data":{"count":int}} | 422 validation (items: boş, güncel değil, döngü, seviye sınırı) | 404 menü yok
- Diğer uçlar form POST (HTML), başarıda 302 listeye + flash success, hatada geri + flash errors/old.

## (4) ROUTE SATIRLARI (routes/admin.php, prefix /yonetim; /yeni ve /sirala {id}'li rotalardan ÖNCE tanımlanmalı)
GET /yonetim/kuponlar -> CouponAdminController@index | auth, perm:coupon.manage
GET /yonetim/kuponlar/yeni -> CouponAdminController@create | auth, perm:coupon.manage
POST /yonetim/kuponlar -> CouponAdminController@store | auth, perm:coupon.manage
GET /yonetim/kuponlar/{id}/duzenle -> CouponAdminController@edit | auth, perm:coupon.manage
POST /yonetim/kuponlar/{id}/guncelle -> CouponAdminController@update | auth, perm:coupon.manage
POST /yonetim/kuponlar/{id}/durum -> CouponAdminController@toggle | auth, perm:coupon.manage
POST /yonetim/kuponlar/{id}/sil -> CouponAdminController@destroy | auth, perm:coupon.manage
GET /yonetim/bannerlar -> BannerAdminController@index | auth, perm:banner.manage
GET /yonetim/bannerlar/yeni -> BannerAdminController@create | auth, perm:banner.manage
POST /yonetim/bannerlar -> BannerAdminController@store | auth, perm:banner.manage
POST /yonetim/bannerlar/sirala -> BannerAdminController@reorder | auth, perm:banner.manage
GET /yonetim/bannerlar/{id}/duzenle -> BannerAdminController@edit | auth, perm:banner.manage
POST /yonetim/bannerlar/{id}/guncelle -> BannerAdminController@update | auth, perm:banner.manage
POST /yonetim/bannerlar/{id}/durum -> BannerAdminController@toggle | auth, perm:banner.manage
POST /yonetim/bannerlar/{id}/sil -> BannerAdminController@destroy | auth, perm:banner.manage
GET /yonetim/duyurular -> AnnouncementAdminController@index | auth, perm:banner.manage
POST /yonetim/duyurular -> AnnouncementAdminController@store | auth, perm:banner.manage
POST /yonetim/duyurular/{id}/guncelle -> AnnouncementAdminController@update | auth, perm:banner.manage
POST /yonetim/duyurular/{id}/durum -> AnnouncementAdminController@toggle | auth, perm:banner.manage
POST /yonetim/duyurular/{id}/sil -> AnnouncementAdminController@destroy | auth, perm:banner.manage
GET /yonetim/menuler -> MenuAdminController@index | auth, perm:menu.manage
GET /yonetim/menuler/{id} -> MenuAdminController@show | auth, perm:menu.manage
POST /yonetim/menuler/{id}/sirala -> MenuAdminController@reorder | auth, perm:menu.manage
POST /yonetim/menuler/{id}/ogeler -> MenuAdminController@storeItem | auth, perm:menu.manage
POST /yonetim/menuler/{id}/ogeler/{itemId:\d+}/guncelle -> MenuAdminController@updateItem | auth, perm:menu.manage
POST /yonetim/menuler/{id}/ogeler/{itemId:\d+}/durum -> MenuAdminController@toggleItem | auth, perm:menu.manage
POST /yonetim/menuler/{id}/ogeler/{itemId:\d+}/sil -> MenuAdminController@destroyItem | auth, perm:menu.manage

## (5) .env / settings anahtarları
- .env/settings: YOK. ImageUploadService içeride config app.upload.max_mb ve app.upload.allowed_mimes okur.

## SAPMA / açık noktalar
- announcements ve menu_items tablolarında deleted_at yok: silme forceDelete (kalıcı); silinen kaydın özeti audit_logs'ta saklanır.
- banners/index.php sıra düğmeleri [data-banner-move] ve banners/form.php önizleme [data-image-input][data-preview-target] için JS YAZILMADI; controller index/create/edit'e pageScripts verilmedi. Gerekirse ayrı dosya (admin-banner.js) istenmeli.
- Menu::allWithItemCounts() öğe sayısı anahtarı sözleşmede yok; menus/index.php sırayla item_count, items_count, count dener.
- Page::all(['is_active'=>1], 'id ASC') ve CouponUsage::all(['coupon_id'=>id]) BaseModel::all üzerinden çağrıldı; sıralama/kolon beyaz listesi varsayımı doğrulanmalı.
- Coupon/Banner/Announcement/MenuItem::create için fillable listesi şema kolonlarıyla aynı varsayıldı (created_by dahil).
- Duyuru yönetimi için ayrı permission kodu yok (banner.manage).