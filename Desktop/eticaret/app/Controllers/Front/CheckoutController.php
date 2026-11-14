<?php // app/Controllers/Front/CheckoutController.php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Helpers\Money;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Page;
use App\Models\Setting;
use App\Services\AddressService;
use App\Services\CartService;
use App\Services\InsufficientStockException;
use App\Services\OrderNotificationService;
use App\Services\OrderService;
use App\Services\PricingService;
use DomainException;

/**
 * Ödeme (checkout) akışı. Tutarlar her adımda sunucuda yeniden hesaplanır.
 */
final class CheckoutController extends BaseController
{
    public function index(Request $request): Response
    {
        $cart = new CartService();
        if ($cart->count() === 0) {
            $this->flash('warning', 'Sepetiniz boş.');
            return $this->redirect('/sepet');
        }
        if ($blocked = $this->requireVerifiedEmail()) {
            return $blocked;
        }

        $userId = (int) Auth::id();
        $addressService = new AddressService();
        $addresses = array_map(
            static fn (array $a): array => $addressService->present($a),
            (new Address())->forUser($userId)
        );

        $selectedAddressId = 0;
        foreach ($addresses as $a) {
            if ($a['is_default']) {
                $selectedAddressId = $a['id'];
                break;
            }
        }
        if ($selectedAddressId === 0 && $addresses !== []) {
            $selectedAddressId = $addresses[0]['id'];
        }
        $oldAddress = (int) old('shipping_address_id', 0);
        if ($oldAddress > 0) {
            $selectedAddressId = $oldAddress;
        }

        $methods = $this->paymentMethods();
        $codes = array_column($methods, 'code');
        $selectedMethod = (string) old('payment_method', $codes[0] ?? '');
        if (!in_array($selectedMethod, $codes, true)) {
            $selectedMethod = $codes[0] ?? '';
        }

        $summary = (new PricingService())->calculate(
            $cart->lines(),
            $cart->couponCode(),
            $selectedMethod !== '' ? $selectedMethod : null,
            $userId
        );
        if ($methods === []) {
            $summary['blocking_errors'][] = 'Şu anda aktif bir ödeme yöntemi bulunmuyor.';
        }

        $settings = new Setting();

        return $this->view('front.checkout.index', [
            'title' => 'Ödeme',
            'metaDescription' => 'Siparişinizi tamamlayın.',
            'scripts' => ['checkout.js'],
            'addresses' => $addresses,
            'selectedAddressId' => $selectedAddressId,
            'paymentMethods' => $methods,
            'selectedMethod' => $selectedMethod,
            'summary' => $summary,
            'nonce' => (new OrderService())->issueNonce(),
            'codFee' => (string) $settings->get('cod.fee', '0.00'),
            'legalLinks' => $this->legalLinks(),
        ]);
    }

    public function summary(Request $request): Response
    {
        $payload = $this->payload($request);
        $validator = Validator::make($payload, ['payment_method' => 'required|in:credit_card,bank_transfer,cod']);
        if ($validator->fails()) {
            throw new ValidationException($validator->errors());
        }
        $method = (string) $validator->validated()['payment_method'];

        $cart = new CartService();
        $lines = $cart->lines();
        if ($lines === []) {
            return $this->fail('cart_empty', 'Sepetiniz boş.', 422);
        }

        $summary = (new PricingService())->calculate($lines, $cart->couponCode(), $method, (int) Auth::id());

        $threshold = Money::toCents((string) ($summary['free_shipping_threshold'] ?? '0.00'));
        $base = max(0, Money::toCents((string) $summary['subtotal']) - Money::toCents((string) $summary['discount_total']));
        $percent = $threshold > 0 ? min(100, intdiv($base * 100, $threshold)) : 0;

        return $this->ok([
            'payment_method' => $method,
            'subtotal' => $summary['subtotal'],
            'discount_total' => $summary['discount_total'],
            'coupon' => $summary['coupon'],
            'shipping_total' => $summary['shipping_total'],
            'shipping_is_free' => (bool) $summary['shipping_is_free'],
            'cod_fee' => $summary['cod_fee'],
            'grand_total' => $summary['grand_total'],
            'tax_total' => $summary['tax_total'],
            'free_shipping' => [
                'threshold' => $summary['free_shipping_threshold'] ?? '0.00',
                'remaining' => $summary['free_shipping_remaining'],
                'percent' => $percent,
                'is_free' => (bool) $summary['shipping_is_free'],
            ],
            'warnings' => $summary['warnings'],
            'blocking_errors' => $summary['blocking_errors'],
        ]);
    }

    public function place(Request $request): Response
    {
        if ($blocked = $this->requireVerifiedEmail()) {
            return $blocked;
        }

        $billingType = (string) $request->post('billing_type', 'individual');
        $data = $this->validate($request, [
            'shipping_address_id' => 'required|integer',
            'billing_type' => 'required|in:individual,corporate',
            'payment_method' => 'required|in:credit_card,bank_transfer,cod',
            'customer_note' => 'nullable|string|max:500',
            'nonce' => 'required|string',
            'accept_distance_sales' => 'required|in:1',
            'accept_kvkk' => 'required|in:1',
        ], [
            'attributes' => [
                'shipping_address_id' => 'Teslimat adresi',
                'billing_type' => 'Fatura tipi',
                'payment_method' => 'Ödeme yöntemi',
                'customer_note' => 'Sipariş notu',
                'nonce' => 'Form anahtarı',
                'accept_distance_sales' => 'Mesafeli satış sözleşmesi onayı',
                'accept_kvkk' => 'KVKK onayı',
            ],
            'accept_distance_sales.required' => 'Mesafeli Satış Sözleşmesi ve Ön Bilgilendirme Formu onayı zorunludur.',
            'accept_distance_sales.in' => 'Mesafeli Satış Sözleşmesi ve Ön Bilgilendirme Formu onayı zorunludur.',
            'accept_kvkk.required' => 'KVKK Aydınlatma Metni onayı zorunludur.',
            'accept_kvkk.in' => 'KVKK Aydınlatma Metni onayı zorunludur.',
        ]);

        $orderService = new OrderService();

        // Çift gönderim: nonce tek kullanımlıktır; ikinci istek burada elenir.
        if (!$orderService->consumeNonce((string) $data['nonce'])) {
            $this->flash('warning', 'Bu sipariş formu daha önce gönderilmiş veya süresi dolmuş. Lütfen bilgileri kontrol edip tekrar deneyin.');
            return $this->redirect('/odeme');
        }

        $checkout = [
            'shipping_address_id' => (int) $data['shipping_address_id'],
            'payment_method' => (string) $data['payment_method'],
            'customer_note' => $data['customer_note'] ?? null,
            'billing' => [
                'type' => $billingType,
                'tc_identity_no' => (string) $request->post('billing_tc_identity_no', ''),
                'company_name' => (string) $request->post('billing_company_name', ''),
                'tax_office' => (string) $request->post('billing_tax_office', ''),
                'tax_number' => (string) $request->post('billing_tax_number', ''),
            ],
            'consents' => [
                'distance_sales' => true,
                'kvkk' => true,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        ];

        try {
            $result = $orderService->createFromCart((int) Auth::id(), $checkout);
        } catch (InsufficientStockException $e) {
            $this->flash('warning', 'Sepetinizdeki bazı ürünlerin stoğu yetersiz. Lütfen sepetinizi kontrol edin.');
            return $this->redirect('/sepet');
        } catch (ValidationException $e) {
            // Fatura alanı hataları form verisiyle geri döner (hassas alanlar old'dan düşürülür).
            throw new ValidationException($e->errors(), $request->all());
        } catch (DomainException $e) {
            $this->flash('error', $e->getMessage());
            return $this->redirect('/odeme');
        }

        $number = rawurlencode($result['order_number']);

        return $this->redirect(
            $result['payment_method'] === 'credit_card'
                ? '/odeme/kart/' . $number
                : '/siparis/tamamlandi/' . $number
        );
    }

    public function complete(Request $request, string $orderNumber): Response
    {
        if (preg_match('/^YM-\d{4}-\d{6}$/', $orderNumber) !== 1) {
            throw new HttpException(404, 'Sipariş bulunamadı.');
        }

        // IDOR: sipariş yalnızca sahibine gösterilir.
        $order = (new Order())->findForUser($orderNumber, (int) Auth::id());
        if ($order === null) {
            throw new HttpException(404, 'Sipariş bulunamadı.');
        }

        $shipping = json_decode((string) $order['shipping_address'], true);

        return $this->view('front.checkout.complete', [
            'title' => 'Siparişiniz alındı',
            'metaDescription' => 'Sipariş özeti.',
            'scripts' => ['checkout.js'],
            'order' => $order,
            'items' => (new OrderItem())->forOrder((int) $order['id']),
            'shipping' => is_array($shipping) ? $shipping : [],
            'paymentInstructions' => (new OrderNotificationService())->paymentInstructions($order),
        ]);
    }

    private function requireVerifiedEmail(): ?Response
    {
        $user = Auth::user();
        if ($user === null || empty($user['email_verified_at'])) {
            $this->flash('warning', 'Sipariş verebilmek için e-posta adresinizi doğrulamanız gerekir.');
            return $this->redirect('/eposta-dogrula');
        }

        return null;
    }

    /** @return list<array{code:string,label:string,description:string}> */
    private function paymentMethods(): array
    {
        $settings = new Setting();
        $methods = [];

        if ((bool) $settings->get('card.enabled', false)) {
            $methods[] = [
                'code' => 'credit_card',
                'label' => 'Kredi / Banka Kartı',
                'description' => 'PayTR güvencesiyle güvenli ödeme. Kart bilgileriniz sitemizde saklanmaz.',
            ];
        }
        if ((bool) $settings->get('bank_transfer.enabled', false)) {
            $methods[] = [
                'code' => 'bank_transfer',
                'label' => 'Havale / EFT',
                'description' => 'Sipariş sonrası banka bilgilerini göreceksiniz. Ödemeniz dekont kontrolüyle onaylanır.',
            ];
        }
        if ((bool) $settings->get('cod.enabled', false)) {
            $fee = (string) $settings->get('cod.fee', '0.00');
            $methods[] = [
                'code' => 'cod',
                'label' => 'Kapıda Ödeme',
                'description' => 'Teslimatta nakit veya kartla ödeyebilirsiniz. Kapıda ödeme ücreti: ' . money($fee) . '.',
            ];
        }

        return $methods;
    }

    /** @return array<string,?string> sistem sayfası anahtarı => yol */
    private function legalLinks(): array
    {
        $pages = new Page();
        $links = [];
        foreach (['distance_sales', 'kvkk'] as $key) {
            $row = $pages->findBySystemKey($key);
            $links[$key] = $row !== null ? '/sayfa/' . $row['slug'] : null;
        }

        return $links;
    }

    private function payload(Request $request): array
    {
        $json = $request->json();

        return $json !== [] ? $json : $request->all();
    }
}