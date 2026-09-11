<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountingLedgerRequest extends FormRequest
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
        $ledger = $this->route('accountingLedger');

        return [
            'code' => ['sometimes', 'string', 'max:30', Rule::unique('accounting_ledgers', 'code')->ignore($ledger)],
            'name' => ['sometimes', 'string', 'max:200'],
            'currency_code' => ['nullable', 'string', 'size:3', 'exists:currencies,code'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
