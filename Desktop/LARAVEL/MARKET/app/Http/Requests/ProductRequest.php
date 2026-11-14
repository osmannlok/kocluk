<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    /**
     * Yalnızca admin ve warehouse kullanıcıları ürün ekleyip düzenleyebilir.
     */
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'warehouse'], true);
    }

    /**
     * Doğrulamadan önce verileri normalize eder:
     * - Virgüllü sayıları (12,50) noktalı hale getirir.
     * - Slug doluysa temizler; boşsa ProductService benzersiz slug üretir.
     * - Onay kutularını boolean yapar.
     */
    protected function prepareForValidation(): void
    {
        $data = [
            'is_weighed' => $this->boolean('is_weighed'),
            'is_active'  => $this->boolean('is_active'),
            'slug'       => filled($this->input('slug')) ? Str::slug($this->input('slug')) : null,
            'sku'        => filled($this->input('sku')) ? trim($this->input('sku')) : null,
            'barcode'    => filled($this->input('barcode')) ? trim($this->input('barcode')) : null,
        ];

        foreach (['purchase_price', 'sale_price', 'vat_rate', 'stock', 'critical_stock'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $data[$field] = str_replace(',', '.', trim($this->input($field)));
            }
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        // Güncelleme sırasında kendi kaydını unique kontrolünden hariç tut.
        $product   = $this->route('product');
        $productId = $product?->id ?? $product;
        $isCreate  = $this->isMethod('post');

        $rules = [
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->whereNull('deleted_at'),
            ],
            'supplier_id' => [
                'nullable',
                Rule::exists('suppliers', 'id')->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:200'],
            'slug' => [
                'nullable',
                'string',
                'max:220',
                Rule::unique('products', 'slug')->ignore($productId),
            ],
            'sku' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('products', 'sku')->ignore($productId),
            ],
            // Ana barkod: product_barcodes tablosunda benzersiz olmalı.
            'barcode' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('product_barcodes', 'barcode')->where(function ($query) use ($productId) {
                    // Düzenlemede ürünün kendi barkodları hariç tutulur.
                    if ($productId) {
                        $query->where('product_id', '!=', $productId);
                    }
                }),
            ],
            'unit'           => ['required', Rule::in(['adet', 'kg', 'gram', 'litre', 'paket'])],
            'purchase_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'sale_price'     => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'vat_rate'       => ['nullable', 'numeric', 'min:0', 'max:100'],
            'critical_stock' => ['required', 'numeric', 'min:0', 'max:999999999.999'],
            'is_weighed'     => ['boolean'],
            'is_active'      => ['boolean'],
            'image'          => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image'   => ['nullable', 'boolean'],
            'description'    => ['nullable', 'string', 'max:2000'],
        ];

        // Stok yalnızca ürün oluştururken (başlangıç stoğu) girilebilir.
        // Sonraki değişiklikler stok girişi, fire veya sayım ile yapılır;
        // böylece her stok değişimi stock_movements tablosuna yazılır.
        if ($isCreate) {
            $rules['stock'] = ['required', 'numeric', 'min:0', 'max:999999999.999'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'category_id'    => 'kategori',
            'supplier_id'    => 'tedarikçi',
            'name'           => 'ürün adı',
            'slug'           => 'bağlantı adı (slug)',
            'sku'            => 'stok kodu (SKU)',
            'barcode'        => 'barkod',
            'unit'           => 'birim',
            'purchase_price' => 'alış fiyatı',
            'sale_price'     => 'satış fiyatı',
            'vat_rate'       => 'KDV oranı',
            'stock'          => 'stok',
            'critical_stock' => 'kritik stok',
            'image'          => 'görsel',
            'description'    => 'açıklama',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute alanı zorunludur.',
            'numeric'  => ':attribute sayısal bir değer olmalıdır.',
            'min'      => [
                'numeric' => ':attribute en az :min olabilir.',
            ],
            'max'      => [
                'numeric' => ':attribute en fazla :max olabilir.',
                'string'  => ':attribute en fazla :max karakter olabilir.',
                'file'    => ':attribute en fazla :max KB olabilir.',
            ],
            'category_id.exists' => 'Seçilen kategori bulunamadı.',
            'supplier_id.exists' => 'Seçilen tedarikçi bulunamadı.',
            'slug.unique'        => 'Bu bağlantı adı (slug) zaten kullanılıyor.',
            'sku.unique'         => 'Bu stok kodu (SKU) başka bir üründe kullanılıyor.',
            'barcode.unique'     => 'Bu barkod başka bir ürüne ait.',
            'unit.in'            => 'Birim adet, kg, gram, litre veya paket olmalıdır.',
            'image.image'        => 'Görsel geçerli bir resim dosyası olmalıdır.',
            'image.mimes'        => 'Görsel jpg, jpeg, png veya webp formatında olmalıdır.',
            'image.max'          => 'Görsel en fazla 2 MB olabilir.',
        ];
    }
}