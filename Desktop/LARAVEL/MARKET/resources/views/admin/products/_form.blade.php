{{--
    Ürün ortak form parçası (create + edit).
    Beklenen değişkenler:
      - $product      : (opsiyonel) düzenlenen Product modeli
      - $categories   : aktif kategoriler
      - $suppliers    : aktif tedarikçiler
      - $units        : birim listesi
      - $action       : formun gönderileceği URL
      - $method       : 'POST' veya 'PUT'
      - $submitLabel  : (opsiyonel) buton metni
--}}
@php
    $isEdit      = isset($product) && $product->exists;
    $submitLabel = $submitLabel ?? ($isEdit ? 'Güncelle' : 'Kaydet');
    $inputClass  = 'mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-green-500 focus:ring-green-500';

    // Miktar alanlarında gereksiz sıfırları kırp (12.500 -> 12.5)
    $trimNumber = fn ($value) => rtrim(rtrim((string) $value, '0'), '.') ?: '0';
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">

        {{-- Ürün adı --}}
        <div class="md:col-span-2">
            <label for="name" class="block text-sm font-medium text-gray-700">
                Ürün Adı <span class="text-red-600">*</span>
            </label>
            <input type="text" id="name" name="name" required maxlength="200" autofocus
                   value="{{ old('name', $product->name ?? '') }}"
                   class="{{ $inputClass }} @error('name') border-red-500 @enderror">
            @error('name')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Barkod --}}
        <div>
            <label for="barcode" class="block text-sm font-medium text-gray-700">Ana Barkod</label>
            <input type="text" id="barcode" name="barcode" maxlength="100"
                   value="{{ old('barcode', $product->primaryBarcode->barcode ?? '') }}"
                   class="{{ $inputClass }} @error('barcode') border-red-500 @enderror">
            @error('barcode')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- SKU --}}
        <div>
            <label for="sku" class="block text-sm font-medium text-gray-700">Stok Kodu (SKU)</label>
            <input type="text" id="sku" name="sku" maxlength="50"
                   value="{{ old('sku', $product->sku ?? '') }}"
                   class="{{ $inputClass }} @error('sku') border-red-500 @enderror">
            @error('sku')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Kategori --}}
        <div>
            <label for="category_id" class="block text-sm font-medium text-gray-700">Kategori</label>
            <select id="category_id" name="category_id"
                    class="{{ $inputClass }} @error('category_id') border-red-500 @enderror">
                <option value="">Seçiniz</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}"
                        @selected((string) old('category_id', $product->category_id ?? '') === (string) $category->id)>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
            @error('category_id')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Tedarikçi --}}
        <div>
            <label for="supplier_id" class="block text-sm font-medium text-gray-700">Tedarikçi</label>
            <select id="supplier_id" name="supplier_id"
                    class="{{ $inputClass }} @error('supplier_id') border-red-500 @enderror">
                <option value="">Seçiniz</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}"
                        @selected((string) old('supplier_id', $product->supplier_id ?? '') === (string) $supplier->id)>
                        {{ $supplier->name }}
                    </option>
                @endforeach
            </select>
            @error('supplier_id')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Birim --}}
        <div>
            <label for="unit" class="block text-sm font-medium text-gray-700">
                Birim <span class="text-red-600">*</span>
            </label>
            <select id="unit" name="unit" required
                    class="{{ $inputClass }} @error('unit') border-red-500 @enderror">
                @foreach ($units as $unit)
                    <option value="{{ $unit }}" @selected(old('unit', $product->unit ?? 'adet') === $unit)>
                        {{ $unit }}
                    </option>
                @endforeach
            </select>
            @error('unit')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- KDV --}}
        <div>
            <label for="vat_rate" class="block text-sm font-medium text-gray-700">KDV Oranı (%)</label>
            <input type="text" inputmode="decimal" id="vat_rate" name="vat_rate"
                   value="{{ old('vat_rate', isset($product) ? $trimNumber($product->vat_rate) : '0') }}"
                   class="{{ $inputClass }} @error('vat_rate') border-red-500 @enderror">
            @error('vat_rate')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Alış fiyatı --}}
        <div>
            <label for="purchase_price" class="block text-sm font-medium text-gray-700">
                Alış Fiyatı (₺) <span class="text-red-600">*</span>
            </label>
            <input type="text" inputmode="decimal" id="purchase_price" name="purchase_price" required
                   value="{{ old('purchase_price', $product->purchase_price ?? '0.00') }}"
                   class="{{ $inputClass }} @error('purchase_price') border-red-500 @enderror">
            @error('purchase_price')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Satış fiyatı --}}
        <div>
            <label for="sale_price" class="block text-sm font-medium text-gray-700">
                Satış Fiyatı (₺) <span class="text-red-600">*</span>
            </label>
            <input type="text" inputmode="decimal" id="sale_price" name="sale_price" required
                   value="{{ old('sale_price', $product->sale_price ?? '0.00') }}"
                   class="{{ $inputClass }} @error('sale_price') border-red-500 @enderror">
            @error('sale_price')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Başlangıç stoğu (yalnızca oluştururken) / mevcut stok (düzenlemede salt okunur) --}}
        <div>
            @if ($isEdit)
                <label class="block text-sm font-medium text-gray-700">Mevcut Stok</label>
                <input type="text" disabled
                       value="{{ $trimNumber($product->stock) }} {{ $product->unit }}"
                       class="{{ $inputClass }} bg-gray-100 text-gray-500">
                <p class="mt-1 text-xs text-gray-500">
                    Stok burada değiştirilemez. Stok girişi, fire veya sayım ile güncellenir.
                </p>
            @else
                <label for="stock" class="block text-sm font-medium text-gray-700">
                    Başlangıç Stoğu <span class="text-red-600">*</span>
                </label>
                <input type="text" inputmode="decimal" id="stock" name="stock" required
                       value="{{ old('stock', '0') }}"
                       class="{{ $inputClass }} @error('stock') border-red-500 @enderror">
                @error('stock')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            @endif
        </div>

        {{-- Kritik stok --}}
        <div>
            <label for="critical_stock" class="block text-sm font-medium text-gray-700">
                Kritik Stok <span class="text-red-600">*</span>
            </label>
            <input type="text" inputmode="decimal" id="critical_stock" name="critical_stock" required
                   value="{{ old('critical_stock', isset($product) ? $trimNumber($product->critical_stock) : '0') }}"
                   class="{{ $inputClass }} @error('critical_stock') border-red-500 @enderror">
            @error('critical_stock')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Slug --}}
        <div class="md:col-span-2">
            <label for="slug" class="block text-sm font-medium text-gray-700">Bağlantı Adı (Slug)</label>
            <input type="text" id="slug" name="slug" maxlength="220"
                   value="{{ old('slug', $product->slug ?? '') }}"
                   class="{{ $inputClass }} @error('slug') border-red-500 @enderror">
            <p class="mt-1 text-xs text-gray-500">Boş bırakırsanız ürün adından otomatik üretilir.</p>
            @error('slug')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Açıklama --}}
        <div class="md:col-span-2">
            <label for="description" class="block text-sm font-medium text-gray-700">Açıklama</label>
            <textarea id="description" name="description" rows="3" maxlength="2000"
                      class="{{ $inputClass }} @error('description') border-red-500 @enderror">{{ old('description', $product->description ?? '') }}</textarea>
            @error('description')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Görsel --}}
        <div class="md:col-span-2">
            <label for="image" class="block text-sm font-medium text-gray-700">Ürün Görseli</label>

            @if ($isEdit && $product->image)
                <div class="mt-2 flex items-center gap-4">
                    <img src="{{ asset('storage/' . $product->image) }}"
                         alt="{{ $product->name }}"
                         class="h-20 w-20 rounded object-cover">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="remove_image" value="1"
                               class="rounded border-gray-300 text-red-600 shadow-sm focus:ring-red-500">
                        <span class="text-sm text-gray-700">Mevcut görseli kaldır</span>
                    </label>
                </div>
            @endif

            <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp"
                   class="mt-2 block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-green-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-green-700 hover:file:bg-green-100">
            <p class="mt-1 text-xs text-gray-500">jpg, jpeg, png veya webp. En fazla 2 MB.</p>
                {{-- Butonlar --}}
            <div class="flex items-center gap-3 border-t border-gray-100 pt-4">
                <button type="submit"
                        class="rounded-md bg-green-600 px-5 py-2 text-sm font-semibold text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                    {{ $submitLabel }}
                </button>
                <a href="{{ route('admin.categories.index') }}"
                class="rounded-md border border-gray-300 px-5 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    Vazgeç
                </a>
            </div>