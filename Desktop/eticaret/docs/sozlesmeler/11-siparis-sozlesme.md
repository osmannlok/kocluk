# 11-siparis-sozlesme.md
Oturum 8: Sipariş, stok, checkout. Kaynak: yazılan GERÇEK kod. Servisler `new` ile üretilir; AuditService statik; MailService örnek.

## (1) Dosya, sınıf, public metot imzaları
- app/Services/InsufficientStockException.php: final extends RuntimeException | __construct(int $variantId, string $message='') | getVariantId(): int. Görev: stok yetersizliği, varyantı taşır.
- app/Services/StockService.php: final | __construct() | reserve(array $qtyByVariantId, string $refType, int $refId, ?int $userId): void | release(array $qtyByVariantId, string $refType, int $refId, ?int $userId): void | adjust(int $variantId, int $delta, string $type, string $note, int $adminId): void. Görev: stok düşme/iade (çağıran transaction içinde; track_stock=0 atlanır) ve elle düzeltme (kendi transaction'ı; not zorunlu; eksiye düşmez; AuditService 'stock.adjust'; hata InvalidArgumentException/DomainException).
- app/Services/OrderStatusService.php: final | __construct() | canTransition(string $from, string $to): bool | allowedNext(string $from): array | transition(int $orderId, string $to, ?int $userId, ?string $note=null): void. Görev: durum makinesi, tek transaction; geçersiz geçişte DomainException; e-posta göndermez (çağıran OrderNotificationService'i çağırır).
- app/Services/OrderService.php: final | __construct(?PricingService=null, ?CartService=null, ?StockService=null, ?OrderNotificationService=null) | createFromCart(int $userId, array $checkout): array{order_id:int,order_number:string,payment_method:string} | issueNonce(): string | consumeNonce(string $nonce): bool. Görev: sepetten sipariş (tek transaction; bildirim transaction dışı). Hatalar: DomainException (iş kuralı, Türkçe mesaj), InsufficientStockException, ValidationException (billing_* anahtarları).
- app/Services/OrderNotificationService.php: final | __construct() | sendCreated(int $orderId): void | sendPaid(int $orderId): void | sendShipped(int $orderId, int $shipmentId): void | sendCancelled(int $orderId): void | [EK] paymentInstructions(array $order): array. Görev: sipariş e-postaları; hata yalnızca Logger::warning.
- app/Services/AddressService.php: final | __construct() | create(int $userId, array $input): int | update(int $id, int $userId, array $input): bool | delete(int $id, int $userId): bool | setDefault(int $id, int $userId): void (yoksa HttpException 404) | [EK] present(array $address): array (TC maskeli). Görev: adres doğrulama + sahiplik + en çok 10 adres.
- app/Controllers/Front/AddressController.php: final extends BaseController | list(Request): Response | store(Request): Response | update(Request, int $id): Response | destroy(Request, int $id): Response | makeDefault(Request, int $id): Response. Görev: adres JSON uçları.
- app/Controllers/Front/CheckoutController.php: final extends BaseController | index(Request): Response | summary(Request): Response | place(Request): Response | complete(Request, string $orderNumber): Response. Görev: checkout akışı.
- resources/views/emails/order-created.php: girdi $name,$order,$items,$paymentInstructions. resources/views/front/checkout/index.php: girdi $addresses,$selectedAddressId,$paymentMethods,$selectedMethod,$summary,$nonce,$codFee,$legalLinks. resources/views/front/checkout/complete.php: girdi $order,$items,$shipping,$paymentInstructions. resources/views/partials/address-modal.php: girdi yok (#ymAddressModal, #ymAddressForm).
- public/assets/js/checkout.js: IIFE (window.App gerekir); $scripts=['checkout.js'] ile yüklenir. Olay: document 'address:saved' {detail:{id}}. Öznitelikler: [data-address-new], [data-address-edit][data-address=JSON], [data-copy], [data-step-next], [data-place-order], [data-sum], [data-sum-row].
- scripts/cancel_expired_orders.php: yalnızca CLI; flock ile çakışma korumalı; çıkış 0/1. Cron: */5 * * * * /usr/bin/php /home/osman/Desktop/eticaret/scripts/cancel_expired_orders.php >> storage/logs/cron.log 2>&1

## Geçiş tablosu ve davranış
- pending_payment→paid|cancelled | paid→preparing|cancelled|refunded | preparing→shipped|cancelled | shipped→delivered | delivered→refunded | cancelled/refunded terminal.
- paid: payment_status=paid, paid_at | shipped/delivered: shipped_at/delivered_at | refunded: payment_status=refunded (gerçek iade PayTR panelinden) | cancelled: cancelled_at, cancel_reason=not, payment_status pending ise failed + açık payments failed, stok iadesi (reference_type 'order', type cancel_return), CouponUsage::deleteByOrder + Coupon::decrementUsed.
- createFromCart sırası: e-posta doğrulandı mı → PricingService → Address::findForUser → Order::createOrder + setOrderNumber 'YM-yıl-6 hane id' + OrderItem::insertMany → StockService::reserve('order', type sale) → kupon → OrderStatusHistory → Payment::createPayment → sepet+kupon temizliği. card/bank=pending_payment, cod=preparing; payment_status=pending; expires_at: kart order.card_expiry_minutes, havale order.bank_expiry_hours, kapıda NULL. Payment: provider paytr|bank_transfer|cod; kart merchant_oid 'YM'.id.'T'.time().hex8, status initiated; diğerleri pending.
- $checkout: shipping_address_id:int, payment_method, customer_note:?string, billing{type,tc_identity_no,company_name,tax_office,tax_number}, consents{distance_sales:bool,kvkk:bool,ip:string,user_agent:?string}. orders.billing_address JSON = teslimat anlık görüntüsü + fatura alanları.
- paymentInstructions: method, order_number, amount, deadline(?string); bank_transfer ek: bank_name, account_holder, iban, note; cod ek: cod_fee; credit_card ek: pay_url.
- SAPMA: createOrder, reserve'den ÖNCE çalışır (stok hareketi sipariş id ister); hepsi tek transaction. AddressService::present, OrderNotificationService::paymentInstructions ek public metotlardır. Eksiye düşmeme ve track_stock=0 için adjust DomainException atar.

## (2) Tablolar ve permission kodları
- Tablolar (yalnızca model üzerinden): orders, order_items, order_status_history, payments, coupons, coupon_usages, product_variants, stock_movements, addresses, users, carts/cart_items (CartService), shipments (okuma), pages, settings, audit_logs.
- Permission: YOK (yalnızca auth). Stok düzeltme/durum geçişi çağıran admin controller'ında stock.manage / order.update_status / order.cancel ile korunmalı.

## (3) JSON uç noktaları (başlık X-CSRF-Token; hata: {"ok":false,"error":{code,message,errors}})
- GET /hesabim/adresler/liste -> {"ok":true,"data":{"addresses":[{id,title,full_name,phone,city,district,neighborhood,address_line,postal_code,is_default,invoice_type,tc_identity_no(maskeli),company_name,tax_office,tax_number}]}}
- POST /hesabim/adresler (JSON: title,full_name,phone,city,district,neighborhood?,address_line,postal_code?,invoice_type,tc_identity_no?,company_name?,tax_office?,tax_number?,is_default?) -> {"ok":true,"data":{"id":int,"address":{...}}} | 422 validation (errors alan=>mesaj; limit için errors.address)
- POST /hesabim/adresler/{id}/guncelle (aynı gövde; maskeli TC korunur) -> {"ok":true,"data":{"id","address"}} | 404 not_found
- POST /hesabim/adresler/{id}/sil -> {"ok":true,"data":{"id":int}} | 404 not_found
- POST /hesabim/adresler/{id}/varsayilan -> {"ok":true,"data":{"id":int}} | 404
- POST /odeme/ozet (JSON {"payment_method":"credit_card|bank_transfer|cod"}) -> {"ok":true,"data":{payment_method,subtotal,discount_total,coupon{code,message},shipping_total,shipping_is_free,cod_fee,grand_total,tax_total,free_shipping{threshold,remaining,percent,is_free},warnings[],blocking_errors[]}} | 422 validation | 422 cart_empty
- POST /odeme/siparis-ver: JSON DEĞİL, form (HTML). Alanlar: _token, nonce, shipping_address_id, billing_type, billing_tc_identity_no, billing_company_name, billing_tax_office, billing_tax_number, payment_method, customer_note, accept_distance_sales=1, accept_kvkk=1. Başarı: 302 -> /odeme/kart/{orderNumber} (credit_card) veya /siparis/tamamlandi/{orderNumber}. Hata: stok -> /sepet (flash warning); DomainException -> /odeme (flash error); doğrulama -> geri + flash errors/old.
- Sayfalar: GET /odeme (front.checkout.index), GET /siparis/tamamlandi/{orderNumber} (front.checkout.complete; sahibi değilse 404).

## (4) ROUTE SATIRLARI
GET /odeme -> CheckoutController@index | auth
POST /odeme/ozet -> CheckoutController@summary | auth
POST /odeme/siparis-ver -> CheckoutController@place | auth, throttle:10,60
GET /siparis/tamamlandi/{orderNumber} -> CheckoutController@complete | auth
GET /hesabim/adresler/liste -> AddressController@list | auth
POST /hesabim/adresler -> AddressController@store | auth
POST /hesabim/adresler/{id}/guncelle -> AddressController@update | auth
POST /hesabim/adresler/{id}/sil -> AddressController@destroy | auth
POST /hesabim/adresler/{id}/varsayilan -> AddressController@makeDefault | auth
(Controller: App\Controllers\Front\*. "auth" = global secure_headers+csrf üstüne. Rota sırası: /hesabim/adresler/liste, {id} rotalarından önce tanımlanmalı.)

## (5) .env / settings anahtarları
- .env: yok (APP_KEY CartService içinde kullanılır).
- settings (okunan): card.enabled, bank_transfer.enabled, bank_transfer.bank_name, bank_transfer.account_holder, bank_transfer.iban, bank_transfer.note, cod.enabled, cod.fee, order.card_expiry_minutes, order.bank_expiry_hours (+ PricingService'in okuduğu shipping.free_threshold, order.min_amount).
- Oturum anahtarı: checkout_nonces (en çok 5 tek kullanımlık nonce). Flash türleri kullanıldı: warning, error.
- Bağımlılıklar: pages.system_key 'distance_sales' ve 'kvkk' kayıtları (onay bağlantıları), e-posta şablonları order-paid / order-shipped / order-cancelled henüz yok (send* false döner, Logger::warning yazılır; Oturum 9 ve 13).