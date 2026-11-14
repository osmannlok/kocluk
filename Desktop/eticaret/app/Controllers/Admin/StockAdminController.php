<?php
// app/Controllers/Admin/StockAdminController.php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\HttpException;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Services\StockService;
use DomainException;
use InvalidArgumentException;

/**
 * Yönetim: varyant stok listesi, stok düzeltme, hareket geçmişi ve kritik stok.
 * Güvenlik: stok miktarı yalnızca StockService::adjust ile değişir (tek transaction, not zorunlu,
 * eksiye düşmez, stock_movements + audit kaydı serviste). Controller SQL içermez.
 */
final class StockAdminController extends AdminBaseController
{
    /** Elle düzeltmede izin verilen hareket türleri ve arayüz etiketleri (beyaz liste). */
    private const ADJUST_TYPES = [
        'purchase'      => 'Mal kabul (satın alma)',
        'manual_adjust' => 'Manuel stok düzeltme',
        'correction'    => 'Sayım düzeltmesi',
    ];

    /** Geçmiş ekranında gösterilen tüm hareket türü etiketleri. */
    private const TYPE_LABELS = [
        'initial'       => 'Başlangıç stoğu',
        'purchase'      => 'Mal kabul (satın alma)',
        'sale'          => 'Satış',
        'cancel_return' => 'İptal iadesi',
        'manual_adjust' => 'Manuel stok düzeltme',
        'correction'    => 'Sayım düzeltmesi',
    ];

    private const MAX_QUANTITY = 1000000;

    private ProductVariant $variants;
    private Product $products;
    private StockMovement $movements;
    private Order $orders;
    private StockService $stock;

    public function __construct(
        ?ProductVariant $variants = null,
        ?Product $products = null,
        ?StockMovement $movements = null,
        ?Order $orders = null,
        ?StockService $stock = null
    ) {
        if (method_exists(parent::class, '__construct')) {
            parent::__construct();
        }
        $this->variants = $variants ?? new ProductVariant();
        $this->products = $products ?? new Product();
        $this->movements = $movements ?? new StockMovement();
        $this->orders = $orders ?? new Order();
        $this->stock = $stock ?? new StockService();
    }

    public function index(Request $request): Response
    {
        $this->require('stock.manage');

        return $this->listing($request, null);
    }

    /** /yonetim/stok/kritik: kritik stoktaki varyantlar (durum süzgeci kritik'e sabitlenir). */
    public function lowStock(Request $request): Response
    {
        $this->require('stock.manage');

        return $this->listing($request, 'kritik');
    }

    /**
     * POST JSON: variant_id, type (purchase|manual_adjust|correction), note (zorunlu) ve
     * delta VEYA new_quantity (yalnızca biri).
     */
    public function adjust(Request $request): Response
    {
        $this->require('stock.manage');

        $data = $request->json();
        if ($data === []) {
            $post = $request->post();
            $data = is_array($post) ? $post : [];
        }

        $errors = [];

        $variantId = $this->toInt($data['variant_id'] ?? null);
        if ($variantId === null || $variantId < 1) {
            $errors['variant_id'] = 'Geçerli bir varyant seçin.';
        }

        $type = $data['type'] ?? null;
        if (!is_string($type) || !isset(self::ADJUST_TYPES[$type])) {
            $errors['type'] = 'Geçerli bir hareket türü seçin.';
            $type = '';
        }

        $note = isset($data['note']) && is_scalar($data['note']) ? trim((string) $data['note']) : '';
        if ($note === '') {
            $errors['note'] = 'Not zorunludur.';
        } elseif (mb_strlen($note) > 255) {
            $errors['note'] = 'Not en çok 255 karakter olabilir.';
        }

        $hasDelta = array_key_exists('delta', $data) && $data['delta'] !== '' && $data['delta'] !== null;
        $hasNew = array_key_exists('new_quantity', $data) && $data['new_quantity'] !== '' && $data['new_quantity'] !== null;
        $delta = null;
        $newQty = null;
        if ($hasDelta === $hasNew) {
            $errors['delta'] = 'Değişim miktarı veya yeni miktardan yalnızca biri girilmelidir.';
        } elseif ($hasDelta) {
            $delta = $this->toInt($data['delta']);
            if ($delta === null || abs($delta) > self::MAX_QUANTITY) {
                $errors['delta'] = 'Değişim miktarı geçerli bir tam sayı olmalıdır.';
            }
        } else {
            $newQty = $this->toInt($data['new_quantity']);
            if ($newQty === null || $newQty < 0 || $newQty > self::MAX_QUANTITY) {
                $errors['new_quantity'] = 'Yeni miktar 0 veya daha büyük bir tam sayı olmalıdır.';
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $variant = $this->variants->find((int) $variantId);
        if ($variant === null) {
            throw new HttpException(404, 'Varyant bulunamadı.');
        }
        if ((int) $variant['track_stock'] !== 1) {
            throw new ValidationException(['variant_id' => 'Bu varyantta stok takibi kapalı.']);
        }

        // Yeni miktar girildiyse fark, işlemden hemen önce okunan güncel stoğa göre hesaplanır.
        // Eşzamanlı satış olursa son stok istenenden farklı kalabilir; yanıttaki stock_quantity gerçek değerdir.
        $current = (int) $variant['stock_quantity'];
        if ($newQty !== null) {
            if ($type === 'purchase') {
                throw new ValidationException(['new_quantity' => 'Mal kabulde değişim miktarı girilmelidir.']);
            }
            $delta = $newQty - $current;
        }
        $delta = (int) $delta;

        if ($delta === 0) {
            throw new ValidationException([$hasNew ? 'new_quantity' : 'delta' => 'Stok değişmiyor; farklı bir miktar girin.']);
        }
        if ($type === 'purchase' && $delta < 0) {
            throw new ValidationException(['delta' => 'Mal kabul için pozitif miktar girin.']);
        }
        if ($current + $delta < 0) {
            throw new ValidationException(['delta' => 'Stok eksiye düşemez (mevcut stok: ' . $current . ').']);
        }

        $adminId = Auth::id();
        if ($adminId === null) {
            throw new HttpException(401, 'Oturum gerekli.');
        }

        try {
            $this->stock->adjust((int) $variantId, $delta, $type, $note, $adminId);
        } catch (InvalidArgumentException | DomainException $e) {
            throw new ValidationException(['delta' => $e->getMessage()]);
        }

        $fresh = $this->variants->find((int) $variantId) ?? $variant;
        $qty = (int) $fresh['stock_quantity'];
        $threshold = (int) $fresh['low_stock_threshold'];

        return $this->ok([
            'variant_id'          => (int) $variantId,
            'quantity_change'     => $delta,
            'stock_quantity'      => $qty,
            'low_stock_threshold' => $threshold,
            'status'              => $this->statusOf(true, $qty, $threshold),
        ]);
    }

    public function history(Request $request, int $variantId): Response
    {
        $this->require('stock.manage');

        $variant = $this->variants->find($variantId);
        if ($variant === null) {
            throw new HttpException(404, 'Varyant bulunamadı.');
        }
        $product = $this->products->find((int) $variant['product_id']);

        $result = $this->movements->forVariant($variantId, $this->currentPage($request), $this->perPage());
        if (!isset($result['items'])) {
            // Savunma: sayfalı olmayan dönüş gelirse tek sayfa gibi ele alınır.
            $count = count($result);
            $result = ['items' => $result, 'total' => $count, 'page' => 1, 'per_page' => max(1, $count), 'pages' => 1];
        }

        // Sipariş referanslarının insan okunur numaraları (bağlantı: /yonetim/siparisler/{orderNumber}).
        $numbers = [];
        $movements = [];
        foreach ($result['items'] as $m) {
            $orderNumber = null;
            if (($m['reference_type'] ?? null) === 'order' && !empty($m['reference_id'])) {
                $rid = (int) $m['reference_id'];
                if (!array_key_exists($rid, $numbers)) {
                    $order = $this->orders->find($rid);
                    $numbers[$rid] = $order !== null && !empty($order['order_number']) ? (string) $order['order_number'] : null;
                }
                $orderNumber = $numbers[$rid];
            }
            $m['order_number'] = $orderNumber;
            $m['type_label'] = self::TYPE_LABELS[(string) ($m['type'] ?? '')] ?? (string) ($m['type'] ?? '');
            $movements[] = $m;
        }

        return $this->view('admin.stock.history', [
            'title'     => 'Stok hareketleri',
            'variant'   => [
                'id'                  => (int) $variant['id'],
                'product_id'          => (int) $variant['product_id'],
                'product_name'        => $product !== null ? (string) $product['name'] : '',
                'name'                => (string) $variant['name'],
                'sku'                 => (string) $variant['sku'],
                'stock_quantity'      => (int) $variant['stock_quantity'],
                'low_stock_threshold' => (int) $variant['low_stock_threshold'],
                'track_stock'         => (int) $variant['track_stock'] === 1,
            ],
            'movements' => $movements,
            'pager'     => $this->pager($result, $request),
        ]);
    }

    // ------------------------------------------------------------------
    // Özel yardımcılar
    // ------------------------------------------------------------------

    /**
     * Varyant stok listesi. Kritik/stoksuz süzgeci v_low_stock (ProductVariant::lowStock) üzerinden,
     * süzgeçsiz liste ürün sayfalaması (Product::adminList) + ProductVariant::forProduct ile kurulur.
     */
    private function listing(Request $request, ?string $forced): Response
    {
        $q = $request->query('q');
        $q = is_string($q) ? mb_substr(trim($q), 0, 100) : '';

        $durum = $forced ?? $this->readStatus($request);
        $page = $this->currentPage($request);
        $perPage = $this->perPage();

        $rows = [];
        if ($durum === 'kritik' || $durum === 'stoksuz') {
            foreach ($this->variants->lowStock() as $r) {
                $qty = (int) $r['stock_quantity'];
                if ($durum === 'stoksuz' && $qty > 0) {
                    continue;
                }
                if ($q !== '' && !$this->matches($r, $q)) {
                    continue;
                }
                $threshold = (int) $r['low_stock_threshold'];
                $rows[] = [
                    'variant_id'          => (int) $r['variant_id'],
                    'product_id'          => (int) $r['product_id'],
                    'product_name'        => (string) $r['product_name'],
                    'variant_name'        => (string) $r['variant_name'],
                    'sku'                 => (string) $r['sku'],
                    'stock_quantity'      => $qty,
                    'low_stock_threshold' => $threshold,
                    'track_stock'         => true,
                    'is_active'           => true,
                    'status'              => $this->statusOf(true, $qty, $threshold),
                ];
            }

            $total = count($rows);
            $pages = max(1, (int) ceil($total / $perPage));
            $page = min($page, $pages);
            $result = [
                'items'    => array_slice($rows, ($page - 1) * $perPage, $perPage),
                'total'    => $total,
                'page'     => $page,
                'per_page' => $perPage,
                'pages'    => $pages,
            ];
            $rows = $result['items'];
        } else {
            $result = $this->products->adminList($q !== '' ? ['q' => $q] : [], $page, $perPage);
            foreach ($result['items'] as $p) {
                foreach ($this->variants->forProduct((int) $p['id'], false) as $v) {
                    $qty = (int) $v['stock_quantity'];
                    $threshold = (int) $v['low_stock_threshold'];
                    $tracked = (int) $v['track_stock'] === 1;
                    $rows[] = [
                        'variant_id'          => (int) $v['id'],
                        'product_id'          => (int) $p['id'],
                        'product_name'        => (string) $p['name'],
                        'variant_name'        => (string) $v['name'],
                        'sku'                 => (string) $v['sku'],
                        'stock_quantity'      => $qty,
                        'low_stock_threshold' => $threshold,
                        'track_stock'         => $tracked,
                        'is_active'           => (int) $v['is_active'] === 1,
                        'status'              => $this->statusOf($tracked, $qty, $threshold),
                    ];
                }
            }
        }

        return $this->view('admin.stock.index', [
            'title'      => $forced === 'kritik' ? 'Kritik stok' : 'Stok',
            'rows'       => $rows,
            'pager'      => $this->pager($result, $request),
            'filters'    => ['q' => $q, 'durum' => $durum],
            'lockedLow'  => $forced === 'kritik',
            'types'      => self::ADJUST_TYPES,
        ]);
    }

    /** durum=kritik|stoksuz veya dashboard bağlantısındaki low=1 / out=1. */
    private function readStatus(Request $request): string
    {
        $durum = $request->query('durum');
        if ($durum === 'kritik' || $durum === 'stoksuz') {
            return $durum;
        }
        if ($request->query('out') === '1') {
            return 'stoksuz';
        }
        if ($request->query('low') === '1') {
            return 'kritik';
        }

        return '';
    }

    /** @param array<string,mixed> $row */
    private function matches(array $row, string $q): bool
    {
        foreach (['product_name', 'variant_name', 'sku'] as $key) {
            if (mb_stripos((string) ($row[$key] ?? ''), $q) !== false) {
                return true;
            }
        }

        return false;
    }

    /** untracked | out | low | ok */
    private function statusOf(bool $tracked, int $qty, int $threshold): string
    {
        if (!$tracked) {
            return 'untracked';
        }
        if ($qty <= 0) {
            return 'out';
        }

        return $qty <= $threshold ? 'low' : 'ok';
    }

    private function toInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value)) {
            return null;
        }
        $r = filter_var(trim($value), FILTER_VALIDATE_INT);

        return $r === false ? null : (int) $r;
    }
}