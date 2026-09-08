<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Inventory\StockTransfer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $operation = $this->input('operation_type', StockTransfer::OPERATION_INTERNAL);

        return [
            'operation_type' => ['required', Rule::in(StockTransfer::operationTypes())],
            'from_warehouse_id' => [
                Rule::requiredIf(in_array($operation, [StockTransfer::OPERATION_INTERNAL, StockTransfer::OPERATION_DELIVERY], true)),
                'nullable',
                'integer',
                'exists:warehouses,id',
            ],
            'to_warehouse_id' => [
                Rule::requiredIf(in_array($operation, [StockTransfer::OPERATION_INTERNAL, StockTransfer::OPERATION_RECEIPT], true)),
                'nullable',
                'integer',
                'exists:warehouses,id',
                'different:from_warehouse_id',
            ],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'scheduled_date' => ['nullable', 'date'],
            'source_document' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'operation_type.required' => 'Jenis operasi wajib diisi.',
            'from_warehouse_id.required' => 'Gudang asal wajib diisi.',
            'to_warehouse_id.required' => 'Gudang tujuan wajib diisi.',
            'to_warehouse_id.different' => 'Gudang tujuan harus berbeda dengan gudang asal.',
            'items.required' => 'Minimal satu baris produk wajib diisi.',
            'items.min' => 'Minimal satu baris produk wajib diisi.',
            'items.*.product_id.required' => 'Produk pada baris transfer wajib dipilih.',
            'items.*.quantity.min' => 'Jumlah minimal 1.',
        ];
    }
}
