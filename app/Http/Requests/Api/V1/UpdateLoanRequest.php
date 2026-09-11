<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLoanRequest extends FormRequest
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
        $loan = $this->route('loan');
        $loanId = is_object($loan) ? $loan->id : $loan;

        return [
            'code' => ['sometimes', 'string', 'max:30', Rule::unique('loans', 'code')->ignore($loanId)],
            'name' => ['sometimes', 'string', 'max:200'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'principal' => ['sometimes', 'integer', 'min:1'],
            'annual_interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'duration_months' => ['sometimes', 'integer', 'min:1', 'max:600'],
            'start_date' => ['sometimes', 'date'],
            'liability_account_id' => ['sometimes', 'integer', 'exists:accounts,id'],
            'interest_account_id' => ['sometimes', 'integer', 'exists:accounts,id'],
            'bank_account_id' => ['sometimes', 'integer', 'exists:accounts,id'],
            'journal_id' => ['nullable', 'integer', 'exists:journals,id'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'Kode pinjaman sudah digunakan.',
        ];
    }
}
