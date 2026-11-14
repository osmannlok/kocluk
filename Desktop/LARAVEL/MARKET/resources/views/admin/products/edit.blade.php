<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Ürün Düzenle: {{ $product->name }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow-sm">
                @include('admin.products._form', [
                    'product'     => $product,
                    'categories'  => $categories,
                    'suppliers'   => $suppliers,
                    'units'       => $units,
                    'action'      => route('admin.products.update', $product),
                    'method'      => 'PUT',
                    'submitLabel' => 'Güncelle',
                ])
            </div>
            
        </div>
    </div>
</x-app-layout>