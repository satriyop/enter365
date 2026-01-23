<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\ValidationRules;

class UpdateInvoiceRequest extends BaseTransactionalRequest
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
                return collect($rules)->map(function ($rule) {
                    return $rule === 'required' ? 'sometimes' : $rule;
                })->toArray();
            })->toArray(),

            [
                'invoice_date' => ['sometimes', 'date'],
                'due_date' => ['sometimes', 'date', 'after_or_equal:invoice_date'],
                'description' => ['nullable', 'string', 'max:1000'],
                'discount_amount' => ['nullable', 'integer', 'min:0'],
                'receivable_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
                'items.*.id' => ['nullable', 'integer', 'exists:invoice_items,id'],
                'items.*.unit' => ['nullable', 'string', 'max:20'],
                'items.*.revenue_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            ]
        );
    }
}
