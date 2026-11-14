<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Raporlar</h2>
    </x-slot>

    @php
        $fmtMoney = fn ($v) => number_format((float) $v, 2, ',', '.') . ' ₺';
        $fmtQty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');
        $summary = $report['summary'];
        $payments = $report['payments'];
        $paymentLabels = [
            'cash' => 'Nakit',
            'card' => 'Kart',
            'transfer' => 'Havale/EFT',
            'credit' => 'Veresiye',
            'refund' => 'İade',
        ];
        $exportQuery = ['period' => $period, 'from' => $from, 'to' => $to];
    @endphp

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Hata mesajları --}}
            @if ($errors->any())
                <div class="rounded-md bg-red-50 border border-red-200 p-4 text-red-800">
                    {{ $errors->first() }}
                </div>
            @endif

            @if ($isCashier)
                <div class="rounded-md bg-blue-50 border border-blue-200 p-4 text-blue-800">
                    Bu raporda yalnızca kendi satışlarınız gösterilir.
                </div>
            @endif

            {{-- Tarih filtresi --}}
            <div class="bg-white shadow sm:rounded-lg p-4">
                <form method="GET" action="{{ route('admin.reports.index') }}" class="flex flex-wrap items-end gap-4">
                    <div>
                        <label for="period" class="block text-sm font-medium text-gray-700">Dönem</label>
                        <select id="period" name="period"
                                class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                            <option value="daily" @selected($period === 'daily')>Bugün</option>
                            <option value="weekly" @selected($period === 'weekly')>Bu hafta</option>
                            <option value="monthly" @selected($period === 'monthly')>Bu ay</option>
                            <option value="custom" @selected($period === 'custom')>Özel aralık</option>
                        </select>
                    </div>
                    <div>
                        <label for="from" class="block text-sm font-medium text-gray-700">Başlangıç</label>
                        <input id="from" type="date" name="from" value="{{ $from }}"
                               class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                    </div>
                    <div>
                        <label for="to" class="block text-sm font-medium text-gray-700">Bitiş</label>
                        <input id="to" type="date" name="to" value="{{ $to }}"
                               class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                    </div>
                    <button type="submit"
                            class="px-4 py-2 bg-green-600 text-white text-sm font-semibold rounded-md hover:bg-green-700">
                        Raporu Getir
                    </button>
                </form>
                <p class="mt-2 text-xs text-gray-500">
                    "Özel aralık" dışındaki dönemlerde tarih alanları yok sayılır.
                    Gösterilen aralık: {{ \Carbon\Carbon::parse($report['from'])->format('d.m.Y') }} - {{ \Carbon\Carbon::parse($report['to'])->format('d.m.Y') }}
                </p>
            </div>

            {{-- Özet kartları --}}
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="bg-white shadow sm:rounded-lg p-4">
                    <p class="text-sm text-gray-500">Toplam Ciro</p>
                    <p class="text-2xl font-bold text-green-700">{{ $fmtMoney($summary['revenue']) }}</p>
                </div>
                <div class="bg-white shadow sm:rounded-lg p-4">
                    <p class="text-sm text-gray-500">Toplam Kâr</p>
                    <p class="text-2xl font-bold text-green-700">{{ $fmtMoney($summary['profit']) }}</p>
                </div>
                <div class="bg-white shadow sm:rounded-lg p-4">
                    <p class="text-sm text-gray-500">Satış Sayısı</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $summary['sale_count'] }}</p>
                </div>
                <div class="bg-white shadow sm:rounded-lg p-4">
                    <p class="text-sm text-gray-500">Ortalama Sepet</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $fmtMoney($summary['average_basket']) }}</p>
                </div>
                <div class="bg-white shadow sm:rounded-lg p-4">
                    <p class="text-sm text-gray-500">Toplam İndirim</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $fmtMoney($summary['discount_total']) }}</p>
                </div>
            </div>

            {{-- Ödeme kırılımı ve grafik --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-white shadow sm:rounded-lg p-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Ödeme Türü Kırılımı</h3>
                    <table class="min-w-full text-sm">
                        <tbody class="divide-y divide-gray-200">
                            @foreach ($paymentLabels as $key => $label)
                                <tr>
                                    <td class="py-2">{{ $label }}</td>
                                    <td class="py-2 text-right font-semibold">{{ $fmtMoney($payments[$key] ?? 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="bg-white shadow sm:rounded-lg p-4 lg:col-span-2">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Günlük Ciro ve Kâr</h3>
                    <div class="relative h-64">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>

            {{-- En çok satan ürünler --}}
            <div class="bg-white shadow sm:rounded-lg p-4">
                <h3 class="text-lg font-medium text-gray-900 mb-3">En Çok Satan Ürünler</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ürün</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Adet</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Ciro</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse ($report['top_products'] as $row)
                                <tr>
                                    <td class="px-3 py-2">{{ $row['name'] }}</td>
                                    <td class="px-3 py-2 text-right">{{ $fmtQty($row['quantity']) }} {{ $row['unit'] }}</td>
                                    <td class="px-3 py-2 text-right">{{ $fmtMoney($row['revenue']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-3 py-6 text-center text-gray-500">Bu aralıkta satış yok.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Ürün bazlı satış --}}
            <div class="bg-white shadow sm:rounded-lg p-4">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <h3 class="text-lg font-medium text-gray-900">Ürün Bazlı Satış, Ciro ve Kâr</h3>
                    <a href="{{ route('admin.reports.export', $exportQuery + ['type' => 'products']) }}"
                       class="px-3 py-1 text-sm font-medium text-green-700 bg-green-50 rounded-md hover:bg-green-100">CSV İndir</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ürün</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Adet</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Ciro</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Kâr</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse ($report['products'] as $row)
                                <tr>
                                    <td class="px-3 py-2">{{ $row['name'] }}</td>
                                    <td class="px-3 py-2 text-right">{{ $fmtQty($row['quantity']) }} {{ $row['unit'] }}</td>
                                    <td class="px-3 py-2 text-right">{{ $fmtMoney($row['revenue']) }}</td>
                                    <td class="px-3 py-2 text-right">{{ $fmtMoney($row['profit']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-3 py-6 text-center text-gray-500">Bu aralıkta satış yok.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @unless ($isCashier)
                {{-- Kcritik stok --}}
                <div class="bg-white shadow sm:rounded-lg p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <h3 class="text-lg font-medium text-red-700">Kritik Stoktaki Ürünler</h3>
                        <a href="{{ route('admin.reports.export', $exportQuery + ['type' => 'low_stock']) }}"
                           class="px-3 py-1 text-sm font-medium text-green-700 bg-green-50 rounded-md hover:bg-green-100">CSV İndir</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ürün</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Stok</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Kritik Seviye</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse ($report['low_stock'] as $p)
                                    <tr class="bg-red-50">
                                        <td class="px-3 py-2">{{ $p->name }}</td>
                                        <td class="px-3 py-2 text-right">{{ $fmtQty($p->stock) }} {{ $p->unit }}</td>
                                        <td class="px-3 py-2 text-right">{{ $fmtQty($p->critical_stock) }} {{ $p->unit }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="px-3 py-6 text-center text-gray-500">Kritik seviyenin altında ürün yok.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Fire ve iade --}}
                @if ($report['damage_returns'])
                    <div class="bg-white shadow sm:rounded-lg p-4">
                        <h3 class="text-lg font-medium text-gray-900 mb-1">Fire ve İade Toplamları</h3>
                        <p class="text-xs text-gray-500 mb-3">Maliyet, ürünlerin güncel alış fiyatıyla hesaplanan tahmindir.</p>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">İşlem</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Kayıt</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Miktar</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Tahmini Maliyet</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach (['damage' => 'Fire', 'return' => 'İade'] as $key => $label)
                                        <tr>
                                            <td class="px-3 py-2">{{ $label }}</td>
                                            <td class="px-3 py-2 text-right">{{ $report['damage_returns'][$key]['record_count'] }}</td>
                                            <td class="px-3 py-2 text-right">{{ $fmtQty($report['damage_returns'][$key]['quantity']) }}</td>
                                            <td class="px-3 py-2 text-right">{{ $fmtMoney($report['damage_returns'][$key]['estimated_cost']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- Kasiyer performansı --}}
                <div class="bg-white shadow sm:rounded-lg p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <h3 class="text-lg font-medium text-gray-900">Kasiyer Performansı</h3>
                        <a href="{{ route('admin.reports.export', $exportQuery + ['type' => 'cashiers']) }}"
                           class="px-3 py-1 text-sm font-medium text-green-700 bg-green-50 rounded-md hover:bg-green-100">CSV İndir</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Kasiyer</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Satış</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Ciro</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Kâr</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Ort. Sepet</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse ($report['cashiers'] as $c)
                                    <tr>
                                        <td class="px-3 py-2">{{ $c['name'] }}</td>
                                        <td class="px-3 py-2 text-right">{{ $c['sale_count'] }}</td>
                                        <td class="px-3 py-2 text-right">{{ $fmtMoney($c['revenue']) }}</td>
                                        <td class="px-3 py-2 text-right">{{ $fmtMoney($c['profit']) }}</td>
                                        <td class="px-3 py-2 text-right">{{ $fmtMoney($c['average_basket']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500">Bu aralıkta satış yok.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endunless

            {{-- Özet CSV --}}
            <div>
                <a href="{{ route('admin.reports.export', $exportQuery + ['type' => 'summary']) }}"
                   class="inline-flex px-4 py-2 text-sm font-semibold text-green-700 bg-green-50 rounded-md hover:bg-green-100">
                    Özet Raporu CSV İndir
                </a>
            </div>
        </div>
    </div>

    {{-- Chart.js (CDN): dönem grafiği --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <script>
        (function () {
            const trend = @json($report['trend']);
            const canvas = document.getElementById('trendChart');

            if (!canvas || typeof Chart === 'undefined') return;

            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: trend.map(d => d.date.split('-').reverse().join('.')),
                    datasets: [
                        { label: 'Ciro (₺)', data: trend.map(d => d.revenue), backgroundColor: '#16a34a' },
                        { label: 'Kâr (₺)', data: trend.map(d => d.profit), backgroundColor: '#86efac' },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true } },
                },
            });
        })();
    </script>
</x-app-layout>