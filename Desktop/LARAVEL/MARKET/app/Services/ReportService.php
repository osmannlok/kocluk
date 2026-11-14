<?php

namespace App\Services;

use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    private const PAYMENT_TYPES = ['cash', 'card', 'transfer', 'credit', 'refund'];

    /**
     * Dönem adına göre tarih aralığı üretir.
     * $period: daily | weekly | monthly | custom
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function resolveRange(string $period, ?string $from = null, ?string $to = null): array
    {
        return match ($period) {
            'weekly' => [now()->startOfWeek(), now()->endOfWeek()],
            'monthly' => [now()->startOfMonth(), now()->endOfMonth()],
            'custom' => [
                Carbon::parse($from ?? now()->toDateString())->startOfDay(),
                Carbon::parse($to ?? $from ?? now()->toDateString())->endOfDay(),
            ],
            default => [now()->startOfDay(), now()->endOfDay()],
        };
    }

    /**
     * Ciro, kâr, satış sayısı ve ortalama sepet. Yalnızca tamamlanan satışlar sayılır.
     * $userId verilirse yalnızca o kasiyerin satışları hesaplanır.
     */
    public function summary(Carbon $from, Carbon $to, ?int $userId = null): array
    {
        $sales = $this->salesQuery($from, $to, $userId)
            ->selectRaw('COUNT(*) as sale_count, COALESCE(SUM(sales.total), 0) as revenue, COALESCE(SUM(sales.discount), 0) as discount_total')
            ->first();

        $profit = $this->itemsQuery($from, $to, $userId)
            ->sum('sale_items.profit');

        $saleCount = (int) $sales->sale_count;
        $revenue = round((float) $sales->revenue, 2);

        return [
            'sale_count' => $saleCount,
            'revenue' => $revenue,
            'discount_total' => round((float) $sales->discount_total, 2),
            'profit' => round((float) $profit, 2),
            'average_basket' => $saleCount > 0 ? round($revenue / $saleCount, 2) : 0.0,
        ];
    }

    /**
     * Nakit, kart, havale, veresiye ve iade kırılımı.
     * payments tablosuna para üstü düşülmüş gerçek tutar yazıldığı için toplam ciroyla tutar.
     *
     * @return array<string, float>
     */
    public function paymentBreakdown(Carbon $from, Carbon $to, ?int $userId = null): array
    {
        $rows = DB::table('payments')
            ->join('sales', 'sales.id', '=', 'payments.sale_id')
            ->where('sales.status', 'completed')
            ->whereNull('sales.deleted_at')
            ->whereBetween('sales.sale_date', [$from, $to])
            ->when($userId, fn ($q) => $q->where('sales.user_id', $userId))
            ->groupBy('payments.payment_type')
            ->selectRaw('payments.payment_type, SUM(payments.amount) as total')
            ->pluck('total', 'payment_type');

        $result = [];

        foreach (self::PAYMENT_TYPES as $type) {
            $result[$type] = round((float) ($rows[$type] ?? 0), 2);
        }

        return $result;
    }

    /**
     * Ürün bazlı satış adedi, ciro ve kâr. Kâr sale_items.profit üzerinden hesaplanır.
     */
    public function productSales(Carbon $from, Carbon $to, ?int $userId = null, ?int $limit = null, string $orderBy = 'quantity'): Collection
    {
        $orderColumn = in_array($orderBy, ['quantity', 'revenue', 'profit'], true) ? $orderBy : 'quantity';

        return $this->itemsQuery($from, $to, $userId)
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->groupBy('sale_items.product_id', 'products.name', 'products.unit')
            ->selectRaw('sale_items.product_id, products.name, products.unit,
                SUM(sale_items.quantity) as quantity,
                SUM(sale_items.total_price) as revenue,
                SUM(sale_items.profit) as profit')
            ->orderByDesc($orderColumn)
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get()
            ->map(fn ($row) => [
                'product_id' => (int) $row->product_id,
                'name' => $row->name,
                'unit' => $row->unit,
                'quantity' => round((float) $row->quantity, 3),
                'revenue' => round((float) $row->revenue, 2),
                'profit' => round((float) $row->profit, 2),
            ]);
    }

    /**
     * En çok satan ürünler (adede göre).
     */
    public function topProducts(Carbon $from, Carbon $to, int $limit = 10, ?int $userId = null): Collection
    {
        return $this->productSales($from, $to, $userId, $limit, 'quantity');
    }

    /**
     * Kritik stoktaki aktif ürünler.
     */
    public function lowStockProducts(): Collection
    {
        return Product::query()
            ->lowStock()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'unit', 'stock', 'critical_stock']);
    }

    /**
     * Fire ve iade toplamları (miktar, kayıt sayısı, tahmini maliyet).
     * Maliyet, ürünün güncel alış fiyatıyla hesaplanan tahmindir.
     *
     * @return array{damage: array, return: array}
     */
    public function damageAndReturns(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('stock_movements')
            ->join('products', 'products.id', '=', 'stock_movements.product_id')
            ->whereIn('stock_movements.movement_type', ['damage', 'return'])
            ->whereBetween('stock_movements.created_at', [$from, $to])
            ->groupBy('stock_movements.movement_type')
            ->selectRaw('stock_movements.movement_type,
                COUNT(*) as record_count,
                SUM(stock_movements.quantity) as quantity,
                SUM(stock_movements.quantity * products.purchase_price) as estimated_cost')
            ->get()
            ->keyBy('movement_type');

        $format = fn ($row) => [
            'record_count' => (int) ($row->record_count ?? 0),
            'quantity' => round((float) ($row->quantity ?? 0), 3),
            'estimated_cost' => round((float) ($row->estimated_cost ?? 0), 2),
        ];

        return [
            'damage' => $format($rows->get('damage')),
            'return' => $format($rows->get('return')),
        ];
    }

    /**
     * Kasiyer bazlı satış performansı.
     */
    public function cashierPerformance(Carbon $from, Carbon $to): Collection
    {
        $sales = $this->salesQuery($from, $to)
            ->join('users', 'users.id', '=', 'sales.user_id')
            ->groupBy('sales.user_id', 'users.name')
            ->selectRaw('sales.user_id, users.name, COUNT(*) as sale_count, SUM(sales.total) as revenue')
            ->orderByDesc('revenue')
            ->get();

        $profits = $this->itemsQuery($from, $to)
            ->groupBy('sales.user_id')
            ->selectRaw('sales.user_id, SUM(sale_items.profit) as profit')
            ->pluck('profit', 'user_id');

        return $sales->map(function ($row) use ($profits) {
            $revenue = round((float) $row->revenue, 2);
            $count = (int) $row->sale_count;

            return [
                'user_id' => (int) $row->user_id,
                'name' => $row->name,
                'sale_count' => $count,
                'revenue' => $revenue,
                'profit' => round((float) ($profits[$row->user_id] ?? 0), 2),
                'average_basket' => $count > 0 ? round($revenue / $count, 2) : 0.0,
            ];
        });
    }

    /**
     * Grafik için gün gün ciro ve kâr (Chart.js).
     */
    public function dailyTrend(Carbon $from, Carbon $to, ?int $userId = null): Collection
    {
        $revenue = $this->salesQuery($from, $to, $userId)
            ->groupByRaw('DATE(sales.sale_date)')
            ->selectRaw('DATE(sales.sale_date) as day, SUM(sales.total) as revenue')
            ->pluck('revenue', 'day');

        $profit = $this->itemsQuery($from, $to, $userId)
            ->groupByRaw('DATE(sales.sale_date)')
            ->selectRaw('DATE(sales.sale_date) as day, SUM(sale_items.profit) as profit')
            ->pluck('profit', 'day');

        $days = collect();

        for ($date = $from->copy()->startOfDay(); $date->lte($to); $date->addDay()) {
            $key = $date->toDateString();

            $days->push([
                'date' => $key,
                'revenue' => round((float) ($revenue[$key] ?? 0), 2),
                'profit' => round((float) ($profit[$key] ?? 0), 2),
            ]);
        }

        return $days;
    }

    /**
     * Tek çağrıda tüm rapor verisi (dashboard ve rapor ekranı için).
     */
    public function fullReport(Carbon $from, Carbon $to, ?int $userId = null): array
    {
        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'summary' => $this->summary($from, $to, $userId),
            'payments' => $this->paymentBreakdown($from, $to, $userId),
            'products' => $this->productSales($from, $to, $userId),
            'top_products' => $this->topProducts($from, $to, 10, $userId),
            'low_stock' => $this->lowStockProducts(),
            'damage_returns' => $this->damageAndReturns($from, $to),
            'cashiers' => $userId ? collect() : $this->cashierPerformance($from, $to),
            'trend' => $this->dailyTrend($from, $to, $userId),
        ];
    }

    /**
     * Tamamlanan, silinmemiş satışlar.
     */
    private function salesQuery(Carbon $from, Carbon $to, ?int $userId = null)
    {
        return DB::table('sales')
            ->where('sales.status', 'completed')
            ->whereNull('sales.deleted_at')
            ->whereBetween('sales.sale_date', [$from, $to])
            ->when($userId, fn ($q) => $q->where('sales.user_id', $userId));
    }

    /**
     * Tamamlanan satışların kalemleri (sales ile birleşik).
     */
    private function itemsQuery(Carbon $from, Carbon $to, ?int $userId = null)
    {
        return DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'completed')
            ->whereNull('sales.deleted_at')
            ->whereBetween('sales.sale_date', [$from, $to])
            ->when($userId, fn ($q) => $q->where('sales.user_id', $userId));
    }
}