<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    /**
     * Yalnızca admin ve warehouse kullanıcıları kategori ekleyip düzenleyebilir.
     */
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'warehouse'], true);
    }

    /**
     * Doğrulamadan önce slug boşsa addan üretir, onay kutusunu boolean yapar.
     */
    protected function prepareForValidation(): void
    {
        $slug = $this->input('slug');

        $this->merge([
            'slug'      => filled($slug) ? Str::slug($slug) : Str::slug((string) $this->input('name')),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        // Güncelleme sırasında kendi kaydını unique kontrolünden hariç tut.
        $categoryId = $this->route('category')?->id ?? $this->route('category');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name')->ignore($categoryId),
            ],
            'slug' => [
                'required',
                'string',
                'max:120',
                Rule::unique('categories', 'slug')->ignore($categoryId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active'   => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name'        => 'kategori adı',
            'slug'        => 'bağlantı adı (slug)',
            'description' => 'açıklama',
            'is_active'   => 'durum',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Kategori adı zorunludur.',
            'name.max'      => 'Kategori adı en fazla 100 karakter olabilir.',
            'name.unique'   => 'Bu kategori adı zaten kullanılıyor.',
            'slug.required' => 'Bağlantı adı (slug) üretilemedi, lütfen geçerli bir ad girin.',
            'slug.unique'   => 'Bu bağlantı adı (slug) zaten kullanılıyor.',
            'slug.max'      => 'Bağlantı adı en fazla 120 karakter olabilir.',
            'description.max' => 'Açıklama en fazla 2000 karakter olabilir.',
        ];
    }
}