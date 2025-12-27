<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contact_id' => ['sometimes', 'integer', 'exists:contacts,id'],
            'vendor_invoice_number' => ['nullable', 'string', 'max:100'],
            'bill_date' => ['sometimes', 'date'],
            'due_date' => ['sometimes', 'date', 'after_or_equal:bill_date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'reference' => ['nullable', 'string', 'max:100'],
            'tax_rate' => ['numeric', 'min:0', 'max:100'],
            'discount_amount' => ['integer', 'min:0'],
            'payable_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer', 'exists:bill_items,id'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit' => ['string', 'max:20'],
            'items.*.unit_price' => ['required', 'integer', 'min:0'],
            'items.*.expense_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
        ];
    }
}
