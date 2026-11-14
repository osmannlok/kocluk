# 13-hesap-sozlesme.md
Oturum 10: Müşteri hesabı. Kaynak: yazılan GERÇEK kod. Modeller/servisler `new` ile üretilir; SQL yok.

## (1) Dosya, sınıf, public metot imzaları
- app/Controllers/Front/AccountController.php: final extends BaseController
  - dashboard(Request $request): Response — son 3 sipariş, varsayılan adres (TC maskeli), doğrulama durumu.
  - profile(Request $request): Response — profil + parola formları.
  - updateProfile(Request $request): Response — ad, soyad, telefon, pazarlama izni (e-posta yok); AuditService 'user.profile_updated'.
  - passwordForm(Request $request): Response — [EK] profil sayfasını parola kartına odaklı gösterir (menü "Şifre").
  - changePassword(Request $request): Response — Validator (yalnız alan doğrulama) + (new AuthService())->changePassword; hata flash 'error', başarı flash 'success'; old() boş.
  - addresses(Request $request): Response — [EK] adres sayfası (AddressService::present ile maskeli).
- app/Controllers/Front/OrderHistoryController.php: final extends BaseController
  - index(Request $request): Response — sayfalı sipariş listesi (10/sayfa), kalem başına en çok 3 küçük görsel.
  - show(Request $request, string $orderNumber): Response — detay, adımlı çizelge, kargo, ödeme, havale talimatı, tekrar ödeme bağlantısı.
  - cancel(Request $request, string $orderNumber): Response — yalnız pending_payment|paid; OrderStatusService::transition(...,'cancelled',...); AuditService 'order.cancel_by_customer'; sendCancelled.
  - public static decorate(array $order): array — etiket/rozet/tarih ekler, ham IP alanlarını çıkarır [EK].
  - public static formatDate(mixed $value): string — d.m.Y H:i [EK].
  - public const STATUS_MAP, PAYMENT_METHODS, PAYMENT_STATUSES [EK].
- resources/views/layouts/account.php: girdi $content,$title,$metaDescription; kendi içinde layouts.main'i View::partial ile sarmalar; menü is_path() ile.
- resources/views/front/account/dashboard.php: $user,$recentOrders,$defaultAddress,$emailVerified.
- resources/views/front/account/orders.php: $orders,$thumbs,$pager.
- resources/views/front/account/order-show.php: $order,$items,$steps,$historyRows,$shipments,$installments,$instructions,$payUrl,$shippingAddress,$billing,$canCancel.
- resources/views/front/account/addresses.php: $addresses,$maxAddresses (+ partials.address-modal).
- resources/views/front/account/profile.php: $user,$focusPassword (iki ayrı form).
- public/assets/js/account.js: IIFE, window.App kullanır. Öznitelikler: form[data-confirm-submit="mesaj"], [data-copy-text][data-copy-label], [data-address-delete][data-url][data-title], [data-address-default][data-url], [data-address-card], [data-default-badge]. 'address:saved' dinler (sayfa yenilenir).

## (2) Tablolar ve permission kodları
- Tablolar (yalnız model/servis üzerinden): users, addresses, orders, order_items, order_status_history, payments, shipments, product_variants, product_images, stock_movements/coupons/coupon_usages (OrderStatusService), audit_logs, sessions (AuthService).
- Permission: YOK (yalnızca auth). IDOR: Order::findForUser, Address (AddressService) user_id ile sınırlı.

## (3) JSON uç noktaları
- Bu oturumda yeni JSON ucu YOK; tüm POST'lar form + 302/303.
- account.js, 11-siparis-sozlesme'deki uçları kullanır (gövdesiz POST): POST /hesabim/adresler/{id}/sil -> {"ok":true,"data":{"id":int}}; POST /hesabim/adresler/{id}/varsayilan -> {"ok":true,"data":{"id":int}}; hata {"ok":false,"error":{code,message,errors}}.
- Adres ekle/düzenle modal akışı checkout.js'tedir (addresses sayfasında checkout.js + account.js birlikte yüklenir).
- Form alanları: POST /hesabim/profil: _token, first_name, last_name, phone?, marketing_consent(0|1). POST /hesabim/sifre: _token, current_password, new_password, new_password_confirmation. POST /hesabim/siparisler/{orderNumber}/iptal: _token. POST /eposta-dogrula/tekrar (08): _token.

## (4) ROUTE SATIRLARI
GET /hesabim -> AccountController@dashboard | auth
GET /hesabim/siparisler -> OrderHistoryController@index | auth
GET /hesabim/siparisler/{orderNumber} -> OrderHistoryController@show | auth
POST /hesabim/siparisler/{orderNumber}/iptal -> OrderHistoryController@cancel | auth
GET /hesabim/adresler -> AccountController@addresses | auth
GET /hesabim/profil -> AccountController@profile | auth
POST /hesabim/profil -> AccountController@updateProfile | auth
GET /hesabim/sifre -> AccountController@passwordForm | auth [EK]
POST /hesabim/sifre -> AccountController@changePassword | auth
(Rota sırası: /hesabim/adresler/liste ve {id} rotaları 11'deki gibi; /hesabim/adresler GET bunlardan bağımsız. {orderNumber} biçimi controller'da ^YM-\d{4}-\d{6,}$ ile doğrulanır, aksi 404.)

## (5) .env / settings anahtarları
- Yok. (Havale talimatı için OrderNotificationService::paymentInstructions settings bank_transfer.* ve order.* anahtarlarını okur.)
- Oturum: flash türleri success, error, warning; View::share('scripts', ['account.js']) (adresler: ['checkout.js','account.js']).

## Notlar / SAPMA
- Validator kural adları varsayıldı: required, min, max, nullable, phone_tr, boolean, confirmed (new_password üzerinde, new_password_confirmation alanıyla).
- Sipariş kartı görselleri: OrderItem::forOrder -> ProductVariant::findActiveMany -> ProductImage::forProduct (pasif/silinmiş ürünlerde yer tutucu simge).
- Müşteriye durum geçmişinde personel adı/iç not gösterilmez; cancel_reason gösterilmez.
- Müşteri iptali: paid sipariş iptalinde payment_status 'paid' kalır, iade yöneticiyle (PayTR panelinden) yapılır.
- order-cancelled e-posta şablonu Oturum 13'te; o zamana dek sendCancelled false döner (Logger::warning).
- Takip bağlantısı yalnız http/https ve rel="noopener noreferrer"; TC maskeli (ilk 3 + son 2).