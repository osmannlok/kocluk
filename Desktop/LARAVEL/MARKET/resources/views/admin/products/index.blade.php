<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Ürünler
            </h2>
            <a href="{{ route('admin.products.create') }}"
               class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                + Yeni Ürün
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">

            {{-- Bilgi mesajları --}}
            @if (session('success'))
                <div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Arama ve filtreler --}}
            <form method="GET" action="{{ route('admin.products.index') }}"
                  class="grid grid-cols-1 gap-3 rounded-lg bg-white p-4 shadow-sm md:grid-cols-12">
                <div class="md:col-span-4">
                    <input type="text"
                           name="search"
                           value="{{ $filters['search'] ?? '' }}"
                           placeholder="Ürün adı, barkod veya SKU ara..."
                           class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-green-500 focus:ring-green-500">
                </div>

                <div class="md:col-span-3">
                    <select name="category_id"
                            class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-green-500 focus:ring-green-500">
                        <option value="">Tüm kategoriler</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(($filters['category_id'] ?? '') == $category->id)>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-2">
                    <select name="status"
                            class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-green-500 focus:ring-green-500">
                        <option value="">Tüm durumlar</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
                        <option value="passive" @selected(($filters['status'] ?? '') === 'passive')>Pasif</option>
                    </select>
                </div>

                <div class="flex items-center md:col-span-3">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox"
                               name="low_stock"
                               value="1"
                               @checked(! empty($filters['low_stock']))
                               class="rounded border-gray-300 text-green-600 shadow-sm focus:ring-green-500">
                        <span class="text-sm text-gray-700">Sadece kritik stok</span>
                    </label>
                </div>

                <div class="flex gap-2 md:col-span-12">
                    <button type="submit"
                            class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">
                        Filtrele
                    </button>
                    @if (collect($filters)->filter()->isNotEmpty())
                        <a href="{{ route('admin.products.index') }}"
                           class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            Temizle
                        </a>
                    @endif
                </div>
            </form>

            {{-- Liste (mobilde yatay kaydırılabilir) --}}
            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                            <tr>
                                <th class="px-4 py-3">Görsel</th>
                                <th class="px-4 py-3">Ürün</th>
                                <th class="px-4 py-3">Barkod</th>
                                <th class="px-4 py-3">Kategori</th>
                                <th class="px-4 py-3 text-right">Alış</th>
                                <th class="px-4 py-3 text-right">Satış</th>
                                <th class="px-4 py-3 text-right">Stok</th>
                                <th class="px-4 py-3">Durum</th>
                                <th class="px-4 py-3 text-right">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($products as $product)
                                @php $isLow = $product->isLowStock(); @endphp
                                <tr class="{{ $isLow ? 'bg-red-50 hover:bg-red-100' : 'hover:bg-gray-50' }}">
                                    <td class="px-4 py-3">
                                        @if ($product->image)
                                            <img src="{{ asset('storage/' . $product->image) }}"
                                                 alt="{{ $product->name }}"
                                                 class="h-10 w-10 rounded object-cover">
                                        @else
                                            <div class="flex h-10 w-10 items-center justify-center rounded bg-gray-100 text-xs text-gray-400">
                                                Yok
                                            </div>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <div class="font-medium text-gray-900">{{ $product->name }}</div>
                                        @if ($product->sku)
                                            <div class="text-xs text-gray-500">SKU: {{ $product->sku }}</div>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600">
                                        {{ $product->primaryBarcode?->barcode ?? '-' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600">
                                        {{ $product->category?->name ?? '-' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-gray-700">
                                        {{ number_format($product->purchase_price, 2, ',', '.') }} ₺
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-gray-900">
                                        {{ number_format($product->sale_price, 2, ',', '.') }} ₺
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">
                                        <span class="{{ $isLow ? 'font-semibold text-red-700' : 'text-gray-700' }}">
                                            {{ rtrim(rtrim(number_format($product->stock, 3, ',', '.'), '0'), ',') }}
                                            {{ $product->unit }}
                                        </span>
                                        @if ($isLow)
                                            <div class="text-xs font-semibold text-red-600">Kritik stok</div>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        @if ($product->is_active)
                                            <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800">
                                                Aktif
                                            </span>
                                        @else
                                            <span class="inline-flex rounded-full bg-gray-200 px-2 py-0.5 text-xs font-semibold text-gray-700">
                                                Pasif
                                            </span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            <a href="{{ route('admin.products.edit', $product) }}"
                                               class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                                Düzenle
                                            </a>

                                            <form method="POST"
                                                  action="{{ route('admin.products.toggle-active', $product) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit"
                                                        class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                                    {{ $product->is_active ? 'Pasife Al' : 'Aktif Et' }}
                                                </button>
                                            </form>

                                            <form method="POST"
                                                  action="{{ route('admin.products.destroy', $product) }}"
                                                  onsubmit="return confirm('«{{ e($product->name) }}» ürününü silmek istediğinize emin misiniz?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="rounded-md bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">
                                                    Sil
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                                        @if (collect($filters)->filter()->isNotEmpty())
                                            Filtrelerle eşleşen ürün bulunamadı.
                                        @else
                                            Henüz ürün eklenmemiş.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Sayfalama --}}
            <div>
                {{ $products->links() }}
            </div>
        </div>
    </div>
</x-app-layout>