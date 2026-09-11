<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountingTransferRequest extends FormRequest
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
            'transfer_date' => ['required', 'date'],
            'from_journal_id' => ['required', 'integer', 'exists:journals,id'],
            'to_journal_id' => ['required', 'integer', 'exists:journals,id'],
            'from_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'to_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'memo' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'transfer_date.required' => 'Tanggal transfer wajib diisi.',
            'from_journal_id.required' => 'Jurnal asal wajib dipilih.',
            'to_journal_id.required' => 'Jurnal tujuan wajib dipilih.',
            'amount.required' => 'Nilai transfer wajib diisi.',
            'amount.min' => 'Nilai transfer harus lebih dari 0.',
        ];
    }
}
