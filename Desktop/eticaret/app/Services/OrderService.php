<?php // app/Services/OrderService.php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Session;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Models\Address;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use DateTimeImmutable;
use DomainException;
use Throwable;

/**
 * Sepetten sipariş oluşturma. Fiyatlar sunucuda yeniden hesaplanır; istemci değeri kullanılmaz.
 */
final class OrderService
{
    private const NONCE_KEY = 'checkout_nonces';
    private const NONCE_MAX = 5;

    private const PROVIDERS = [
        'credit_card' => 'paytr',
        'bank_transfer' => 'bank_transfer',
        'cod' => 'cod',
    ];

    private PricingService $pricing;
    private CartService $cart;
    private StockService $stock;
    private OrderNotificationService $notifier;
    private Order $orders;
    private OrderItem $orderItems;
    private OrderStatusHistory $history;
    private Payment $payments;
    private Address $addresses;
    private Coupon $coupons;
    private CouponUsage $couponUsages;
    private Setting $settings;
    private User $users;

    public function __construct(
        ?PricingService $pricing = null,
        ?CartService $cart = null,
        ?StockService $stock = null,
        ?OrderNotificationService $notifier = null
    ) {
        $this->pricing = $pricing ?? new PricingService();
        $this->cart = $cart ?? new CartService();
        $this->stock = $stock ?? new StockService();
        $this->notifier = $notifier ?? new OrderNotificationService();
        $this->orders = new Order();
        $this->orderItems = new OrderItem();
        $this->history = new OrderStatusHistory();
        $this->payments = new Payment();
        $this->addresses = new Address();
        $this->coupons = new Coupon();
        $this->couponUsages = new CouponUsage();
        $this->settings = new Setting();
        $this->users = new User();
    }

    /**
     * $checkout: shipping_address_id:int, billing:{type,tc_identity_no?,company_name?,tax_office?,tax_number?},
     * payment_method, customer_note:?string, consents:{distance_sales:bool,kvkk:bool,ip:string,user_agent:?string}
     *
     * @return array{order_id:int,order_number:string,payment_method:string}
     */
    public function createFromCart(int $userId, array $checkout): array
    {
        $method = (string) ($checkout['payment_method'] ?? '');
        if (!isset(self::PROVIDERS[$method])) {
            throw new DomainException('Geçersiz ödeme yöntemi.');
        }

        $consents = (array) ($checkout['consents'] ?? []);
        if (empty($consents['distance_sales']) || empty($consents['kvkk'])) {
            throw new DomainException('Mesafeli satış sözleşmesi ve KVKK onayı zorunludur.');
        }

        // Fatura alanları işlemden önce doğrulanır (veritabanına gitmeden hızlı red).
        $billingInput = $this->validateBilling((array) ($checkout['billing'] ?? []));
        $addressId = (int) ($checkout['shipping_address_id'] ?? 0);
        $note = isset($checkout['customer_note']) ? trim((string) $checkout['customer_note']) : '';
        $note = $note === '' ? null : mb_substr($note, 0, 500);

        $result = Database::instance()->transaction(
            function () use ($userId, $method, $consents, $billingInput, $addressId, $note): array {
                // (1) E-posta doğrulaması: doğrulanmamış hesap sipariş veremez.
                $user = $this->users->find($userId);
                if ($user === null || (string) $user['status'] !== 'active' || empty($user['email_verified_at'])) {
                    throw new DomainException('Sipariş verebilmek için e-posta adresinizi doğrulamanız gerekir.');
                }

                // (2) Sunucuda yeniden fiyatlandırma: istemci tutarı hiçbir yerde kullanılmaz.
                $lines = $this->cart->lines();
                if ($lines === []) {
                    throw new DomainException('Sepetiniz boş.');
                }
                $pricing = $this->pricing->calculate($lines, $this->cart->couponCode(), $method, $userId);
                if (!empty($pricing['blocking_errors'])) {
                    throw new DomainException(implode(' ', array_map('strval', $pricing['blocking_errors'])));
                }

                // (3) IDOR: adres yalnızca bu kullanıcıya aitse alınır.
                $address = $this->addresses->findForUser($addressId, $userId);
                if ($address === null) {
                    throw new DomainException('Teslimat adresi bulunamadı.');
                }
                $shippingSnapshot = $this->addressSnapshot($address);
                $billingSnapshot = array_merge($shippingSnapshot, $billingInput);

                // Kupon: kodu id'ye çöz.
                $coupon = null;
                $couponCode = $pricing['coupon']['code'] ?? null;
                if ($couponCode !== null && $couponCode !== '') {
                    $coupon = $this->coupons->findByCode((string) $couponCode);
                    if ($coupon === null) {
                        throw new DomainException('Kupon artık geçerli değil.');
                    }
                }

                $status = $method === 'cod' ? 'preparing' : 'pending_payment';
                $expiresAt = $this->expiresAt($method);
                $ipBin = ip_to_bin((string) ($consents['ip'] ?? '')) ?? ip_to_bin('0.0.0.0');
                $userAgent = isset($consents['user_agent']) ? mb_substr((string) $consents['user_agent'], 0, 255) : null;
                $now = now();

                // (6) Sipariş başlığı. Stok hareketi sipariş id'sine bağlandığı için rezervasyondan önce yazılır.
                $orderId = $this->orders->createOrder([
                    'user_id' => $userId,
                    'status' => $status,
                    'payment_method' => $method,
                    'payment_status' => 'pending',
                    'currency' => 'TRY',
                    'subtotal' => $pricing['subtotal'],
                    'discount_total' => $pricing['discount_total'],
                    'coupon_id' => $coupon !== null ? (int) $coupon['id'] : null,
                    'coupon_code' => $coupon !== null ? (string) $coupon['code'] : null,
                    'shipping_total' => $pricing['shipping_total'],
                    'cod_fee' => $pricing['cod_fee'],
                    'grand_total' => $pricing['grand_total'],
                    'tax_total' => $pricing['tax_total'],
                    'total_weight_grams' => (int) $pricing['total_weight_grams'],
                    'shipping_address' => json_encode($shippingSnapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'billing_address' => json_encode($billingSnapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'customer_note' => $note,
                    'kvkk_consent_at' => $now,
                    'distance_sales_consent_at' => $now,
                    'consent_ip' => $ipBin,
                    'consent_user_agent' => $userAgent,
                    'ip_address' => $ipBin,
                    'expires_at' => $expiresAt,
                ]);

                $orderNumber = sprintf('YM-%s-%06d', date('Y'), $orderId);
                $this->orders->setOrderNumber($orderId, $orderNumber);

                // Kalemler: fiyat/ürün adı anlık görüntüsü (sonradan fiyat değişse de sipariş değişmez).
                $rows = [];
                $qty = [];
                foreach ($pricing['lines'] as $line) {
                    if (empty($line['available'])) {
                        continue;
                    }
                    $variantId = (int) $line['variant_id'];
                    $rows[] = [
                        'variant_id' => $variantId,
                        'product_name' => $line['product_name'],
                        'variant_name' => $line['variant_name'],
                        'sku' => $line['sku'],
                        'list_price' => $line['list_price'],
                        'unit_price' => $line['unit_price'],
                        'quantity' => (int) $line['quantity'],
                        'tax_rate' => $line['tax_rate'],
                        'line_total' => $line['line_total'],
                        'weight_grams' => (int) $line['weight_grams'],
                    ];
                    $qty[$variantId] = ($qty[$variantId] ?? 0) + (int) $line['quantity'];
                }
                if ($rows === []) {
                    throw new DomainException('Sepetinizde satın alınabilir ürün yok.');
                }
                $this->orderItems->insertMany($orderId, $rows);

                // (4) Stok rezervasyonu (yetersizse InsufficientStockException → rollback).
                $this->stock->reserve($qty, 'order', $orderId, $userId);

                // (5) Kupon: kullanıcı limiti yeniden kontrol, sonra atomik sayaç artırma.
                if ($coupon !== null) {
                    $perUser = $coupon['usage_per_user'] ?? null;
                    if ($perUser !== null && $this->couponUsages->countByUser((int) $coupon['id'], $userId) >= (int) $perUser) {
                        throw new DomainException('Bu kuponu kullanım hakkınızı doldurdunuz.');
                    }
                    if (!$this->coupons->incrementUsedIfAvailable((int) $coupon['id'])) {
                        throw new DomainException('Kuponun kullanım limiti doldu.');
                    }
                    $this->couponUsages->record((int) $coupon['id'], $userId, $orderId, $pricing['discount_total']);
                }

                // (7) Durum geçmişi ilk kaydı.
                $this->history->add($orderId, null, $status, $userId, 'Sipariş oluşturuldu.');

                // (8) Ödeme kaydı.
                $this->payments->createPayment([
                    'order_id' => $orderId,
                    'provider' => self::PROVIDERS[$method],
                    'merchant_oid' => $method === 'credit_card'
                        ? 'YM' . $orderId . 'T' . time() . bin2hex(random_bytes(4))
                        : null,
                    'amount' => $pricing['grand_total'],
                    'currency' => 'TRY',
                    'status' => $method === 'credit_card' ? 'initiated' : 'pending',
                ]);

                // (9) Sepet temizliği ve oturumdaki kupon.
                $this->cart->clear();
                $this->cart->removeCoupon();

                return [
                    'order_id' => $orderId,
                    'order_number' => $orderNumber,
                    'payment_method' => $method,
                ];
            }
        );

        // Bildirim transaction DIŞINDA: e-posta hatası siparişi geri almaz.
        try {
            $this->notifier->sendCreated($result['order_id']);
        } catch (Throwable $e) {
            Logger::warning('Sipariş e-postası gönderilemedi.', ['order_id' => $result['order_id'], 'error' => $e->getMessage()]);
        }

        return $result;
    }

    /** Çift gönderim koruması: formda taşınacak tek kullanımlık anahtar üretir (en fazla 5 açık sekme). */
    public function issueNonce(): string
    {
        $nonce = bin2hex(random_bytes(16));
        $list = Session::get(self::NONCE_KEY, []);
        $list = is_array($list) ? $list : [];
        $list[] = $nonce;
        Session::put(self::NONCE_KEY, array_slice($list, -self::NONCE_MAX));

        return $nonce;
    }

    /** Anahtar geçerliyse tüketir (ikinci kullanım false döner). */
    public function consumeNonce(string $nonce): bool
    {
        if (preg_match('/^[a-f0-9]{32}$/', $nonce) !== 1) {
            return false;
        }
        $list = Session::get(self::NONCE_KEY, []);
        $list = is_array($list) ? $list : [];
        foreach ($list as $i => $stored) {
            if (is_string($stored) && hash_equals($stored, $nonce)) {
                unset($list[$i]);
                Session::put(self::NONCE_KEY, array_values($list));

                return true;
            }
        }

        return false;
    }

    private function expiresAt(string $method): ?string
    {
        $unit = match ($method) {
            'credit_card' => max(1, (int) $this->settings->get('order.card_expiry_minutes', 30)) . ' minutes',
            'bank_transfer' => max(1, (int) $this->settings->get('order.bank_expiry_hours', 48)) . ' hours',
            default => null,
        };

        return $unit === null
            ? null
            : (new DateTimeImmutable('now'))->modify('+' . $unit)->format('Y-m-d H:i:s');
    }

    /** @return array<string,mixed> */
    private function addressSnapshot(array $address): array
    {
        return [
            'title' => (string) $address['title'],
            'full_name' => (string) $address['full_name'],
            'phone' => (string) $address['phone'],
            'city' => (string) $address['city'],
            'district' => (string) $address['district'],
            'neighborhood' => $address['neighborhood'] ?? null,
            'address_line' => (string) $address['address_line'],
            'postal_code' => $address['postal_code'] ?? null,
        ];
    }

    /**
     * Fatura bilgisi doğrulaması (tek yer). Hata anahtarları form alanlarıyla eşleşir (billing_*).
     *
     * @return array<string,mixed>
     */
    private function validateBilling(array $billing): array
    {
        $type = (string) ($billing['type'] ?? 'individual');
        $data = ['type' => $type];
        $rules = ['type' => 'required|in:individual,corporate'];

        if ($type === 'corporate') {
            foreach (['company_name', 'tax_office', 'tax_number'] as $key) {
                $data[$key] = trim((string) ($billing[$key] ?? ''));
            }
            $rules['company_name'] = 'required|string|max:190';
            $rules['tax_office'] = 'required|string|max:120';
            $rules['tax_number'] = ['required', 'regex:/^\d{10,11}$/'];
        } else {
            $data['tc_identity_no'] = trim((string) ($billing['tc_identity_no'] ?? ''));
            $rules['tc_identity_no'] = 'nullable|tc_identity';
        }

        $validator = Validator::make($data, $rules, [
            'attributes' => [
                'type' => 'Fatura tipi',
                'company_name' => 'Şirket adı',
                'tax_office' => 'Vergi dairesi',
                'tax_number' => 'Vergi numarası',
                'tc_identity_no' => 'TC kimlik numarası',
            ],
        ]);
        if ($validator->fails()) {
            $mapped = [];
            foreach ($validator->errors() as $field => $message) {
                $mapped['billing_' . $field] = $message;
            }
            throw new ValidationException($mapped);
        }

        $clean = $validator->validated();
        if ($type === 'corporate') {
            return [
                'type' => 'corporate',
                'company_name' => $clean['company_name'],
                'tax_office' => $clean['tax_office'],
                'tax_number' => $clean['tax_number'],
            ];
        }

        return [
            'type' => 'individual',
            'tc_identity_no' => ($clean['tc_identity_no'] ?? null) ?: null,
        ];
    }
}