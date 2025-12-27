<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\SalesReturn;
use App\Models\Accounting\SalesReturnItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalesReturnRequest extends FormRequest
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
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'contact_id' => ['sometimes', 'required', 'integer', 'exists:contacts,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'return_date' => ['sometimes', 'required', 'date'],
            'reason' => ['nullable', 'string', Rule::in(array_keys(SalesReturn::getReasons()))],
            'notes' => ['nullable', 'string', 'max:1000'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items' => ['sometimes', 'required', 'array', 'min:1'],
            'items.*.invoice_item_id' => ['nullable', 'integer', 'exists:invoice_items,id'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.unit_price' => ['required', 'integer', 'min:0'],
            'items.*.condition' => ['nullable', 'string', Rule::in(array_keys(SalesReturnItem::getConditions()))],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
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
            'contact_id.required' => 'Pelanggan harus diisi.',
            'contact_id.exists' => 'Pelanggan yang dipilih tidak ditemukan.',
            'return_date.required' => 'Tanggal retur harus diisi.',
            'items.required' => 'Minimal satu item retur diperlukan.',
            'items.min' => 'Minimal satu item retur diperlukan.',
            'items.*.description.required' => 'Deskripsi item harus diisi.',
            'items.*.quantity.required' => 'Kuantitas item harus diisi.',
            'items.*.quantity.min' => 'Kuantitas item harus lebih dari 0.',
            'items.*.unit_price.required' => 'Harga satuan harus diisi.',
            'items.*.unit_price.min' => 'Harga satuan tidak boleh negatif.',
        ];
    }
}
