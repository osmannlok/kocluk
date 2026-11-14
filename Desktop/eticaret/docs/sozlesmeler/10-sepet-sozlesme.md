# 10-sepet-sozlesme.md
Oturum 7: Sepet, fiyat, kupon. Kaynak: yazılan GERÇEK kod. Modeller instance çağrılır. Para: iç hesap tamsayı kuruş, dış "x.xx" string.

## (1) Dosya, sınıf, public metot imzaları
- app/Services/ShippingCalculator.php: final ShippingCalculator | __construct() | calculate(int $totalWeightGrams, int $discountedSubtotalCents): array{fee_cents:int,is_free:bool,threshold_cents:int,remaining_cents:int,label:string}. Eşik shipping.free_threshold (<=0 ise özellik kapalı); tarife yoksa en yüksek ağırlık bandı.
- app/Services/CouponService.php: final CouponService | __construct() | validate(string $code, int $subtotalCents, ?int $userId): array{valid:bool,coupon?:array,discount_cents?:int,message:string}. Sayaç artırmaz. Kod biçimi ^[A-Z0-9_-]{3,40}$.
- app/Services/PricingService.php: final PricingService | const MAX_LINE_QUANTITY=20 | __construct(?CouponService=null, ?ShippingCalculator=null) | calculate(array $cartLines, ?string $couponCode, ?string $paymentMethod, ?int $userId): array. TEK fiyat kaynağı.
- app/Services/CartService.php: final CartService | const COOKIE_NAME='ym_cart' | currentCartId(bool $create=false): ?int | add(int $variantId, int $qty): array{ok:bool,message:string,count:int} | setQuantity(int $variantId, int $qty): array{ok,message,count} (qty<=0 = sil) | remove(int $variantId): array{ok,message,count} | clear(): void | lines(): array | count(): int | couponCode(): ?string | applyCoupon(string $code): array{ok:bool,message:string} | removeCoupon(): void. Tembel misafir birleştirme.
- app/Controllers/Front/CartController.php: final CartController extends BaseController | index(Request): Response | summary(Request): Response | add(Request): Response | update(Request): Response | remove(Request): Response | applyCoupon(Request): Response | removeCoupon(Request): Response. Servisler metot içinde new ile üretilir (constructor yok).
- resources/views/front/cart/index.php: girdi $summary, $checkoutHref; veri data-initial (JSON, e() ile); şablon #ymCartLineTpl.
- resources/views/partials/mini-cart.php: girdi yok; #ymCartOffcanvas, şablon #ymMiniCartItemTpl. Header'ın include etmesi gerekir.
- public/assets/js/cart.js: IIFE (window.App gerekir). Sayfa JS'i: controller $scripts=['cart.js'] verir; mini-cart için layout'ta global yüklenmeli.

## PricingService::calculate çıktı şeması
- lines[]: variant_id:int, product_name, variant_name, sku, quantity:int, list_price:"x.xx", unit_price:"x.xx", line_total:"x.xx", tax_rate:"10.00", weight_grams:int (birim), available:bool, available_qty:int (min(stok,20); stok takipsizse 20)
- subtotal "x.xx" (yalnızca available satırlar) | discount_total | coupon{code:string|null, message:string} | shipping_total | shipping_is_free:bool | free_shipping_remaining | [EK] free_shipping_threshold | cod_fee (method=cod ve cod.enabled ise cod.fee, aksi 0.00) | grand_total = subtotal-discount+shipping+cod_fee | tax_total (KDV dahilden ayrıştırılmış, indirim oranında düşülmüş, bilgi amaçlı) | total_weight_grams:int | warnings:string[] (geçersiz kupon) | blocking_errors:string[]
- blocking_errors: satış dışı/stoksuz satır, adet>stok, satın alınabilir ürün yok, order.min_amount altı, kapalı/geçersiz ödeme yöntemi. Geçerli method: credit_card|bank_transfer|cod.

## (2) Tablolar ve permission kodları
- Tablolar (yalnızca model üzerinden): carts, cart_items, product_variants, products, product_images (forCart JOIN), coupons, coupon_usages, shipping_rates, settings.
- Permission kodu: YOK (vitrin uçları herkese açık).

## (3) JSON uç noktaları
Tüm istekler JSON gövde (veya form), başlık X-CSRF-Token. Başarı: {"ok":true,"data":SUMMARY}; mutasyonlarda data.message da vardır. Hata: {"ok":false,"error":{code,message,errors}}.
- SUMMARY: count:int, lines[] (PricingService satırı + slug, image_path|null), subtotal, discount_total, coupon{code|null,message}, shipping_total, shipping_is_free, cod_fee, grand_total, tax_total, free_shipping{threshold,remaining,percent:int 0-100,is_free}, warnings[], blocking_errors[].
- GET /sepet/ozet: istek yok -> data=SUMMARY. Girişsizse oturuma intended_url='/odeme' yazar.
- POST /sepet/ekle: {"variant_id":int,"quantity":int 1..20} -> data=SUMMARY+message. Hata: 422 validation (errors.variant_id) | 422 cart_error (stok/limit/satışta değil).
- POST /sepet/guncelle: {"variant_id":int,"quantity":int 0..99} (0=sil; servis 20 ve stokla kırpar, kırpılırsa message bilgi verir) -> data=SUMMARY+message. Hata: 422 validation | 422 cart_error.
- POST /sepet/sil: {"variant_id":int} -> data=SUMMARY+message. Hata: 422 validation.
- POST /sepet/kupon: {"code":string<=40} -> data=SUMMARY+message. Hata: 422 validation (errors.code) | 422 coupon_invalid (message).
- POST /sepet/kupon/kaldir: {} -> data=SUMMARY+message.
- Ortak hatalar: 401/403/419/429 çekirdek middleware'den (05b).
- Sayfa: GET /sepet HTML (view front.cart.index).

## (4) ROUTE SATIRLARI
GET /sepet -> CartController@index | global
GET /sepet/ozet -> CartController@summary | global
POST /sepet/ekle -> CartController@add | throttle:60,60
POST /sepet/guncelle -> CartController@update | global
POST /sepet/sil -> CartController@remove | global
POST /sepet/kupon -> CartController@applyCoupon | throttle:10,60
POST /sepet/kupon/kaldir -> CartController@removeCoupon | global
(Controller: App\Controllers\Front\CartController. "global" = secure_headers + csrf.)

## (5) .env / settings anahtarları
- .env: APP_KEY (ym_cart HMAC imzası; boşsa RuntimeException), APP_URL (çerez Secure kararı).
- settings (okunan): shipping.free_threshold, cod.enabled, cod.fee, card.enabled, bank_transfer.enabled, order.min_amount.
- Oturum anahtarları: cart.coupon (kupon kodu), intended_url (yazılır). Çerez: ym_cart (HttpOnly, SameSite=Lax, Secure HTTPS'te, 30 gün; DB'de SHA-256).

## Sonraki oturumlar için notlar
- Sipariş oluştururken PricingService::calculate yeniden çağrılmalı (kupon sayaç artırma Coupon::incrementUsedIfAvailable + CouponUsage::record transaction'da); kupon uygulanıyorsa sepet kuponu CartService::couponCode().
- Sipariş sonrası CartService::clear() çağrılmalı. Stok düşümü ProductVariant::lockManyForUpdate + decrementStock ile.
- Header partial'ı partials.mini-cart'ı include etmeli ve sepet düğmesi data-bs-toggle="offcanvas" data-bs-target="#ymCartOffcanvas" kullanmalı.
- layouts.main cart.js'i global yüklemeli (mini-cart her sayfada); sayfa $scripts'inden ayrıca vermek gerekmez.
- cart.js, App.fetchJson'a body'yi JSON metin + Content-Type ile verir; app.js farklı davranıyorsa post() güncellenmeli.
- 07'deki /sepet/ekle beklentisi (data.count) sağlanır; yanıt ek olarak tam özet taşır.