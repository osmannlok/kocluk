{{--
    Kategori ortak form parçası (create + edit).
    Beklenen değişkenler:
      - $category  : (opsiyonel) düzenlenen Category modeli
      - $action    : formun gönderileceği URL
      - $method    : 'POST' veya 'PUT'
      - $submitLabel : (opsiyonel) buton metni
--}}
@php
    $isEdit      = isset($category) && $category->exists;
    $submitLabel = $submitLabel ?? ($isEdit ? 'Güncelle' : 'Kaydet');
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    {{-- Kategori adı --}}
    <div>
        <label for="name" class="block text-sm font-medium text-gray-700">
            Kategori Adı <span class="text-red-600">*</span>
        </label>
        <input type="text"
               id="name"
               name="name"
               value="{{ old('name', $category->name ?? '') }}"
               maxlength="100"
               required
               autofocus
               class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-green-500 focus:ring-green-500 @error('name') border-red-500 @enderror">
        @error('name')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Slug --}}
    <div>
        <label for="slug" class="block text-sm font-medium text-gray-700">
            Bağlantı Adı (Slug)
        </label>
        <input type="text"
               id="slug"
               name="slug"
               value="{{ old('slug', $category->slug ?? '') }}"
               maxlength="120"
               class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-green-500 focus:ring-green-500 @error('slug') border-red-500 @enderror">
        <p class="mt-1 text-xs text-gray-500">Boş bırakırsanız kategori adından otomatik üretilir.</p>
        @error('slug')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Açıklama --}}
    <div>
        <label for="description" class="block text-sm font-medium text-gray-700">
            Açıklama
        </label>
        <textarea id="description"
                  name="description"
                  rows="4"
                  maxlength="2000"
                  class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-green-500 focus:ring-green-500 @error('description') border-red-500 @enderror">{{ old('description', $category->description ?? '') }}</textarea>
        @error('description')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Durum --}}
    <div>
        <label class="inline-flex items-center gap-2">
            <input type="checkbox"
                   name="is_active"
                   value="1"
                   @checked(old('is_active', $isEdit ? $category->is_active : true))
                   class="rounded border-gray-300 text-green-600 shadow-sm focus:ring-green-500">
            <span class="text-sm text-gray-700">Aktif</span>
        </label>
        @error('is_active')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

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
</form>