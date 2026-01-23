<?php

namespace App\Http\Requests\Api\V1;

class StoreBillRequest extends BaseTransactionalRequest
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
                'vendor_invoice_number' => ['nullable', 'string', 'max:100'],
                'bill_date' => ['required', 'date'],
                'due_date' => ['required', 'date', 'after_or_equal:bill_date'],
                'description' => ['nullable', 'string', 'max:1000'], // Override common description
                'discount_amount' => ['nullable', 'integer', 'min:0'], // Override common discount_value
                'payable_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
                'items.*.expense_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            ]
        );
    }

    public function messages(): array
    {
        return [
            'contact_id.required' => 'Supplier wajib dipilih.',
            'contact_id.exists' => 'Supplier tidak ditemukan.',
            'bill_date.required' => 'Tanggal faktur wajib diisi.',
            'due_date.required' => 'Tanggal jatuh tempo wajib diisi.',
            'due_date.after_or_equal' => 'Tanggal jatuh tempo tidak boleh sebelum tanggal faktur.',
            'items.required' => 'Item faktur wajib diisi.',
            'items.min' => 'Faktur harus memiliki minimal 1 item.',
        ];
    }
}
