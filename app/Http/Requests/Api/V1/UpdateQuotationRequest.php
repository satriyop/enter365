<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuotationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'contact_id' => ['sometimes', 'exists:contacts,id'],
            'quotation_date' => ['sometimes', 'date'],
            'valid_until' => ['sometimes', 'date', 'after_or_equal:quotation_date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'subject' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'in:percentage,fixed'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms_conditions' => ['nullable', 'string', 'max:5000'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.description' => ['required_with:items', 'string', 'max:500'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0.0001'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.unit_price' => ['required_with:items', 'integer', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact_id.exists' => 'Pelanggan tidak ditemukan.',
            'quotation_date.date' => 'Format tanggal penawaran tidak valid.',
            'valid_until.date' => 'Format tanggal berlaku tidak valid.',
            'valid_until.after_or_equal' => 'Tanggal berlaku harus sama atau setelah tanggal penawaran.',
            'items.min' => 'Minimal satu item penawaran harus diisi.',
            'items.*.description.required_with' => 'Deskripsi item harus diisi.',
            'items.*.quantity.required_with' => 'Jumlah item harus diisi.',
            'items.*.quantity.min' => 'Jumlah item harus lebih dari 0.',
            'items.*.unit_price.required_with' => 'Harga satuan harus diisi.',
            'items.*.unit_price.min' => 'Harga satuan tidak boleh negatif.',
        ];
    }
}
