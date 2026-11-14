# 06 Model Sözleşmesi (TASLAK, bağlayıcı)
Modeller App\Models, BaseModel'den türer, INSTANCE metotlarla çalışır (new Product()). SQL yalnızca modellerde.
BaseModel: protected string $table; $primaryKey='id'; array $fillable; bool $softDeletes; array $sortable. find(int): ?array; findBy(string $col, mixed $v): ?array; all(array $where=[], ?string $orderBy=null, ?int $limit=null): array; paginate(int $page, int $perPage, array $where=[], string $orderBy='id DESC'): array{items,total,page,per_page,pages}; count(array $where=[]): int; create(array): int (yalnızca fillable); update(int,array): bool; softDelete(int): bool; restore(int): bool; forceDelete(int): bool. where: ['kolon'=>değer] veya ['kolon'=>['>=',5]] (operatörler: =,!=,>,>=,<,<=,LIKE,IN,IS NULL,IS NOT NULL). softDeletes=true ise okumalar deleted_at IS NULL filtreli. Kolon/sıralama adı beyaz liste, değerler parametre.
ÜRÜN LİSTE SATIRI anahtarları (Product::listActive/search/featured/related/adminList hepsi): id, name, slug, short_description, region_name, image_path, price, final_price, on_sale (bool), in_stock (bool), default_variant_id.
Product::findActiveBySlug(string): ?array => ürün sütunları + region_name + category{id,name,slug} + variants[] (ProductVariant satırları, final_price dahil) + images[] (ProductImage satırları).

## Kimlik
User (users, softDeletes): findByEmail(string): ?array; emailExists(string,?int $exceptId=null): bool; recordLogin(int $id, string $ipBin): void; incrementFailed(int): int; resetFailed(int): void; lockUntil(int,string $datetime): void; withRole(int): ?array; searchAdmin(array $filters,int $page,int $perPage): array.
Role: findByName(string): ?array; permissionCodes(int $roleId): array; syncPermissions(int $roleId, array $permissionIds): void; allWithUserCounts(): array.
Permission: allGroupedByModule(): array; idsByCodes(array): array.
Address (softDeletes): forUser(int): array; findForUser(int $id,int $userId): ?array; setDefault(int $id,int $userId): void; countForUser(int): int.
LoginAttempt (eklemeli): record(string $email,string $ipBin,?string $ua,bool $success): void; failedCountByEmail(string,int $minutes): int; failedCountByIp(string $ipBin,int $minutes): int; purgeOlderThan(int $days): int.
PasswordReset, EmailVerification (aynı imza): issue(int $userId,string $tokenHash,string $expiresAt,?string $ipBin): int; findValidByHash(string): ?array; markUsed(int): void; invalidateAllForUser(int): void.
## Katalog
Category (softDeletes): activeTree(): array (children iç içe); allFlat(): array; findBySlug(string): ?array; childrenOf(int): array; breadcrumb(int): array; slugExists(string,?int): bool; descendantIds(int): array.
Region: activeAll(): array; findBySlug(string): ?array.
Product (softDeletes): findActiveBySlug; listActive(array $filters,string $sort,int $page,int $perPage): array (filters: category_id[alt kategoriler dahil], region_id, min_price, max_price, in_stock, q; sort: newest|price_asc|price_desc|name; paginate biçimi, satırlar LİSTE SATIRI); search(string $q,int $limit): array; featured(int $limit): array; related(int $productId,int $limit): array; slugExists(string,?int): bool; adminList(array $filters,int $page,int $perPage): array.
ProductVariant (softDeletes): forProduct(int $productId, bool $onlyActive=true): array (final_price dahil); findActiveMany(array $ids): array (id=>satır, final_price dahil); lockManyForUpdate(array $ids): array; decrementStock(int $id,int $qty): bool (atomik); incrementStock(int $id,int $qty): void; lowStock(): array (v_low_stock); skuExists(string,?int): bool.
ProductImage: forProduct(int $productId): array; setPrimary(int $productId,int $imageId): void; reorder(array $idToOrder): void.
StockMovement (eklemeli): record(int $variantId,?int $userId,string $type,int $change,int $after,?string $refType,?int $refId,?string $note): int; forVariant(int,int $page,int $perPage): array; recent(int $limit): array.
## Sepet ve sipariş
Cart: findByUserId(int): ?array; findByGuestHash(string): ?array; createForUser(int): int; createForGuest(string $hash,string $expiresAt): int; touch(int,string $expiresAt): void; assignToUser(int $cartId,int $userId): void; deleteById(int): void; purgeExpired(): int.
CartItem: forCart(int $cartId): array (variant_id, product_id, product_name, slug, variant_name, sku, image_path, quantity, price, sale_price, sale_starts_at, sale_ends_at, final_price, stock_quantity, track_stock, weight_grams, tax_rate, is_active); upsertAdd(int,int $variantId,int $qty): void (toplam 99 sınırlı); setQuantity(int,int,int): void; remove(int,int): void; clear(int): void; countItems(int): int.
Coupon (softDeletes): findByCode(string): ?array; incrementUsedIfAvailable(int): bool (atomik); decrementUsed(int): void; adminList(array,int,int): array; codeExists(string,?int): bool.
CouponUsage: countByUser(int $couponId,int $userId): int; record(int,int,int $orderId,string $amount): int; deleteByOrder(int): void.
ShippingRate: priceForWeight(int $grams): ?string; activeAll(): array.
Order: createOrder(array): int; setOrderNumber(int,string): void; findByNumber(string): ?array; findForUser(string $orderNumber,int $userId): ?array; findByIdForUpdate(int): ?array; listForUser(int,int,int): array; adminList(array $filters,int,int): array (status, payment_method, payment_status, date_from, date_to, q); updateStatusFields(int,array): void; expiredPending(string $now,int $limit): array; countByStatus(): array; salesByDay(int $days): array.
OrderItem: insertMany(int,array): void; forOrder(int): array; topProducts(int $days,int $limit): array.
OrderStatusHistory (eklemeli): add(int,?string $from,string $to,?int $userId,?string $note): void; forOrder(int): array.
Payment: createPayment(array): int; findByMerchantOid(string,bool $forUpdate=false): ?array; latestForOrder(int): ?array; forOrder(int): array; markSuccess(int,array $extra): void; markFailed(int,?string $code,?string $msg): void; confirmManual(int,int $adminId,?string $note): void; pendingBankTransfers(int,int): array.
PaymentCallback (eklemeli): log(?string $oid,string $payloadJson,bool $hashValid,string $ipBin,?string $result): int; recent(int): array.
Shipment: forOrder(int): array; createShipment(array): int; updateShipment(int,array): bool; markDelivered(int): void.
## İçerik
Banner (softDeletes): activeByPosition(string): array (tarih penceresi+aktif+sıra); adminList(int,int): array.
Menu: findByLocation(string): ?array; allWithItemCounts(): array.
MenuItem: treeByMenu(int $menuId,bool $onlyActive=true): array (children iç içe; link_type'a göre url alanı: category=>/kategori/{slug}, page=>/sayfa/{slug}, region=>/yore/{slug}, products=>/urunler, url=>olduğu gibi); reorder(array): void; descendantIds(int): array.
Page (softDeletes): findActiveBySlug(string): ?array; findBySystemKey(string): ?array; footerPages(): array; slugExists(string,?int): bool.
Setting: get(string,mixed $default=null): mixed (tip dönüşümlü, istek içi önbellekli); getMany(array): array; set(string,mixed,?int $userId): void; allByGroup(): array; publicAll(): array.
Announcement: activeList(): array; adminList(int,int): array.
ContactMessage: createMessage(array): int; adminList(?string $status,int,int): array; markStatus(int,string,?int): void; countNew(): int.
AuditLog (eklemeli): write(?int $userId,string $action,?string $entityType,?int $entityId,?array $old,?array $new,?string $ipBin,?string $ua): void; search(array,int,int): array.
SecurityEvent (eklemeli): record(string $type,string $severity,?int $userId,?string $ipBin,array $detail=[]): void; search(array,int,int): array; countSince(string $type,int $minutes): int.