<?php

namespace App\Http\Requests\Api\V1;

class StoreInvoiceRequest extends BaseTransactionalRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge(
            $this->commonTransactionalRules(),
            $this->commonItemRules(),
            [
                'invoice_date' => ['required', 'date'],
                'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
                'description' => ['nullable', 'string', 'max:1000'], // Override common description
                'discount_amount' => ['nullable', 'integer', 'min:0'], // Override common discount_value
                'receivable_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
                'items.*.unit' => ['nullable', 'string', 'max:20'], // Override common unit (make nullable)
                'items.*.revenue_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            ]
        );
    }

    public function messages(): array
    {
        return [
            'contact_id.required' => 'Pelanggan wajib dipilih.',
            'contact_id.exists' => 'Pelanggan tidak ditemukan.',
            'invoice_date.required' => 'Tanggal faktur wajib diisi.',
            'due_date.required' => 'Tanggal jatuh tempo wajib diisi.',
            'due_date.after_or_equal' => 'Tanggal jatuh tempo tidak boleh sebelum tanggal faktur.',
            'items.required' => 'Item faktur wajib diisi.',
            'items.min' => 'Faktur harus memiliki minimal 1 item.',
            'items.*.description.required' => 'Deskripsi item wajib diisi.',
            'items.*.quantity.required' => 'Kuantitas wajib diisi.',
            'items.*.unit_price.required' => 'Harga satuan wajib diisi.',
        ];
    }
}
