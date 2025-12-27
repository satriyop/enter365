<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'from_warehouse_id' => ['required', 'exists:warehouses,id'],
            'to_warehouse_id' => ['required', 'exists:warehouses,id', 'different:from_warehouse_id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Produk wajib diisi.',
            'product_id.exists' => 'Produk tidak ditemukan.',
            'from_warehouse_id.required' => 'Gudang asal wajib diisi.',
            'from_warehouse_id.exists' => 'Gudang asal tidak ditemukan.',
            'to_warehouse_id.required' => 'Gudang tujuan wajib diisi.',
            'to_warehouse_id.exists' => 'Gudang tujuan tidak ditemukan.',
            'to_warehouse_id.different' => 'Gudang tujuan harus berbeda dengan gudang asal.',
            'quantity.required' => 'Jumlah wajib diisi.',
            'quantity.min' => 'Jumlah minimal 1.',
        ];
    }
}
