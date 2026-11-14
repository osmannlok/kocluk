# 06b-model-sozlesme.md
Oturum 3b: Modeller (sepet, sipariş, içerik, log). Kaynak: yazılan GERÇEK kod. Hepsi App\Models, BaseModel'den türer, instance metot (SecurityEvent::record hariç: static). DB erişimi Database::instance(). Sayfalı dönüş "SAYFALI" = {items,total,page,per_page,pages}. IP alanları ham bayt (VARBINARY).
Not: Order/Payment/Shipment $fillable=[] (mass assignment kapalı; yazma açık alan listeleriyle). Eklemeli ($appendOnly=true): OrderStatusHistory, PaymentCallback, AuditLog, SecurityEvent. softDeletes: Coupon, Banner, Page.

## (1) Dosya, sınıf, public metot imzaları
- app/Models/Cart.php Cart (carts): findByUserId(int): ?array | findByGuestHash(string): ?array | createForUser(int): int | createForGuest(string $hash, string $expiresAt): int | touch(int, string): void | assignToUser(int $cartId, int $userId): void | deleteById(int): void | purgeExpired(): int. Görev: sepet başlığı.
- app/Models/CartItem.php CartItem (cart_items): forCart(int): array (final_price dahil, silinmiş varyant hariç, is_active=varyant+ürün) | upsertAdd(int,int,int): void (toplam 99 sınırlı, atomik) | setQuantity(int,int,int): void (1..99) | remove(int,int): void | clear(int): void | countItems(int): int (adet toplamı). Görev: sepet kalemleri.
- app/Models/Coupon.php Coupon: findByCode(string): ?array | incrementUsedIfAvailable(int): bool (atomik) | decrementUsed(int): void | adminList(array $filters{q,is_active}, int, int): array SAYFALI | codeExists(string, ?int): bool (silinmişler dahil). Görev: kupon.
- app/Models/CouponUsage.php CouponUsage: countByUser(int,int): int | record(int $couponId, int $userId, int $orderId, string $amount): int | deleteByOrder(int): void. Görev: kupon kullanım kaydı.
- app/Models/ShippingRate.php ShippingRate: priceForWeight(int): ?string (örtüşmede en yüksek min kazanır) | activeAll(): array. Görev: kargo tarifesi.
- app/Models/Order.php Order: createOrder(array): int (açık alan listesi, tutar string, consent_ip zorunlu ham bayt, adres dizi/JSON metin) | setOrderNumber(int,string): void | findByNumber(string): ?array | findForUser(string $orderNumber, int $userId): ?array | findByIdForUpdate(int): ?array (transaction içinde) | listForUser(int,int,int): array SAYFALI (item_count dahil) | adminList(array,int,int): array SAYFALI (status,payment_method,payment_status,date_from,date_to,q; first_name,last_name,email dahil) | updateStatusFields(int, array): void (beyaz liste: status,payment_status,paid_at,shipped_at,delivered_at,cancelled_at,cancel_reason,expires_at) | expiredPending(string $now, int $limit): array | countByStatus(): array (durum=>adet, hepsi) | salesByDay(int): array (sale_date,order_count,total). Görev: sipariş erişimi.
- app/Models/OrderItem.php OrderItem: insertMany(int, array): void (transaction) | forOrder(int): array | topProducts(int $days, int $limit): array (variant_id,sku,product_name,variant_name,total_quantity,total_revenue). Görev: sipariş kalemi anlık görüntüsü.
- app/Models/OrderStatusHistory.php OrderStatusHistory: add(int, ?string $from, string $to, ?int $userId, ?string $note): void | forOrder(int): array (changed_by_name dahil). Görev: durum geçmişi.
- app/Models/Payment.php Payment: createPayment(array): int | findByMerchantOid(string, bool $forUpdate=false): ?array | latestForOrder(int): ?array | forOrder(int): array | markSuccess(int, array $extra): void (extra: payment_type,installment_count,note,callback_received_at; refunded'a dokunmaz) | markFailed(int, ?string, ?string): void (yalnızca initiated/pending) | confirmManual(int, int $adminId, ?string): void (yalnızca initiated/pending) | pendingBankTransfers(int $page, int $perPage): array SAYFALI (order_number,grand_total,müşteri alanları dahil). Görev: ödeme kayıtları.
- app/Models/PaymentCallback.php PaymentCallback: log(?string $oid, string $payloadJson, bool $hashValid, string $ipBin, ?string $result): int | recent(int): array. Görev: PayTR bildirim günlüğü.
- app/Models/Shipment.php Shipment: forOrder(int): array | createShipment(array): int (tracking_url yalnızca http/https, aksi InvalidArgumentException) | updateShipment(int, array): bool | markDelivered(int): void. Görev: kargo kayıtları.
- app/Models/Banner.php Banner: activeByPosition(string): array | adminList(int,int): array SAYFALI. Görev: banner.
- app/Models/Menu.php Menu: findByLocation(string): ?array (yalnızca aktif) | allWithItemCounts(): array. Görev: menü başlığı.
- app/Models/MenuItem.php MenuItem: treeByMenu(int, bool $onlyActive=true): array (url+children; güvensiz url '#') | reorder(array $idToOrder): void | descendantIds(int): array (int listesi). Görev: menü ağacı.
- app/Models/Page.php Page: findActiveBySlug(string): ?array | findBySystemKey(string): ?array (pasif olsa da döner) | footerPages(): array | slugExists(string, ?int): bool (silinmişler dahil). Görev: CMS sayfası.
- app/Models/Setting.php Setting (PK key_name): get(string, mixed $default=null): mixed | getMany(array): array | set(string, mixed, ?int $userId): void (olmayan anahtarda InvalidArgumentException, tipe göre doğrular) | allByGroup(): array (ham, gizliler dahil: yalnızca yönetim) | publicAll(): array (is_public=1, tipli). decimal string kalır. İstek içi static önbellek.
- app/Models/Announcement.php Announcement: activeList(): array | adminList(int,int): array SAYFALI. Görev: duyuru şeridi.
- app/Models/ContactMessage.php ContactMessage: createMessage(array): int (ip_address ham bayt zorunlu, durum 'new') | adminList(?string $status, int, int): array SAYFALI | markStatus(int, string, ?int): void | countNew(): int. Görev: iletişim mesajı.
- app/Models/AuditLog.php AuditLog: write(?int $userId, string $action, ?string $entityType, ?int $entityId, ?array $old, ?array $new, ?string $ipBin, ?string $ua): void (parola/token/tc_identity/kart alanları '***') | search(array{user_id,action(önek),entity_type,entity_id,date_from,date_to}, int, int): array SAYFALI (user_email,user_name dahil). Görev: denetim günlüğü.
- app/Models/SecurityEvent.php SecurityEvent (final): STATIC record(string $type, string $severity, ?int $userId, ?string $ip, array $detail=[]): void (ip metin VEYA ham bayt; hata yutulur, loglanır) | search(array{type,severity,user_id,date_from,date_to}, int, int): array SAYFALI (user_email dahil) | countSince(string, int $minutes): int. 05b'deki static imza korunur.
- scripts/model_smoke_b.php: yalnızca CLI, 3b modellerinden salt okunur çağrılar; "OK Model::metot" / "FAIL ..."; hata varsa çıkış kodu 1.

## (2) Tablolar ve permission kodları
Tablolar: carts, cart_items, coupons, coupon_usages, shipping_rates, orders, order_items, order_status_history, payments, payment_callbacks, shipments, banners, menus, menu_items, pages, settings, announcements, contact_messages, audit_logs, security_events. Okuma amaçlı JOIN: users, products, product_variants, product_images.
Permission kodu: model katmanında kullanılmaz (kontrol Controller/middleware).

## (3) JSON uç noktaları
YOK (bu oturumda HTTP/JSON uç noktası yazılmadı). Modeller doğrulama hatasında InvalidArgumentException fırlatır; servis katmanı bunu 422'ye çevirmelidir.

## (4) ROUTE SATIRLARI
YOK (route/Controller yazılmadı). menu_items URL çözümü: category=>/kategori/{slug}, page=>/sayfa/{slug}, region=>/yore/{slug}, products=>/urunler, url=>olduğu gibi (yalnızca /yol veya http(s); diğerleri '#').

## (5) .env / settings anahtarları
.env: yok. Setting modeli anahtar adını sabitlemez; çağıranlar 04-sema-ozeti'ndeki anahtarları kullanır (örn. shipping.free_threshold, cod.fee, order.card_expiry_minutes, order.bank_expiry_hours).

## Servis katmanı için notlar
- Tutarlar string "1234.50"; float verilirse InvalidArgumentException. Hesap App\Helpers\Money ile serviste.
- Cart::assignToUser öncesi kullanıcının mevcut sepeti birleştirilip silinmeli (UNIQUE user_id).
- Findbyidforupdate/findByMerchantOid(...,true) yalnızca Database::transaction içinde kullanılmalı.
- Order::expiredPending sonrası iptal + stok iadesi + CouponUsage::deleteByOrder + Coupon::decrementUsed serviste, tek transaction'da.
- Setting::allByGroup gizli (IBAN vb.) ayarları da içerir; vitrin için publicAll kullanın.
- Birleştirme: cat 06a-model-sozlesme.md 06b-model-sozlesme.md > 06-model-sozlesme.md
UserSession (sessions): deleteByUser(int $userId, ?string $exceptSessionId=null): int (exceptSessionId = Session::id() ham değeri; metot içeride DbSessionHandler::storageId() ile özete çevirip karşılaştırır, ham dalı da hariç tutar); countByUser(int $userId): int. NOT: sessions.id, oturum kimliğinin SHA-256 özetidir, ham kimlik DEĞİL.