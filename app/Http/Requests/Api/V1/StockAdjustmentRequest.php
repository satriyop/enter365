<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'new_quantity' => ['required', 'integer', 'min:0'],
            'new_unit_cost' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Produk wajib diisi.',
            'product_id.exists' => 'Produk tidak ditemukan.',
            'warehouse_id.exists' => 'Gudang tidak ditemukan.',
            'new_quantity.required' => 'Jumlah baru wajib diisi.',
            'new_quantity.min' => 'Jumlah tidak boleh negatif.',
            'new_unit_cost.min' => 'Harga satuan tidak boleh negatif.',
        ];
    }
}
