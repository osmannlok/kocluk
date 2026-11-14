<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const MAX_RANGE_DAYS = 366;

    public function __construct(private readonly ReportService $reportService)
    {
    }

    /**
     * Dashboard / rapor ekranı.
     * GET /admin/reports
     */
    public function index(Request $request): View
    {
        $this->authorizeView($request);

        [$period, $from, $to] = $this->resolveFilters($request);

        $report = $this->reportService->fullReport($from, $to, $this->scopedUserId($request));

        // Kasiyer yalnızca kendi satış raporunu görür; stok, fire ve iade bilgisi yönetime aittir
        if ($this->isCashier($request)) {
            $report['low_stock'] = collect();
            $report['damage_returns'] = null;
        }

        return view('admin.reports.index', [
            'report' => $report,
            'period' => $period,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'isCashier' => $this->isCashier($request),
        ]);
    }

    /**
     * Raporu CSV olarak indirir (Excel'de açılır).
     * GET /admin/reports/export?type=products&period=daily
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorizeView($request);

        $allowedTypes = $this->isCashier($request)
            ? ['summary', 'products']
            : ['summary', 'products', 'cashiers', 'low_stock'];

        $type = (string) $request->query('type', 'summary');

        abort_unless(in_array($type, $allowedTypes, true), 404);

        [, $from, $to] = $this->resolveFilters($request);

        $userId = $this->scopedUserId($request);
        [$header, $rows] = $this->buildExport($type, $from, $to, $userId);

        $filename = sprintf('rapor-%s-%s_%s.csv', $type, $from->toDateString(), $to->toDateString());

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');

            // Excel'in Türkçe karakterleri doğru açması için UTF-8 BOM
            fwrite($out, "\xEF\xBB\xBF");

            // Türkçe Excel noktalı virgül ayırıcı bekler
            fputcsv($out, $header, ';');

            foreach ($rows as $row) {
                fputcsv($out, array_map([$this, 'safeCell'], $row), ';');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * CSV başlık ve satırlarını hazırlar.
     *
     * @return array{0: array<int, string>, 1: array<int, array<int, mixed>>}
     */
    private function buildExport(string $type, $from, $to, ?int $userId): array
    {
        switch ($type) {
            case 'products':
                $rows = $this->reportService->productSales($from, $to, $userId)
                    ->map(fn ($p) => [
                        $p['name'],
                        $p['unit'],
                        $this->num($p['quantity'], 3),
                        $this->num($p['revenue']),
                        $this->num($p['profit']),
                    ])->all();

                return [['Ürün', 'Birim', 'Satış Adedi', 'Ciro (₺)', 'Kâr (₺)'], $rows];

            case 'cashiers':
                $rows = $this->reportService->cashierPerformance($from, $to)
                    ->map(fn ($c) => [
                        $c['name'],
                        $c['sale_count'],
                        $this->num($c['revenue']),
                        $this->num($c['profit']),
                        $this->num($c['average_basket']),
                    ])->all();

                return [['Kasiyer', 'Satış Sayısı', 'Ciro (₺)', 'Kâr (₺)', 'Ortalama Sepet (₺)'], $rows];

            case 'low_stock':
                $rows = $this->reportService->lowStockProducts()
                    ->map(fn ($p) => [
                        $p->name,
                        $p->unit,
                        $this->num($p->stock, 3),
                        $this->num($p->critical_stock, 3),
                    ])->all();

                return [['Ürün', 'Birim', 'Stok', 'Kritik Seviye'], $rows];

            default: // summary
                $summary = $this->reportService->summary($from, $to, $userId);
                $payments = $this->reportService->paymentBreakdown($from, $to, $userId);

                $rows = [
                    ['Satış Sayısı', $summary['sale_count']],
                    ['Toplam Ciro (₺)', $this->num($summary['revenue'])],
                    ['Toplam İndirim (₺)', $this->num($summary['discount_total'])],
                    ['Toplam Kâr (₺)', $this->num($summary['profit'])],
                    ['Ortalama Sepet (₺)', $this->num($summary['average_basket'])],
                    ['Nakit (₺)', $this->num($payments['cash'])],
                    ['Kart (₺)', $this->num($payments['card'])],
                    ['Havale/EFT (₺)', $this->num($payments['transfer'])],
                    ['Veresiye (₺)', $this->num($payments['credit'])],
                ];

                return [['Kalem', 'Değer'], $rows];
        }
    }

    /**
     * Tarih filtresini doğrular ve aralığı üretir.
     *
     * @return array{0: string, 1: \Carbon\Carbon, 2: \Carbon\Carbon}
     */
    private function resolveFilters(Request $request): array
    {
        $data = $request->validate([
            'period' => ['nullable', 'in:daily,weekly,monthly,custom'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ], [
            'period.in' => 'Geçersiz rapor dönemi.',
            'from.date' => 'Başlangıç tarihi geçersiz.',
            'to.date' => 'Bitiş tarihi geçersiz.',
            'to.after_or_equal' => 'Bitiş tarihi başlangıç tarihinden önce olamaz.',
        ]);

        // Tarih girilmişse ve dönem seçilmemişse özel aralık kabul edilir
        $period = $data['period'] ?? (isset($data['from']) ? 'custom' : 'daily');

        [$from, $to] = $this->reportService->resolveRange($period, $data['from'] ?? null, $data['to'] ?? null);

        if ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages([
                'to' => 'Tarih aralığı en fazla ' . self::MAX_RANGE_DAYS . ' gün olabilir.',
            ]);
        }

        return [$period, $from, $to];
    }

    /**
     * Raporları yönetici, muhasebe ve (yalnızca kendi satışları için) kasiyer görür.
     */
    private function authorizeView(Request $request): void
    {
        abort_unless(
            in_array($request->user()?->role, ['admin', 'accountant', 'cashier'], true),
            403,
            'Bu işlem için yetkiniz yok.'
        );
    }

    private function isCashier(Request $request): bool
    {
        return $request->user()?->role === 'cashier';
    }

    /**
     * Kasiyer için kendi kimliği, diğerleri için null (tüm satışlar).
     */
    private function scopedUserId(Request $request): ?int
    {
        return $this->isCashier($request) ? $request->user()->id : null;
    }

    /**
     * Sayıyı Türkçe Excel için ondalık virgülle yazar.
     */
    private function num(float|int|string $value, int $decimals = 2): string
    {
        return number_format((float) $value, $decimals, ',', '');
    }

    /**
     * CSV formül enjeksiyonunu önler: =, +, -, @ ile başlayan metinlerin önüne ' koyar.
     * Sayısal (virgüllü sayı) değerlere dokunmaz.
     */
    private function safeCell(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && ! preg_match('/^-?\d+(,\d+)?$/', $value)
            && in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "'" . $value;
        }

        return $value;
    }
}