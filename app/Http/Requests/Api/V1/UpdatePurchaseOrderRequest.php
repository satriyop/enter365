<?php

namespace App\Http\Requests\Api\V1;

class UpdatePurchaseOrderRequest extends BaseTransactionalRequest
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
                'po_date' => ['sometimes', 'date'],
                'expected_date' => ['sometimes', 'date', 'after_or_equal:po_date'],
                'shipping_address' => ['nullable', 'string', 'max:500'],
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
            'contact_id.exists' => 'Vendor tidak ditemukan.',
            'po_date.date' => 'Format tanggal PO tidak valid.',
            'expected_date.date' => 'Format tanggal diharapkan tidak valid.',
            'expected_date.after_or_equal' => 'Tanggal diharapkan harus sama atau setelah tanggal PO.',
            'items.min' => 'Minimal satu item PO harus diisi.',
            'items.*.description.required_with' => 'Deskripsi item harus diisi.',
            'items.*.quantity.required_with' => 'Jumlah item harus diisi.',
            'items.*.quantity.min' => 'Jumlah item harus lebih dari 0.',
            'items.*.unit_price.required_with' => 'Harga satuan harus diisi.',
            'items.*.unit_price.min' => 'Harga satuan tidak boleh negatif.',
        ];
    }
}
