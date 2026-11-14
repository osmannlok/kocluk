<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(private readonly StockService $stockService)
    {
    }

    /**
     * Tedarikçi alımı oluşturur, stoğu artırır ve tedarikçi bakiyesini günceller.
     *
     * $data: supplier_id?, purchase_date?, paid_amount?, note?
     * $items: [['product_id' => 1, 'quantity' => 10, 'unit_price' => 250.00], ...]
     */
    public function createPurchase(array $data, array $items, ?int $userId = null): Purchase
    {
        $this->assertItems($items);

        return DB::transaction(function () use ($data, $items, $userId) {
            // Satırları hesapla
            $lines = [];
            $total = 0.0;

            foreach ($items as $item) {
                $quantity = round((float) $item['quantity'], 3);
                $unitPrice = round((float) $item['unit_price'], 2);
                $lineTotal = round($quantity * $unitPrice, 2);

                $lines[] = [
                    'product_id' => (int) $item['product_id'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $lineTotal,
                ];

                $total += $lineTotal;
            }

            $total = round($total, 2);
            $paid = round((float) ($data['paid_amount'] ?? 0), 2);

            if ($paid < 0 || $paid > $total) {
                throw ValidationException::withMessages([
                    'paid_amount' => 'Ödenen tutar 0 ile toplam tutar arasında olmalıdır.',
                ]);
            }

            $remaining = round($total - $paid, 2);

            $purchase = Purchase::create([
                'purchase_no' => $this->generatePurchaseNo(),
                'supplier_id' => $data['supplier_id'] ?? null,
                'user_id' => $userId,
                'purchase_date' => $data['purchase_date'] ?? now()->toDateString(),
                'total_amount' => $total,
                'paid_amount' => $paid,
                'remaining_amount' => $remaining,
                'status' => 'completed',
                'note' => $data['note'] ?? null,
            ]);

            foreach ($lines as $line) {
                PurchaseItem::create(array_merge($line, ['purchase_id' => $purchase->id]));

                // Stok artışı + stock_movements kaydı (aynı transaction içinde)
                $this->stockService->addPurchase(
                    $line['product_id'],
                    $line['quantity'],
                    $userId,
                    $purchase->id,
                    "Alım: {$purchase->purchase_no}"
                );

                // Son alış fiyatını ürüne yaz (satış anı maliyeti buradan okunur)
                Product::query()
                    ->whereKey($line['product_id'])
                    ->update(['purchase_price' => $line['unit_price']]);
            }

            // Kalan tutar kadar tedarikçiye borçlanılır
            if ($purchase->supplier_id && $remaining > 0) {
                $supplier = Supplier::query()->lockForUpdate()->findOrFail($purchase->supplier_id);
                $supplier->balance = round((float) $supplier->balance + $remaining, 2);
                $supplier->save();
            }

            return $purchase->load('items');
        });
    }

    /**
     * Alım fiş numarası üretir. Transaction içinde çağrılmalıdır. Örnek: A-2026-000015
     */
    private function generatePurchaseNo(): string
    {
        $next = (int) Purchase::withTrashed()->lockForUpdate()->max('id') + 1;

        return sprintf('A-%s-%06d', now()->format('Y'), $next);
    }

    private function assertItems(array $items): void
    {
        if (count($items) === 0) {
            throw ValidationException::withMessages([
                'items' => 'Alım için en az bir ürün eklenmelidir.',
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

            if (! isset($item['unit_price']) || (float) $item['unit_price'] < 0) {
                throw ValidationException::withMessages([
                    "items.{$index}.unit_price" => 'Birim fiyat eksi olamaz.',
                ]);
            }
        }
    }
}