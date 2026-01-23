<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\ValidationRules;

class UpdateBillRequest extends BaseTransactionalRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge(
            // Make transactional rules optional for updates
            collect($this->commonTransactionalRules())->map(function ($rules, $field) {
                if ($field === 'contact_id') {
                    return ValidationRules::CONTACT_SOMETIMES_OPTIONAL;
                }

                return collect($rules)->map(function ($rule) {
                    return $rule === 'required' ? 'sometimes' : $rule;
                })->toArray();
            })->toArray(),

            // Make item rules optional for updates
            collect($this->commonItemRules())->map(function ($rules, $field) {
                if (str_contains($field, 'items.*.')) {
                    return collect($rules)->map(function ($rule) {
                        return $rule === 'required' ? 'sometimes' : $rule;
                    })->toArray();
                }

                return collect($rules)->map(function ($rule) {
                    return $rule === 'required' ? 'sometimes' : $rule;
                })->toArray();
            })->toArray(),

            [
                'vendor_invoice_number' => ['nullable', 'string', 'max:100'],
                'bill_date' => ['sometimes', 'date'],
                'due_date' => ['sometimes', 'date', 'after_or_equal:bill_date'],
                'description' => ['nullable', 'string', 'max:1000'],
                'discount_amount' => ['nullable', 'integer', 'min:0'],
                'payable_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
                'items.*.id' => ['nullable', 'integer', 'exists:bill_items,id'],
                'items.*.expense_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            ]
        );
    }
}
