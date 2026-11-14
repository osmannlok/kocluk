<?php
// app/Services/ReportService.php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Helpers\Money;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Dashboard ve rapor verileri. Para hesabı tamsayı kuruşla yapılır (float yok).
 * Neden izin bayrakları: report.view olmayan kullanıcıya ciro verisi hiç dönmez;
 * diğer bölümler de kendi yetkisine göre doldurulur (en az yetki ilkesi).
 */
final class ReportService
{
    private const CHART_MAX_DAYS = 90;

    public function __construct(
        private readonly Order $orders = new Order(),
        private readonly OrderItem $orderItems = new OrderItem(),
        private readonly ProductVariant $variants = new ProductVariant(),
        private readonly Payment $payments = new Payment(),
        private readonly ContactMessage $messages = new ContactMessage(),
        private readonly User $users = new User(),
        private readonly Role $roles = new Role(),
    ) {
    }

    /**
     * @return array{
     *   flags: array{report:bool,orders:bool,stock:bool,payments:bool,customers:bool},
     *   revenue: ?array{today:string,yesterday:string,last7:string,last30:string,orders_30:int},
     *   avg_basket: ?string,
     *   orders: ?array{pending_payment:int,to_prepare:int,shipped:int},
     *   new_users: ?int,
     *   pending_bank: ?int,
     *   low_stock: ?int,
     *   new_messages: ?int
     * }
     */
    public function dashboardStats(): array
    {
        $flags = $this->flags();

        $stats = [
            'flags' => $flags,
            'revenue' => null,
            'avg_basket' => null,
            'orders' => null,
            'new_users' => null,
            'pending_bank' => null,
            'low_stock' => null,
            'new_messages' => null,
        ];

        if ($flags['report']) {
            $summary = $this->revenueSummary();
            $stats['revenue'] = $summary['revenue'];
            $stats['avg_basket'] = $summary['avg_basket'];
        }

        if ($flags['orders']) {
            $byStatus = $this->orders->countByStatus();
            $stats['orders'] = [
                'pending_payment' => (int) ($byStatus['pending_payment'] ?? 0),
                // "Hazırlanacak": ödemesi alınmış, henüz hazırlığa başlanmamış siparişler.
                'to_prepare' => (int) ($byStatus['paid'] ?? 0),
                'shipped' => (int) ($byStatus['shipped'] ?? 0),
            ];
        }

        if ($flags['customers']) {
            $stats['new_users'] = $this->newCustomersLast7Days();
            $stats['new_messages'] = $this->messages->countNew();
        }

        if ($flags['payments']) {
            $pending = $this->payments->pendingBankTransfers(1, 1);
            $stats['pending_bank'] = (int) ($pending['total'] ?? 0);
        }

        if ($flags['stock']) {
            $stats['low_stock'] = count($this->variants->lowStock());
        }

        return $stats;
    }

    /**
     * Günlük satış grafiği verisi. Eksik günler 0 ile doldurulur.
     * revenue değerleri "1234.50" biçiminde string'dir.
     *
     * @return array{labels:list<string>,revenue:list<string>,orders:list<int>}
     */
    public function salesChart(int $days): array
    {
        $result = ['labels' => [], 'revenue' => [], 'orders' => []];

        if (!Auth::can('report.view')) {
            return $result;
        }

        $days = max(1, min(self::CHART_MAX_DAYS, $days));

        $byDate = [];
        foreach ($this->orders->salesByDay($days) as $row) {
            $byDate[(string) ($row['sale_date'] ?? '')] = $row;
        }

        $today = new DateTimeImmutable('today');
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = $today->modify('-' . $i . ' days');
            $row = $byDate[$day->format('Y-m-d')] ?? null;

            $result['labels'][] = $day->format('d.m');
            $result['revenue'][] = $row !== null ? $this->moneyString($row['total'] ?? null) : '0.00';
            $result['orders'][] = $row !== null ? (int) ($row['order_count'] ?? 0) : 0;
        }

        return $result;
    }

    /**
     * En çok satanlar. Ciro içerdiği için report.view yoksa boş döner.
     *
     * @return list<array{variant_id:int,sku:string,product_name:string,variant_name:string,total_quantity:int,total_revenue:string}>
     */
    public function topProducts(int $days, int $limit): array
    {
        if (!Auth::can('report.view')) {
            return [];
        }

        $days = max(1, min(365, $days));
        $limit = max(1, min(50, $limit));

        $rows = [];
        foreach ($this->orderItems->topProducts($days, $limit) as $row) {
            $rows[] = [
                'variant_id' => (int) ($row['variant_id'] ?? 0),
                'sku' => (string) ($row['sku'] ?? ''),
                'product_name' => (string) ($row['product_name'] ?? ''),
                'variant_name' => (string) ($row['variant_name'] ?? ''),
                'total_quantity' => (int) ($row['total_quantity'] ?? 0),
                'total_revenue' => $this->moneyString($row['total_revenue'] ?? null),
            ];
        }

        return $rows;
    }

    /**
     * Kritik stok satırları (v_low_stock). Yetki: stok/ürün görüntüleme.
     *
     * @return list<array<string,mixed>>
     */
    public function lowStock(int $limit): array
    {
        if (!$this->flags()['stock']) {
            return [];
        }

        return array_slice($this->variants->lowStock(), 0, max(1, min(100, $limit)));
    }

    /**
     * Son siparişler. Yetki: order.view.
     *
     * @return list<array<string,mixed>>
     */
    public function recentOrders(int $limit): array
    {
        if (!Auth::can('order.view')) {
            return [];
        }

        $result = $this->orders->adminList([], 1, max(1, min(50, $limit)));

        return is_array($result['items'] ?? null) ? $result['items'] : [];
    }

    /** @return array{report:bool,orders:bool,stock:bool,payments:bool,customers:bool} */
    private function flags(): array
    {
        return [
            'report' => Auth::can('report.view'),
            'orders' => Auth::can('order.view'),
            'stock' => Auth::can('stock.manage') || Auth::can('product.view'),
            'payments' => Auth::can('payment.confirm'),
            'customers' => Auth::can('customer.view'),
        ];
    }

    /**
     * Bugün/dün/7/30 gün ciro ve ortalama sepet (kuruş toplamı).
     *
     * @return array{revenue:array{today:string,yesterday:string,last7:string,last30:string,orders_30:int},avg_basket:string}
     */
    private function revenueSummary(): array
    {
        $today = new DateTimeImmutable('today');
        $todayKey = $today->format('Y-m-d');
        $yesterdayKey = $today->modify('-1 day')->format('Y-m-d');
        $weekStart = $today->modify('-6 days')->format('Y-m-d');
        $monthStart = $today->modify('-29 days')->format('Y-m-d');

        $cents = ['today' => 0, 'yesterday' => 0, 'last7' => 0, 'last30' => 0];
        $orderCount = 0;

        foreach ($this->orders->salesByDay(30) as $row) {
            $date = (string) ($row['sale_date'] ?? '');
            if ($date < $monthStart || $date > $todayKey) {
                continue;
            }

            $amount = $this->toCents($row['total'] ?? null);
            $cents['last30'] += $amount;
            $orderCount += (int) ($row['order_count'] ?? 0);

            if ($date >= $weekStart) {
                $cents['last7'] += $amount;
            }
            if ($date === $todayKey) {
                $cents['today'] += $amount;
            }
            if ($date === $yesterdayKey) {
                $cents['yesterday'] += $amount;
            }
        }

        // Ortalama sepet: tamsayı bölme, yarım yukarı yuvarlama.
        $average = $orderCount > 0
            ? intdiv($cents['last30'] * 2 + $orderCount, $orderCount * 2)
            : 0;

        return [
            'revenue' => [
                'today' => Money::fromCents($cents['today']),
                'yesterday' => Money::fromCents($cents['yesterday']),
                'last7' => Money::fromCents($cents['last7']),
                'last30' => Money::fromCents($cents['last30']),
                'orders_30' => $orderCount,
            ],
            'avg_basket' => Money::fromCents($average),
        ];
    }

    /** Son 7 günde kayıt olan müşteri sayısı. */
    private function newCustomersLast7Days(): int
    {
        $since = (new DateTimeImmutable('today'))->modify('-6 days')->format('Y-m-d H:i:s');
        $where = ['created_at' => ['>=', $since]];

        $customerRole = $this->roles->findByName('customer');
        if ($customerRole !== null) {
            $where['role_id'] = (int) $customerRole['id'];
        }

        return $this->users->count($where);
    }

    private function toCents(mixed $value): int
    {
        $string = $value === null ? '0' : trim((string) $value);
        if ($string === '') {
            $string = '0';
        }

        try {
            return Money::toCents($string);
        } catch (InvalidArgumentException) {
            return 0;
        }
    }

    private function moneyString(mixed $value): string
    {
        return Money::fromCents($this->toCents($value));
    }
}