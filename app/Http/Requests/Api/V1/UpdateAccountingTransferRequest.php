<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountingTransferRequest extends FormRequest
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
            'transfer_date' => ['sometimes', 'date'],
            'from_journal_id' => ['sometimes', 'integer', 'exists:journals,id'],
            'to_journal_id' => ['sometimes', 'integer', 'exists:journals,id'],
            'from_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'to_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'amount' => ['sometimes', 'integer', 'min:1'],
            'memo' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.min' => 'Nilai transfer harus lebih dari 0.',
        ];
    }
}
