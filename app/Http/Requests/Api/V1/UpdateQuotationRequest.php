<?php

namespace App\Http\Requests\Api\V1;

class UpdateQuotationRequest extends BaseTransactionalRequest
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
            // Make transactional rules optional for updates
            collect($this->commonTransactionalRules())->map(function ($rules, $field) {
                return collect($rules)->map(function ($rule) {
                    return $rule === 'required' ? 'sometimes' : $rule;
                })->toArray();
            })->toArray(),

            // Make item rules optional for updates
            collect($this->commonItemRules())->map(function ($rules, $field) {
                if (str_contains($field, 'items.*.')) {
                    return collect($rules)->map(function ($rule) {
                        return $rule === 'required' ? 'required_with:items' : $rule;
                    })->toArray();
                }

                return collect($rules)->map(function ($rule) {
                    return $rule === 'required' ? 'sometimes' : $rule;
                })->toArray();
            })->toArray(),

            [
                'quotation_date' => ['sometimes', 'date'],
                'valid_until' => ['sometimes', 'date', 'after_or_equal:quotation_date'],
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
