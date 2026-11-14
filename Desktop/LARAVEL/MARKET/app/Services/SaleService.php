<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    /** Kasiyerin yapabileceği en yüksek indirim yüzdesi. */
    public const CASHIER_MAX_DISCOUNT_PERCENT = 10;

    private const PAYMENT_TYPES = ['cash', 'card', 'transfer', 'credit'];

    public function __construct(private readonly StockService $stockService)
    {
    }

    /**
     * Satışı tamamlar: sales, sale_items, payments, stock_movements kayıtlarını
     * tek transaction içinde oluşturur.
     *
     * $data: customer_id?, discount? (genel indirim, TL), note?
     * $items: [['product_id' => 1, 'quantity' => 2, 'discount' => 0], ...]
     *         discount = birim başına indirim (TL). Birim fiyat her zaman üründen okunur.
     * $payments: [['payment_type' => 'cash', 'amount' => 700], ...]
     */
    public function createSale(array $data, array $items, array $payments, User $user): Sale
    {
        $this->assertItems($items);
        $this->assertPayments($payments);

        return DB::transaction(function () use ($data, $items, $payments, $user) {
            $isAdmin = $user->role === 'admin';

            // 1) Satırları hesapla
            $lines = [];
            $subtotal = 0.0;
            $lineDiscountTotal = 0.0;

            foreach ($items as $index => $item) {
                $product = Product::query()->find($item['product_id']);

                if (! $product || ! $product->is_active) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => 'Ürün bulunamadı veya satışa kapalı.',
                    ]);
                }

                $quantity = round((float) $item['quantity'], 3);
                $unitPrice = round((float) $product->sale_price, 2);
                $unitDiscount = round((float) ($item['discount'] ?? 0), 2);

                if ($unitDiscount < 0 || $unitDiscount > $unitPrice) {
                    throw ValidationException::withMessages([
                        "items.{$index}.discount" => 'İndirim 0 ile birim fiyat arasında olmalıdır.',
                    ]);
                }

                if (! $isAdmin && $unitPrice > 0
                    && ($unitDiscount / $unitPrice) * 100 > self::CASHIER_MAX_DISCOUNT_PERCENT) {
                    throw ValidationException::withMessages([
                        "items.{$index}.discount" => 'En fazla %' . self::CASHIER_MAX_DISCOUNT_PERCENT
                            . ' indirim yapılabilir. Fazlası için yönetici onayı gerekir.',
                    ]);
                }

                $lineSubtotal = round($quantity * $unitPrice, 2);
                $lineDiscount = round($quantity * $unitDiscount, 2);

                $lines[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'purchase_price' => round((float) $product->purchase_price, 2), // satış anı maliyeti
                    'vat_rate' => (float) $product->vat_rate,
                    'net' => round($lineSubtotal - $lineDiscount, 2),
                ];

                $subtotal += $lineSubtotal;
                $lineDiscountTotal += $lineDiscount;
            }

            $subtotal = round($subtotal, 2);
            $netBeforeGeneral = round($subtotal - $lineDiscountTotal, 2);

            // 2) Genel indirim (yönetici dışında %10 sınırı)
            $generalDiscount = round((float) ($data['discount'] ?? 0), 2);

            if ($generalDiscount < 0 || $generalDiscount > $netBeforeGeneral) {
                throw ValidationException::withMessages([
                    'discount' => 'Genel indirim 0 ile ara toplam arasında olmalıdır.',
                ]);
            }

            if (! $isAdmin && $subtotal > 0
                && ((($lineDiscountTotal + $generalDiscount) / $subtotal) * 100) > self::CASHIER_MAX_DISCOUNT_PERCENT) {
                throw ValidationException::withMessages([
                    'discount' => 'Toplam indirim en fazla %' . self::CASHIER_MAX_DISCOUNT_PERCENT
                        . ' olabilir. Fazlası için yönetici onayı gerekir.',
                ]);
            }

            // Genel indirimi satırlara orantılı dağıt (kâr hesabı doğru kalsın)
            $remainingDiscount = $generalDiscount;
            $lastIndex = count($lines) - 1;

            foreach ($lines as $i => &$line) {
                $share = $i === $lastIndex
                    ? $remainingDiscount
                    : ($netBeforeGeneral > 0 ? round($generalDiscount * $line['net'] / $netBeforeGeneral, 2) : 0.0);

                $remainingDiscount = round($remainingDiscount - $share, 2);

                $line['total_price'] = round($line['net'] - $share, 2);
                $line['profit'] = round($line['total_price'] - $line['quantity'] * $line['purchase_price'], 2);
                // Fiyatlar KDV dahil kabul edilir.
                $line['vat_amount'] = round($line['total_price'] * $line['vat_rate'] / (100 + $line['vat_rate']), 2);
            }
            unset($line);

            $total = round($netBeforeGeneral - $generalDiscount, 2);
            $vatTotal = round(array_sum(array_column($lines, 'vat_amount')), 2);

            // 3) Ödemeleri doğrula
            $customerId = $data['customer_id'] ?? null;
            [$paidTotal, $change, $appliedPayments] = $this->resolvePayments($payments, $total, $customerId);

            // 4) Satışı oluştur
            $sale = Sale::create([
                'sale_no' => $this->generateSaleNo(),
                'user_id' => $user->id,
                'customer_id' => $customerId,
                'sale_date' => now(),
                'subtotal' => $subtotal,
                'discount' => round($lineDiscountTotal + $generalDiscount, 2),
                'vat_total' => $vatTotal,
                'total' => $total,
                'paid_total' => $paidTotal,
                'change_amount' => $change,
                'status' => 'completed',
                'note' => $data['note'] ?? null,
            ]);

            // 5) Kalemler ve stok çıkışı
            foreach ($lines as $line) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $line['product']->id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'purchase_price' => $line['purchase_price'],
                    'discount' => round($line['unit_price'] * $line['quantity'] - $line['total_price'], 2),
                    'vat_rate' => $line['vat_rate'],
                    'total_price' => $line['total_price'],
                    'profit' => $line['profit'],
                ]);

                // Yetersiz stokta ValidationException fırlatır ve tüm satış geri alınır
                $this->stockService->removeForSale(
                    $line['product'],
                    $line['quantity'],
                    $sale->id,
                    $user->id,
                    "Satış: {$sale->sale_no}"
                );
            }

            // 6) Ödeme kayıtları
            foreach ($appliedPayments as $payment) {
                Payment::create([
                    'sale_id' => $sale->id,
                    'customer_id' => $payment['type'] === 'credit' ? $customerId : null,
                    'user_id' => $user->id,
                    'payment_type' => $payment['type'],
                    'amount' => $payment['amount'],
                    'payment_date' => now(),
                ]);
            }

            // 7) Veresiye: müşteri bakiyesi artar
            $creditAmount = collect($appliedPayments)->where('type', 'credit')->sum('amount');

            if ($creditAmount > 0) {
                $customer = Customer::query()->lockForUpdate()->findOrFail($customerId);
                $customer->balance = round((float) $customer->balance + $creditAmount, 2);
                $customer->save();
            }

            return $sale->load(['items', 'payments']);
        });
    }

    /**
     * Ödemeleri doğrular. Para üstü yalnızca nakit ödemeden düşülür;
     * payments tablosuna para üstü düşülmüş (gerçek) tutar yazılır.
     *
     * @return array{0: float, 1: float, 2: array<int, array{type: string, amount: float}>}
     */
    private function resolvePayments(array $payments, float $total, ?int $customerId): array
    {
        $applied = [];
        $paidTotal = 0.0;
        $cashTotal = 0.0;
        $creditTotal = 0.0;

        foreach ($payments as $payment) {
            $amount = round((float) $payment['amount'], 2);
            $type = $payment['payment_type'];

            $paidTotal += $amount;

            if ($type === 'cash') {
                $cashTotal += $amount;
            }

            if ($type === 'credit') {
                $creditTotal += $amount;
            }

            $applied[] = ['type' => $type, 'amount' => $amount];
        }

        $paidTotal = round($paidTotal, 2);

        if ($creditTotal > 0 && ! $customerId) {
            throw ValidationException::withMessages([
                'customer_id' => 'Veresiye satışta müşteri seçimi zorunludur.',
            ]);
        }

        if ($paidTotal < $total) {
            throw ValidationException::withMessages([
                'payments' => 'Ödenen tutar satış toplamından az olamaz.',
            ]);
        }

        $change = round($paidTotal - $total, 2);

        if ($change > round($cashTotal, 2)) {
            throw ValidationException::withMessages([
                'payments' => 'Para üstü yalnızca nakit ödemeden verilebilir.',
            ]);
        }

        // Para üstünü nakit ödemelerden düş
        $toDeduct = $change;

        foreach ($applied as &$row) {
            if ($row['type'] === 'cash' && $toDeduct > 0) {
                $deduct = min($row['amount'], $toDeduct);
                $row['amount'] = round($row['amount'] - $deduct, 2);
                $toDeduct = round($toDeduct - $deduct, 2);
            }
        }
        unset($row);

        $applied = array_values(array_filter($applied, fn ($row) => $row['amount'] > 0));

        return [$paidTotal, $change, $applied];
    }

    /**
     * Fiş numarası üretir. Transaction içinde çağrılmalıdır. Örnek: S-2026-000015
     */
    private function generateSaleNo(): string
    {
        $next = (int) Sale::withTrashed()->lockForUpdate()->max('id') + 1;

        return sprintf('S-%s-%06d', now()->format('Y'), $next);
    }

    private function assertItems(array $items): void
    {
        if (count($items) === 0) {
            throw ValidationException::withMessages([
                'items' => 'Sepet boş. Satış için en az bir ürün ekleyin.',
            ]);
        }

        foreach ($items as $index => $item) {
            if (empty($item['product_id'])) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => 'Ürün seçimi zorunludur.',
                ]);
            }

            if (! isset($item['quantity']) || (float) $item['quantity'] <= 0) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => 'Miktar sıfırdan büyük olmalıdır.',
                ]);
            }
        }
    }

    private function assertPayments(array $payments): void
    {
        if (count($payments) === 0) {
            throw ValidationException::withMessages([
                'payments' => 'En az bir ödeme girilmelidir.',
            ]);
        }

        foreach ($payments as $index => $payment) {
            if (! in_array($payment['payment_type'] ?? null, self::PAYMENT_TYPES, true)) {
                throw ValidationException::withMessages([
                    "payments.{$index}.payment_type" => 'Geçersiz ödeme türü.',
                ]);
            }

            if (! isset($payment['amount']) || (float) $payment['amount'] <= 0) {
                throw ValidationException::withMessages([
                    "payments.{$index}.amount" => 'Ödeme tutarı sıfırdan büyük olmalıdır.',
                ]);
            }
        }
    }
}