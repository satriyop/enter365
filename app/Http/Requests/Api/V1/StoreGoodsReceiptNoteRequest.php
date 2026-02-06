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
            'purchase_order_id' => ['sometimes', 'nullable', 'exists:purchase_orders,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'contact_id' => ['sometimes', 'nullable', 'exists:contacts,id'],
            'receipt_date' => ['sometimes', 'date'],
            'supplier_do_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'supplier_invoice_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'vehicle_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'driver_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            // Line items required when no PO
            'items' => ['required_without:purchase_order_id', 'array', 'min:1'],
            'items.*.product_id' => ['required_with:items', 'exists:products,id'],
            'items.*.quantity_ordered' => ['required_with:items', 'integer', 'min:1'],
            'items.*.unit_price' => ['required_with:items', 'integer', 'min:0'],
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
            'purchase_order_id.exists' => 'Purchase Order tidak ditemukan.',
            'warehouse_id.required' => 'Gudang wajib dipilih.',
            'warehouse_id.exists' => 'Gudang tidak ditemukan.',
            'contact_id.exists' => 'Supplier tidak ditemukan.',
            'receipt_date.date' => 'Format tanggal tidak valid.',
            'items.required_without' => 'Item wajib diisi untuk GRN tanpa Purchase Order.',
            'items.min' => 'Minimal satu item harus diisi.',
            'items.*.product_id.required_with' => 'Produk wajib dipilih.',
            'items.*.product_id.exists' => 'Produk tidak ditemukan.',
            'items.*.quantity_ordered.required_with' => 'Jumlah wajib diisi.',
            'items.*.quantity_ordered.min' => 'Jumlah minimal 1.',
            'items.*.unit_price.required_with' => 'Harga satuan wajib diisi.',
        ];
    }
}
