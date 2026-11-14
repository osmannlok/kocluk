<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Stok Yönetimi
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Başarı mesajı --}}
            @if (session('success'))
                <div class="rounded-md bg-green-50 border border-green-200 p-4 text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Bilgi mesajı --}}
            @if (session('info'))
                <div class="rounded-md bg-blue-50 border border-blue-200 p-4 text-blue-800">
                    {{ session('info') }}
                </div>
            @endif

            {{-- Kritik stok uyarısı --}}
            @if ($lowStockProducts->isNotEmpty())
                <div class="rounded-md bg-red-50 border border-red-200 p-4">
                    <h3 class="text-lg font-semibold text-red-800 mb-2">
                        Kritik Stok Uyarısı ({{ $lowStockProducts->count() }} ürün)
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-red-700">
                                    <th class="py-1 pr-4">Ürün</th>
                                    <th class="py-1 pr-4 text-right">Stok</th>
                                    <th class="py-1 text-right">Kritik Seviye</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-red-100 text-red-900">
                                @foreach ($lowStockProducts as $item)
                                    <tr>
                                        <td class="py-1 pr-4">{{ $item->name }}</td>
                                        <td class="py-1 pr-4 text-right">{{ rtrim(rtrim(number_format($item->stock, 3, ',', '.'), '0'), ',') }} {{ $item->unit }}</td>
                                        <td class="py-1 text-right">{{ rtrim(rtrim(number_format($item->critical_stock, 3, ',', '.'), '0'), ',') }} {{ $item->unit }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="rounded-md bg-green-50 border border-green-200 p-4 text-green-800">
                    Kritik seviyenin altında ürün yok.
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {{-- Fire formu --}}
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Fire / Bozulma Kaydı</h3>

                    <form method="POST" action="{{ route('admin.stock.damage') }}" class="space-y-4"
                          onsubmit="return confirm('Fire kaydı stoğu azaltacak. Onaylıyor musunuz?');">
                        @csrf

                        <div>
                            <label for="damage_product_id" class="block text-sm font-medium text-gray-700">
                                Ürün <span class="text-red-600">*</span>
                            </label>
                            <select id="damage_product_id" name="product_id" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                                <option value="">Ürün seçin</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}" @selected(old('product_id') == $product->id && old('_form') === 'damage')>
                                        {{ $product->name }} (Stok: {{ rtrim(rtrim(number_format($product->stock, 3, ',', '.'), '0'), ',') }} {{ $product->unit }})
                                    </option>
                                @endforeach
                            </select>
                            @error('product_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="damage_quantity" class="block text-sm font-medium text-gray-700">
                                Fire Miktarı <span class="text-red-600">*</span>
                            </label>
                            <input id="damage_quantity" name="quantity" type="number" step="0.001" min="0.001" required
                                   value="{{ old('quantity') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                            @error('quantity')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="damage_note" class="block text-sm font-medium text-gray-700">
                                Gerekçe <span class="text-red-600">*</span>
                            </label>
                            <textarea id="damage_note" name="note" rows="2" maxlength="500" required
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">{{ old('note') }}</textarea>
                            @error('note')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                            Fire Kaydet
                        </button>
                    </form>
                </div>

                {{-- Sayım formu --}}
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Stok Sayımı</h3>

                    <form method="POST" action="{{ route('admin.stock.count') }}" class="space-y-4">
                        @csrf

                        <div>
                            <label for="count_product_id" class="block text-sm font-medium text-gray-700">
                                Ürün <span class="text-red-600">*</span>
                            </label>
                            <select id="count_product_id" name="product_id" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                                <option value="">Ürün seçin</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}">
                                        {{ $product->name }} (Sistem: {{ rtrim(rtrim(number_format($product->stock, 3, ',', '.'), '0'), ',') }} {{ $product->unit }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="counted_stock" class="block text-sm font-medium text-gray-700">
                                Sayılan (Gerçek) Stok <span class="text-red-600">*</span>
                            </label>
                            <input id="counted_stock" name="counted_stock" type="number" step="0.001" min="0" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                            @error('counted_stock')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="count_note" class="block text-sm font-medium text-gray-700">
                                Fark Gerekçesi <span class="text-red-600">*</span>
                            </label>
                            <textarea id="count_note" name="note" rows="2" maxlength="500" required
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"></textarea>
                        </div>

                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-semibold rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                            Sayımı Kaydet
                        </button>
                    </form>
                </div>
            </div>

            {{-- Son stok hareketleri --}}
            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Son Stok Hareketleri</h3>

                @php
                    $typeLabels = [
                        'purchase' => 'Ürün girişi',
                        'sale' => 'Satış',
                        'return' => 'İade',
                        'damage' => 'Fire',
                        'correction' => 'Sayım düzeltmesi',
                        'initial' => 'Başlangıç stoğu',
                    ];
                @endphp

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tarih</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ürün</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">İşlem</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Miktar</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Önce</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Sonra</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Kullanıcı</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Not</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse ($movements as $movement)
                                <tr>
                                    <td class="px-3 py-2 whitespace-nowrap">{{ $movement->created_at?->format('d.m.Y H:i') }}</td>
                                    <td class="px-3 py-2">{{ $movement->product?->name ?? '-' }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap">{{ $typeLabels[$movement->movement_type] ?? $movement->movement_type }}</td>
                                    <td class="px-3 py-2 text-right whitespace-nowrap">{{ rtrim(rtrim(number_format($movement->quantity, 3, ',', '.'), '0'), ',') }}</td>
                                    <td class="px-3 py-2 text-right whitespace-nowrap">{{ rtrim(rtrim(number_format($movement->stock_before, 3, ',', '.'), '0'), ',') }}</td>
                                    <td class="px-3 py-2 text-right whitespace-nowrap">{{ rtrim(rtrim(number_format($movement->stock_after, 3, ',', '.'), '0'), ',') }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap">{{ $movement->user?->name ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ $movement->note }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-3 py-6 text-center text-gray-500">Henüz stok hareketi yok.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $movements->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>