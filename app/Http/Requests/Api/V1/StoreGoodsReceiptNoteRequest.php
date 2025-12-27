<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreGoodsReceiptNoteRequest extends FormRequest
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
            'purchase_order_id' => ['required', 'exists:purchase_orders,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'receipt_date' => ['sometimes', 'date'],
            'supplier_do_number' => ['sometimes', 'string', 'max:100'],
            'supplier_invoice_number' => ['sometimes', 'string', 'max:100'],
            'vehicle_number' => ['sometimes', 'string', 'max:50'],
            'driver_name' => ['sometimes', 'string', 'max:100'],
            'notes' => ['sometimes', 'string', 'max:1000'],
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
            'purchase_order_id.required' => 'Purchase Order wajib dipilih.',
            'purchase_order_id.exists' => 'Purchase Order tidak ditemukan.',
            'warehouse_id.required' => 'Gudang wajib dipilih.',
            'warehouse_id.exists' => 'Gudang tidak ditemukan.',
            'receipt_date.date' => 'Format tanggal tidak valid.',
        ];
    }
}
