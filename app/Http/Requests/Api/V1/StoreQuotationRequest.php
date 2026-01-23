<?php

namespace App\Http\Requests\Api\V1;

class StoreQuotationRequest extends BaseTransactionalRequest
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
        return array_merge(
            $this->commonTransactionalRules(),
            $this->commonItemRules(),
            [
                'quotation_date' => ['required', 'date'],
                'valid_until' => ['nullable', 'date', 'after_or_equal:quotation_date'],
                'items.*.unit' => ['nullable', 'string', 'max:20'], // Override to make nullable
            ]
        );
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact_id.required' => 'Pelanggan harus dipilih.',
            'contact_id.exists' => 'Pelanggan tidak ditemukan.',
            'quotation_date.required' => 'Tanggal penawaran harus diisi.',
            'quotation_date.date' => 'Format tanggal penawaran tidak valid.',
            'valid_until.date' => 'Format tanggal berlaku tidak valid.',
            'valid_until.after_or_equal' => 'Tanggal berlaku harus sama atau setelah tanggal penawaran.',
            'items.required' => 'Item penawaran harus diisi.',
            'items.min' => 'Minimal satu item penawaran harus diisi.',
            'items.*.description.required' => 'Deskripsi item harus diisi.',
            'items.*.quantity.required' => 'Jumlah item harus diisi.',
            'items.*.quantity.min' => 'Jumlah item harus lebih dari 0.',
            'items.*.unit_price.required' => 'Harga satuan harus diisi.',
            'items.*.unit_price.min' => 'Harga satuan tidak boleh negatif.',
        ];
    }
}
