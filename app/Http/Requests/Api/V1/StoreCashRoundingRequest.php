<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\CashRounding;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCashRoundingRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:200'],
            'rounding' => ['required', 'integer', 'min:1'],
            'strategy' => ['required', 'string', Rule::in(CashRounding::strategies())],
            'profit_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'loss_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama pembulatan kas wajib diisi.',
            'rounding.required' => 'Nilai pembulatan wajib diisi.',
            'rounding.min' => 'Nilai pembulatan harus lebih dari 0.',
            'strategy.required' => 'Metode pembulatan wajib dipilih.',
            'strategy.in' => 'Metode pembulatan tidak valid.',
        ];
    }
}
