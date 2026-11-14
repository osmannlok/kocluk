<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Barkod Yönetimi: {{ $product->name }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Başarı mesajı --}}
            @if (session('success'))
                <div class="rounded-md bg-green-50 border border-green-200 p-4 text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Genel hata mesajı --}}
            @if ($errors->any() && ! $errors->has('barcode'))
                <div class="rounded-md bg-red-50 border border-red-200 p-4 text-red-800">
                    {{ $errors->first() }}
                </div>
            @endif

            {{-- Yeni barkod ekleme formu --}}
            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Yeni Barkod Ekle</h3>

                <form method="POST" action="{{ route('admin.products.barcodes.store', $product) }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="barcode" class="block text-sm font-medium text-gray-700">
                            Barkod <span class="text-red-600">*</span>
                        </label>
                        <input
                            id="barcode"
                            name="barcode"
                            type="text"
                            maxlength="100"
                            value="{{ old('barcode') }}"
                            autofocus
                            required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                            placeholder="Barkodu okutun veya yazın"
                        >
                        @error('barcode')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="inline-flex items-center">
                        <input type="hidden" name="is_primary" value="0">
                        <input
                            type="checkbox"
                            name="is_primary"
                            value="1"
                            @checked(old('is_primary'))
                            class="rounded border-gray-300 text-green-600 focus:ring-green-500"
                        >
                        <span class="ml-2 text-sm text-gray-700">Ana barkod olarak işaretle</span>
                    </label>

                    <div>
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-semibold rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                            Kaydet
                        </button>
                        <a href="{{ url()->previous() }}" class="ml-3 text-sm text-gray-600 hover:underline">Geri dön</a>
                    </div>
                </form>
            </div>

            {{-- Barkod listesi --}}
            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Kayıtlı Barkodlar</h3>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Barkod</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Durum</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse ($barcodes as $item)
                                <tr>
                                    {{-- Düzenleme formu satır içinde --}}
                                    <td class="px-4 py-3">
                                        <form id="update-{{ $item->id }}" method="POST"
                                              action="{{ route('admin.barcodes.update', $item) }}">
                                            @csrf
                                            @method('PUT')
                                            <input
                                                type="text"
                                                name="barcode"
                                                maxlength="100"
                                                value="{{ $item->barcode }}"
                                                required
                                                class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-green-500 focus:ring-green-500"
                                            >
                                        </form>
                                    </td>

                                    <td class="px-4 py-3 text-sm">
                                        @if ($item->is_primary)
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Ana barkod</span>
                                        @else
                                            <span class="text-gray-500">Ek barkod</span>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <button type="submit" form="update-{{ $item->id }}"
                                            class="px-3 py-1 text-sm font-medium text-blue-700 bg-blue-50 rounded-md hover:bg-blue-100">
                                            Güncelle
                                        </button>

                                        @unless ($item->is_primary)
                                            <form method="POST" action="{{ route('admin.barcodes.primary', $item) }}" class="inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit"
                                                    class="px-3 py-1 text-sm font-medium text-green-700 bg-green-50 rounded-md hover:bg-green-100">
                                                    Ana Yap
                                                </button>
                                            </form>
                                        @endunless

                                        <form method="POST" action="{{ route('admin.barcodes.destroy', $item) }}" class="inline"
                                              onsubmit="return confirm('Bu barkodu silmek istediğinize emin misiniz?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="px-3 py-1 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700">
                                                Sil
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-6 text-center text-sm text-gray-500">
                                        Bu ürün için henüz barkod eklenmemiş.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>