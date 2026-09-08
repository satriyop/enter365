<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class MatchBillPurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.bill_item_id' => ['required', 'integer', 'exists:bill_items,id'],
            'lines.*.purchase_order_item_id' => ['required', 'integer', 'exists:purchase_order_items,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'purchase_order_id.required' => 'Purchase order wajib dipilih.',
            'purchase_order_id.exists' => 'Purchase order tidak ditemukan.',
            'lines.required' => 'Baris matching wajib diisi.',
            'lines.min' => 'Minimal satu baris matching.',
            'lines.*.bill_item_id.required' => 'Baris tagihan wajib dipilih.',
            'lines.*.purchase_order_item_id.required' => 'Baris purchase order wajib dipilih.',
        ];
    }
}
