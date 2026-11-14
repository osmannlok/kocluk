<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Kategoriler
            </h2>
            <a href="{{ route('admin.categories.create') }}"
               class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                + Yeni Kategori
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

            {{-- Arama --}}
            <form method="GET" action="{{ route('admin.categories.index') }}"
                  class="flex flex-col gap-2 rounded-lg bg-white p-4 shadow-sm sm:flex-row">
                <input type="text"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="Kategori adı ara..."
                       class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-green-500 focus:ring-green-500">
                <div class="flex gap-2">
                    <button type="submit"
                            class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">
                        Ara
                    </button>
                    @if (filled(request('search')))
                        <a href="{{ route('admin.categories.index') }}"
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
                                <th class="px-4 py-3">Kategori Adı</th>
                                <th class="px-4 py-3">Bağlantı Adı</th>
                                <th class="px-4 py-3 text-right">Ürün Sayısı</th>
                                <th class="px-4 py-3">Durum</th>
                                <th class="px-4 py-3 text-right">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($categories as $category)
                                <tr class="hover:bg-gray-50">
                                    <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900">
                                        {{ $category->name }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-500">
                                        {{ $category->slug }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-gray-700">
                                        {{ number_format($category->products_count, 0, ',', '.') }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        @if ($category->is_active)
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
                                            <a href="{{ route('admin.categories.edit', $category) }}"
                                               class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                                Düzenle
                                            </a>

                                            <form method="POST"
                                                  action="{{ route('admin.categories.destroy', $category) }}"
                                                  onsubmit="return confirm('«{{ e($category->name) }}» kategorisini silmek istediğinize emin misiniz?');">
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
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                        @if (filled(request('search')))
                                            Aramanızla eşleşen kategori bulunamadı.
                                        @else
                                            Henüz kategori eklenmemiş.
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
                {{ $categories->links() }}
            </div>
        </div>
    </div>
</x-app-layout>