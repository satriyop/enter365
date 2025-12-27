<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
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
            'contact_id' => ['required', 'exists:contacts,id'],
            'po_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:po_date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'subject' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'in:percentage,fixed'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms_conditions' => ['nullable', 'string', 'max:5000'],
            'shipping_address' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.unit_price' => ['required', 'integer', 'min:0'],
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
            'contact_id.required' => 'Vendor harus dipilih.',
            'contact_id.exists' => 'Vendor tidak ditemukan.',
            'po_date.required' => 'Tanggal PO harus diisi.',
            'po_date.date' => 'Format tanggal PO tidak valid.',
            'expected_date.date' => 'Format tanggal diharapkan tidak valid.',
            'expected_date.after_or_equal' => 'Tanggal diharapkan harus sama atau setelah tanggal PO.',
            'items.required' => 'Item PO harus diisi.',
            'items.min' => 'Minimal satu item PO harus diisi.',
            'items.*.description.required' => 'Deskripsi item harus diisi.',
            'items.*.quantity.required' => 'Jumlah item harus diisi.',
            'items.*.quantity.min' => 'Jumlah item harus lebih dari 0.',
            'items.*.unit_price.required' => 'Harga satuan harus diisi.',
            'items.*.unit_price.min' => 'Harga satuan tidak boleh negatif.',
        ];
    }
}
