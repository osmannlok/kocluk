<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
{
    /**
     * Satışı yalnızca yönetici ve kasiyer yapabilir.
     */
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'cashier'], true);
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'note' => ['nullable', 'string', 'max:500'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],

            'payments' => ['required', 'array', 'min:1'],
            'payments.*.payment_type' => ['required', 'in:cash,card,transfer,credit'],
            'payments.*.amount' => ['required', 'numeric', 'gt:0', 'max:999999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.exists' => 'Seçilen müşteri bulunamadı.',
            'discount.numeric' => 'Genel indirim sayı olmalıdır.',
            'discount.min' => 'Genel indirim eksi olamaz.',
            'note.max' => 'Not en fazla 500 karakter olabilir.',

            'items.required' => 'Sepet boş. Satış için en az bir ürün ekleyin.',
            'items.min' => 'Sepet boş. Satış için en az bir ürün ekleyin.',
            'items.*.product_id.required' => 'Ürün seçimi zorunludur.',
            'items.*.product_id.exists' => 'Seçilen ürün bulunamadı.',
            'items.*.quantity.required' => 'Miktar zorunludur.',
            'items.*.quantity.numeric' => 'Miktar sayı olmalıdır.',
            'items.*.quantity.gt' => 'Miktar sıfırdan büyük olmalıdır.',
            'items.*.discount.numeric' => 'İndirim sayı olmalıdır.',
            'items.*.discount.min' => 'İndirim eksi olamaz.',

            'payments.required' => 'En az bir ödeme girilmelidir.',
            'payments.min' => 'En az bir ödeme girilmelidir.',
            'payments.*.payment_type.required' => 'Ödeme türü zorunludur.',
            'payments.*.payment_type.in' => 'Geçersiz ödeme türü.',
            'payments.*.amount.required' => 'Ödeme tutarı zorunludur.',
            'payments.*.amount.numeric' => 'Ödeme tutarı sayı olmalıdır.',
            'payments.*.amount.gt' => 'Ödeme tutarı sıfırdan büyük olmalıdır.',
        ];
    }
}