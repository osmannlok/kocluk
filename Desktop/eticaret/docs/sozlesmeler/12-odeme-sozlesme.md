# 12-odeme-sozlesme.md
Oturum 9: Ödeme (PayTR iFrame API). Kaynak: yazılan GERÇEK kod. Servisler `new` ile üretilir; SecurityEvent static, diğer modeller instance.

## (1) Dosya, sınıf, public metot imzaları
- config/paytr.php: merchant_id, merchant_key, merchant_salt, test_mode(0/1), debug_on(0/1), timeout_limit(dk, varsayılan 25), no_installment(varsayılan 1), max_installment(0), currency('TL'), user_ip_override (yalnızca APP_ENV=local iken PAYTR_USER_IP, aksi halde ''), api_url, iframe_base_url, ok_url (APP_URL/odeme/basarili), fail_url (APP_URL/odeme/basarisiz). lang YOK.
- app/Services/Payment/PaymentGatewayInterface.php: initiate(array $order, array $payment, array $user, array $items): array | name(): string | isEnabled(): bool. Dönüş: ['type'=>'iframe','token'] | ['type'=>'instructions',...] | ['type'=>'none',...].
- app/Services/Payment/PaymentException.php: final extends RuntimeException. Mesajlar kullanıcıya gösterilebilir, gizli veri yok.
- app/Services/Payment/PayTRGateway.php: final | name()='paytr' | isEnabled() (settings card.enabled) | initiate(...) (token isteği; iframe) | verifyCallbackHash(array $post): bool (hash_equals). user_basket: kupon/indirim yoksa kalemler + "Kargo" + "Kapıda ödeme ücreti", varsa tek satır "Siparis {order_number}"; toplam payment_amount ile kuruş bazında eşleşmezse PaymentException. user_ip: Request::ip(), yalnızca local'de override.
- app/Services/Payment/BankTransferGateway.php: final | name()='bank_transfer' | isEnabled() (bank_transfer.enabled) | initiate() => ['type'=>'instructions','method','order_number','amount','deadline','bank_name','account_holder','iban','note','description'].
- app/Services/Payment/CodGateway.php: final | name()='cod' | isEnabled() (cod.enabled) | initiate() => ['type'=>'none','method'=>'cod','cod_fee'].
- app/Services/Payment/PaymentService.php: final | __construct(?OrderStatusService=null, ?OrderNotificationService=null) | gatewayFor(string $method): PaymentGatewayInterface (credit_card|paytr|bank_transfer|cod) | startCardPayment(string $orderNumber, int $userId): array{token,order} | handlePaytrCallback(array $post, string $ipBin): string | confirmBankTransfer(int $orderId, int $adminId, ?string $note): void | markCodCollected(int $orderId, int $adminId): void. Hatalar: HttpException 404, DomainException (iş kuralı), PaymentException (sağlayıcı).
- app/Controllers/Front/PaymentController.php: final extends BaseController | card(Request, string $orderNumber) | paytrCallback(Request) | success(Request, string $orderNumber) | fail(Request, string $orderNumber).
- resources/views/front/payment/card.php: girdi $order,$items,$iframeSrc,$remainingMinutes. resources/views/front/payment/result.php: girdi $state(success|pending|failed),$order,$canRetry. resources/views/emails/order-paid.php: girdi $name,$order,$items (satır içi stil yalnızca e-postada).
- public/assets/js/paytr-frame.js: IIFE; iFrameResizer'ı https://www.paytr.com/js/iframeResizer.min.js adresinden dinamik yükler, iFrameResize({}, '#paytriframe') çağırır. $scripts=['paytr-frame.js'] ile yüklenir.
- scripts/paytr_fake_callback.php: yalnızca CLI ve APP_ENV=local; <merchant_oid> <success|failed> <total_amount_kurus> [--bad-hash] [--repeat=N(1..5)].

## Bildirim davranışı (handlePaytrCallback)
- Hash geçersiz: SecurityEvent 'bad_paytr_hash' (critical), yanıt "PAYTR notification failed: bad hash", işlem yok.
- Bilinmeyen/biçimsiz merchant_oid: logla, 'OK' (result unknown_order).
- Payment initiated/pending değilse: 'OK' (result duplicate). Payment 'failed' iken status=success gelirse ek olarak SecurityEvent 'late_payment_on_closed_order' (reason payment_already_failed).
- status=success: total_amount orders.grand_total kuruşuyla BİREBİR eşit değilse SecurityEvent 'amount_mismatch' (critical) + Payment::markFailed('amount_mismatch') + sipariş değişmez + 'OK'. Eşitse: sipariş pending_payment değilse Payment::markSuccess (not: geç ödeme) + SecurityEvent 'late_payment_on_closed_order' (critical) + sipariş DEĞİŞMEZ + 'OK'; pending_payment ise Payment::markSuccess + OrderStatusService::transition('paid') + sendPaid (transaction dışı).
- status!=success: Payment::markFailed; sipariş pending_payment ise transition('cancelled') (stok iadesi/kupon geri alma OrderStatusService'te) + sendCancelled.
- İşlem hatası: result 'error', yanıt "PAYTR notification failed: server error" (PayTR tekrar dener).
- payment_callbacks.result değerleri: processed, duplicate, amount_mismatch, bad_hash, unknown_order, late_payment, error.

## (2) Tablolar ve permission kodları
- Tablolar (yalnızca model üzerinden): orders, order_items, payments, payment_callbacks, security_events, audit_logs, users (okuma), settings.
- Permission: PaymentController yalnızca auth (callback hariç, auth yok). confirmBankTransfer çağıran admin controller'da payment.confirm; markCodCollected için payment.confirm ya da order.update_status ile korunmalı. AuditLog: 'payment.confirm_bank_transfer', 'payment.cod_collected'.

## (3) JSON uç noktaları
YOK. POST /odeme/paytr/bildirim düz metin döner ('OK' veya hata metni, Cache-Control: no-store). Sayfalar: front.payment.card, front.payment.result.

## (4) ROUTE SATIRLARI
GET /odeme/kart/{orderNumber} -> PaymentController@card | auth
POST /odeme/paytr/bildirim -> PaymentController@paytrCallback | auth YOK (csrf istisnası config/security.php csrf_except'te; throttle konmadı)
GET /odeme/basarili/{orderNumber} -> PaymentController@success | auth
GET /odeme/basarisiz/{orderNumber} -> PaymentController@fail | auth
(Controller: App\Controllers\Front\*. Bildirim rotası auth grubunun DIŞINDA tanımlanmalı.)

## (5) .env / settings anahtarları
- .env: PAYTR_MERCHANT_ID, PAYTR_MERCHANT_KEY, PAYTR_MERCHANT_SALT, PAYTR_TEST_MODE, PAYTR_DEBUG_ON, PAYTR_TIMEOUT_LIMIT=25, PAYTR_MAX_INSTALLMENT, [YENİ] PAYTR_NO_INSTALLMENT=1, [YENİ] PAYTR_USER_IP (yalnızca yerel). .env.example güncellenmeli.
- settings (okunan): card.enabled, bank_transfer.enabled, bank_transfer.bank_name, bank_transfer.account_holder, bank_transfer.iban, bank_transfer.note, cod.enabled.
- CSP: config/security.php'de script-src ve frame-src'ye https://www.paytr.com eklenmeli.

## SAPMA / notlar
- payment_callbacks eklemeli olduğundan bildirim günlüğü tek satır, işlem sonunda ve transaction dışında yazılır (sonuç kodu ile).
- Kilit sırası: sipariş (findByIdForUpdate), sonra payment (findByMerchantOid forUpdate).
- AuditService imzası sözleşmede olmadığından denetim kaydı AuditLog::write ile yazılır.
- startCardPayment yalnızca açık (initiated/pending) paytr ödemesi için token üretir; kapanmış kayıtta çift tahsilatı önler.
- amount_mismatch'te Payment 'failed' işaretlenir ve sipariş cron ile süresi dolunca iptal olur; yönetici SecurityEvent'ten haberdar olur.
- Sipariş sayfasının paymentInstructions pay_url değeri /odeme/kart/{orderNumber} olmalıdır.