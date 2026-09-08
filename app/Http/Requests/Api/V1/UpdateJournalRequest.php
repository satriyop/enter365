<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\Journal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJournalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('currency') && $this->input('currency') === '') {
            $this->merge(['currency' => null]);
        }

        if ($this->has('bank_account_number') && $this->input('bank_account_number') === '') {
            $this->merge(['bank_account_number' => null]);
        }
    }

    public function rules(): array
    {
        /** @var Journal $journal */
        $journal = $this->route('journal');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'string', Rule::in(Journal::getTypes())],
            'sequence_prefix' => [
                'sometimes',
                'required',
                'string',
                'max:32',
                Rule::unique('journals', 'sequence_prefix')->ignore($journal->id),
            ],
            'default_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'suspense_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'outstanding_receipts_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'outstanding_payments_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'profit_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'loss_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'bank_account_number' => ['nullable', 'string', 'max:255'],
            'dedicated_payment_sequence' => ['boolean'],
            'currency' => [
                'nullable',
                'string',
                'size:3',
                Rule::in(config('accounting.multi_currency.supported_currencies', [])),
            ],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama jurnal wajib diisi.',
            'type.required' => 'Tipe jurnal wajib diisi.',
            'type.in' => 'Tipe jurnal tidak valid.',
            'sequence_prefix.required' => 'Prefix nomor urut wajib diisi.',
            'sequence_prefix.unique' => 'Prefix nomor urut sudah digunakan.',
            'default_account_id.exists' => 'Akun default tidak ditemukan.',
            'suspense_account_id.exists' => 'Akun suspense tidak ditemukan.',
            'outstanding_receipts_account_id.exists' => 'Akun outstanding receipts tidak ditemukan.',
            'outstanding_payments_account_id.exists' => 'Akun outstanding payments tidak ditemukan.',
            'profit_account_id.exists' => 'Akun laba tidak ditemukan.',
            'loss_account_id.exists' => 'Akun rugi tidak ditemukan.',
        ];
    }
}
