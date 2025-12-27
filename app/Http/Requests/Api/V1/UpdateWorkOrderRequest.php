<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\WorkOrder;
use App\Models\Accounting\WorkOrderItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkOrderRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['nullable', 'string', Rule::in(array_keys(WorkOrder::getTypes()))],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'quantity_ordered' => ['nullable', 'numeric', 'min:0.0001'],
            'priority' => ['nullable', 'string', Rule::in(array_keys(WorkOrder::getPriorities()))],
            'planned_start_date' => ['nullable', 'date'],
            'planned_end_date' => ['nullable', 'date', 'after_or_equal:planned_start_date'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['nullable', 'array'],
            'items.*.type' => ['required', 'string', Rule::in(array_keys(WorkOrderItem::getTypes()))],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.unit_cost' => ['required', 'integer', 'min:0'],
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
            'product_id.exists' => 'Produk yang dipilih tidak ditemukan.',
            'quantity_ordered.min' => 'Kuantitas pesanan harus lebih dari 0.',
            'planned_end_date.after_or_equal' => 'Tanggal selesai harus setelah atau sama dengan tanggal mulai.',
            'warehouse_id.exists' => 'Gudang yang dipilih tidak ditemukan.',
            'items.*.type.required' => 'Tipe item harus diisi.',
            'items.*.description.required' => 'Deskripsi item harus diisi.',
            'items.*.quantity.required' => 'Kuantitas item harus diisi.',
            'items.*.unit_cost.required' => 'Biaya satuan item harus diisi.',
        ];
    }
}
