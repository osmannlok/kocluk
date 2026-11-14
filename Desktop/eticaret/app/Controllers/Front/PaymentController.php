<?php
// app/Controllers/Front/PaymentController.php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Payment\PaymentException;
use App\Services\Payment\PaymentService;
use DomainException;

final class PaymentController extends BaseController
{
    /**
     * GET /odeme/kart/{orderNumber}: PayTR iframe sayfası.
     */
    public function card(Request $request, string $orderNumber): Response
    {
        $orderNumber = $this->validOrderNumber($orderNumber);

        try {
            $result = (new PaymentService())->startCardPayment($orderNumber, (int) Auth::id());
        } catch (DomainException $e) {
            $this->flash('error', $e->getMessage());
            return $this->redirect('/hesabim/siparisler/' . rawurlencode($orderNumber));
        } catch (PaymentException $e) {
            $this->flash('error', $e->getMessage());
            return $this->redirect('/siparis/tamamlandi/' . rawurlencode($orderNumber));
        }

        $order = $result['order'];
        $remainingMinutes = 0;
        if (!empty($order['expires_at'])) {
            $ts = strtotime((string) $order['expires_at']);
            $remainingMinutes = $ts === false ? 0 : max(0, (int) ceil(($ts - time()) / 60));
        }

        // İçinde token olduğu için sayfa önbelleğe alınmaz.
        return $this->view('front.payment.card', [
            'title'            => 'Kart ile Ödeme',
            'order'            => $order,
            'items'            => (new OrderItem())->forOrder((int) $order['id']),
            'iframeSrc'        => (string) config('paytr.iframe_base_url') . rawurlencode($result['token']),
            'remainingMinutes' => $remainingMinutes,
            'scripts'          => ['paytr-frame.js'],
        ])->withHeader('Cache-Control', 'no-store');
    }

    /**
     * POST /odeme/paytr/bildirim: oturum ve CSRF yok (config/security.php istisnası); hash doğrulaması serviste.
     * Yanıt gövdesi yalnızca düz metin: başka çıktı, boşluk veya BOM olmamalı.
     */
    public function paytrCallback(Request $request): Response
    {
        if ($request->method() !== 'POST') {
            throw new HttpException(405, 'Yöntem desteklenmiyor.');
        }

        $post = [];
        foreach ((array) $request->post() as $key => $value) {
            if (is_string($value)) {
                $post[(string) $key] = $value;
            }
        }

        $ipBin = ip_to_bin($request->ip()) ?? "\x00\x00\x00\x00";
        $body = (new PaymentService())->handlePaytrCallback($post, $ipBin);

        return Response::text($body)->withHeader('Cache-Control', 'no-store');
    }

    /**
     * GET /odeme/basarili/{orderNumber}: kesin durum DB'den okunur, URL'ye güvenilmez.
     */
    public function success(Request $request, string $orderNumber): Response
    {
        $order = $this->ownOrder($orderNumber);
        $state = $this->resolveState($order, false);

        return $this->resultView($order, $state);
    }

    /**
     * GET /odeme/basarisiz/{orderNumber}: bildirim henüz gelmediyse tekrar deneme bağlantısı sunulur.
     */
    public function fail(Request $request, string $orderNumber): Response
    {
        $order = $this->ownOrder($orderNumber);
        $state = $this->resolveState($order, true);

        return $this->resultView($order, $state);
    }

    /**
     * @param array<string,mixed> $order
     */
    private function resultView(array $order, string $state): Response
    {
        $canRetry = (string) $order['status'] === 'pending_payment'
            && (string) $order['payment_method'] === 'credit_card'
            && !$this->isExpired($order);

        return $this->view('front.payment.result', [
            'title'    => $state === 'success' ? 'Ödeme Alındı' : ($state === 'pending' ? 'Ödeme Doğrulanıyor' : 'Ödeme Tamamlanamadı'),
            'state'    => $state,
            'order'    => $order,
            'canRetry' => $state === 'failed' && $canRetry,
        ])->withHeader('Cache-Control', 'no-store');
    }

    /**
     * @param array<string,mixed> $order
     */
    private function resolveState(array $order, bool $fromFailUrl): string
    {
        if ((string) $order['payment_status'] === 'paid') {
            return 'success';
        }
        if (in_array((string) $order['status'], ['cancelled', 'refunded'], true)) {
            return 'failed';
        }
        if ((string) $order['status'] === 'pending_payment') {
            return $fromFailUrl ? 'failed' : 'pending';
        }

        return 'pending';
    }

    /**
     * @return array<string,mixed>
     */
    private function ownOrder(string $orderNumber): array
    {
        $orderNumber = $this->validOrderNumber($orderNumber);
        // IDOR: yalnızca oturumdaki kullanıcının siparişi.
        $order = (new Order())->findForUser($orderNumber, (int) Auth::id());
        if ($order === null) {
            throw new HttpException(404, 'Sipariş bulunamadı.');
        }

        return $order;
    }

    private function validOrderNumber(string $orderNumber): string
    {
        if (preg_match('/^[A-Za-z0-9-]{1,30}$/', $orderNumber) !== 1) {
            throw new HttpException(404, 'Sipariş bulunamadı.');
        }

        return $orderNumber;
    }

    /**
     * @param array<string,mixed> $order
     */
    private function isExpired(array $order): bool
    {
        if (empty($order['expires_at'])) {
            return false;
        }
        $ts = strtotime((string) $order['expires_at']);

        return $ts !== false && $ts <= time();
    }
}