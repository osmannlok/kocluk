# 06a-model-sozlesme.md
Oturum 3a: Modeller (kimlik + katalog). Kaynak: yazılan GERÇEK kod. Hepsi `App\Models`, `final` (BaseModel hariç), INSTANCE metot; SQL `App\Core\Database::instance()` ile.

## (1) Dosya, sınıf, public metot imzaları
- app/Models/BaseModel.php (abstract): __construct(); find(int):?array; findBy(string $col,mixed $v):?array; all(array $where=[],?string $orderBy=null,?int $limit=null):array; paginate(int $page,int $perPage,array $where=[],string $orderBy='id DESC'):array{items,total,page,per_page,pages}; count(array $where=[]):int; create(array):int; update(int,array):bool; softDelete(int):bool; restore(int):bool; forceDelete(int):bool. Korumalı: $table,$primaryKey,$fillable,$softDeletes,$sortable,$appendOnly,$extraColumns,$db; paginateRaw(), inClause(), escapeLike() (kaçış '#'), likeSql(), clamp(), truncate(), positiveInts(), assertDateTime(), ipHex(), knownColumns(), bindable().
- app/Models/User.php (users, soft): findByEmail(string):?array; emailExists(string,?int $exceptId=null):bool; recordLogin(int,string $ipBin):void; incrementFailed(int):int; resetFailed(int):void; lockUntil(int,string $datetime):void; withRole(int):?array; searchAdmin(array $filters,int,int):array. Sınıf: kimlik doğrulama/kullanıcı erişimi.
- app/Models/Role.php (roles): findByName(string):?array; permissionCodes(int):array<string>; syncPermissions(int,array):void; allWithUserCounts():array.
- app/Models/Permission.php (permissions): allGroupedByModule():array<modül,list>; idsByCodes(array):array<kod,int>.
- app/Models/Address.php (addresses, soft): forUser(int):array; findForUser(int $id,int $userId):?array; setDefault(int $id,int $userId):void; countForUser(int):int.
- app/Models/LoginAttempt.php (login_attempts, eklemeli): record(string $email,string $ipBin,?string $ua,bool $success):void; failedCountByEmail(string,int $minutes):int; failedCountByIp(string $ipBin,int $minutes):int; purgeOlderThan(int $days):int.
- app/Models/PasswordReset.php (password_resets) ve app/Models/EmailVerification.php (email_verifications), aynı imza: issue(int $userId,string $tokenHash,string $expiresAt,?string $ipBin):int; findValidByHash(string):?array; markUsed(int):void; invalidateAllForUser(int):void.
- app/Models/Category.php (categories, soft): activeTree():array; allFlat():array; findBySlug(string):?array; childrenOf(int):array; breadcrumb(int):array; slugExists(string,?int):bool; descendantIds(int):array<int>.
- app/Models/Region.php (regions): activeAll():array; findBySlug(string):?array.
- app/Models/Product.php (products, soft): findActiveBySlug(string):?array; listActive(array $filters,string $sort,int $page,int $perPage):array; search(string $q,int $limit):array; featured(int):array; related(int $productId,int $limit):array; slugExists(string,?int):bool; adminList(array $filters,int,int):array.
- app/Models/ProductVariant.php (product_variants, soft): forProduct(int,bool $onlyActive=true):array; findActiveMany(array):array<id,satır>; lockManyForUpdate(array):array<id,satır>; decrementStock(int $id,int $qty):bool; incrementStock(int $id,int $qty):void; lowStock():array; skuExists(string,?int):bool; [EK] static finalPriceSql(string $alias='v'):string; static onSaleSql(string $alias='v'):string.
- app/Models/ProductImage.php (product_images): forProduct(int):array; setPrimary(int $productId,int $imageId):void; reorder(array $idToOrder):void.
- app/Models/StockMovement.php (stock_movements, eklemeli): record(int $variantId,?int $userId,string $type,int $change,int $after,?string $refType,?int $refId,?string $note):int; forVariant(int,int,int):array; recent(int):array.
- scripts/model_smoke.php: yalnızca CLI; okuma metotlarını çağırır, "OK Model::metot" / "HATA ..." yazar; çıkış 0/1; veri yazmaz.

## Davranış notları (gerçek koddan)
- where: ['kolon'=>değer|null] veya ['kolon'=>[op,değer]]; op: =,!=,>,>=,<,<=,LIKE,IN,IS NULL,IS NOT NULL. Kolon ^[a-z0-9_.]+$ + bilinen kolon listesi; "tablo.kolon" yalnızca kendi tablosu. IN boşsa 1=0. LIKE ESCAPE '#'. perPage<=100; sayfa üst sınırı yok (boş items döner); pages>=1.
- Eklemeli modeller (LoginAttempt, StockMovement): update/softDelete/restore/forceDelete => LogicException. softDeletes=false modelde softDelete/restore => LogicException. LoginAttempt::purgeOlderThan tek silme yoludur.
- İkili IP alanları UNHEX(hex) ile yazılır/karşılaştırılır; ipBin 4 veya 16 bayt olmalı (aksi InvalidArgumentException). Token özeti ^[a-f0-9]{64}$.
- Liste satırı (Product::listActive/search/featured/related/adminList): id,name,slug,short_description,region_name,image_path,price,final_price,on_sale(bool),in_stock(bool),default_variant_id. Varsayılan varyant: aktif+silinmemiş, is_default DESC,sort_order,id. Vitrin yalnızca aktif kategori + aktif varyantı olan aktif ürünleri listeler. adminList ek anahtarlar: is_active,is_featured,category_id,category_name,stock_total,created_at,updated_at,deleted_at.
- listActive filtreleri: category_id (alt kategoriler dahil), region_id, min_price, max_price, in_stock, q (>=2 karakter), [EK] on_sale. Geçersiz sort => newest. adminList filtreleri: q (ad/slug/sku), category_id, region_id, is_active, is_featured, trashed.
- Product::findActiveBySlug: p.* + region_name + category{id,name,slug} + variants[] (final_price, on_sale dahil) + images[]. Aktif kategori şartı vardır.
- ProductVariant satırları: tüm kolonlar + final_price + on_sale(bool). findActiveMany ek: product_name, tax_rate (aktif varyant + aktif ürün). lockManyForUpdate transaction dışında LogicException; id sırasıyla kilitler. decrementStock: tek UPDATE, `stock_quantity >= :qty`, etkilenen satır 1 ise true (track_stock'a bakmaz; çağıran karar verir).
- Category: findBySlug/childrenOf yalnızca is_active=1; activeTree üst kategorisi pasif olanları dışlar; derinlik sınırı 10.
- Region::findBySlug yalnızca aktif. User::withRole/searchAdmin password_hash ve last_login_ip DÖNDÜRMEZ; ek anahtarlar role_name, role_label. findByEmail/find tam satır (password_hash dahil) döner.
- Permission::idsByCodes kod=>id haritası döner (olmayan kod yok). Address::setDefault ve ProductImage::setPrimary sahibi/ürünü eşleşmezse sessizce hiçbir şey yapmaz. ProductImage::reorder ürün doğrulaması yapmaz (çağıran IDOR kontrolü yapmalı).
- StockMovement::forVariant ek: user_name; recent ek: user_name,variant_name,sku,product_name. type: initial,purchase,sale,cancel_return,manual_adjust,correction.
- fillable = 04 şemasındaki kolonlar (id,created_at,updated_at,deleted_at hariç); LoginAttempt için attempted_at dahil.

## SAPMA:
- Product::findActiveBySlug: created_by/updated_by anahtarları dönmez (personel kimliği sızmasın).
- Taslakta belirtilmeyen biçimler somutlaştırıldı: Permission::idsByCodes kod=>id; User::withRole/searchAdmin hassas kolonları çıkarır; Category/Region::findBySlug yalnızca aktif.
- BaseModel::paginate üst sayfa sınırı yok, perPage 1..100'e kırpılır.

## (2) Tablolar ve permission kodları
Tablolar: users, roles, role_permissions, permissions, addresses, login_attempts, password_resets, email_verifications, categories, regions, products, product_variants, product_images, stock_movements; VIEW v_low_stock. Permission kodu kullanılmaz (modeller yetki kontrolü yapmaz).

## (3) JSON uç noktaları
YOK (bu oturumda controller/route yazılmadı).

## (4) ROUTE SATIRLARI
YOK.

## (5) .env / settings anahtarları
YOK (bağlantı ayarları yalnızca App\Core\Database/config üzerinden). settings tablosu kullanılmadı.