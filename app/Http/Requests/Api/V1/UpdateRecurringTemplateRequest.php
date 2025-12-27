<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\RecurringTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRecurringTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:200'],
            'frequency' => ['sometimes', Rule::in([
                RecurringTemplate::FREQUENCY_DAILY,
                RecurringTemplate::FREQUENCY_WEEKLY,
                RecurringTemplate::FREQUENCY_MONTHLY,
                RecurringTemplate::FREQUENCY_QUARTERLY,
                RecurringTemplate::FREQUENCY_YEARLY,
            ])],
            'interval' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'end_date' => ['nullable', 'date'],
            'occurrences_limit' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:1000'],
            'reference' => ['nullable', 'string', 'max:100'],
            'tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['sometimes', 'integer', 'min:0'],
            'early_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'early_discount_days' => ['nullable', 'integer', 'min:0'],
            'payment_term_days' => ['sometimes', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.description' => ['required_with:items', 'string', 'max:500'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0.0001'],
            'items.*.unit' => ['string', 'max:20'],
            'items.*.unit_price' => ['required_with:items', 'integer', 'min:0'],
            'items.*.revenue_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'items.*.expense_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'is_active' => ['sometimes', 'boolean'],
            'auto_post' => ['sometimes', 'boolean'],
            'auto_send' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'frequency.in' => 'Frekuensi tidak valid.',
            'items.min' => 'Minimal 1 item harus diisi.',
            'items.*.description.required_with' => 'Deskripsi item wajib diisi.',
            'items.*.quantity.required_with' => 'Kuantitas wajib diisi.',
            'items.*.unit_price.required_with' => 'Harga satuan wajib diisi.',
        ];
    }
}
