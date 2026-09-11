<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\CashRounding;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCashRoundingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:200'],
            'rounding' => ['sometimes', 'integer', 'min:1'],
            'strategy' => ['sometimes', 'string', Rule::in(CashRounding::strategies())],
            'profit_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'loss_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
