<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoanRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:30', 'unique:loans,code'],
            'name' => ['required', 'string', 'max:200'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'principal' => ['required', 'integer', 'min:1'],
            'annual_interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:600'],
            'start_date' => ['required', 'date'],
            'liability_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'interest_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'bank_account_id' => ['required', 'integer', 'exists:accounts,id'],
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
            'code.required' => 'Kode pinjaman wajib diisi.',
            'code.unique' => 'Kode pinjaman sudah digunakan.',
            'name.required' => 'Nama pinjaman wajib diisi.',
            'principal.required' => 'Pokok pinjaman wajib diisi.',
            'duration_months.required' => 'Tenor pinjaman wajib diisi.',
            'start_date.required' => 'Tanggal mulai wajib diisi.',
            'liability_account_id.required' => 'Akun utang pinjaman wajib dipilih.',
            'interest_account_id.required' => 'Akun beban bunga wajib dipilih.',
            'bank_account_id.required' => 'Akun kas/bank wajib dipilih.',
        ];
    }
}
