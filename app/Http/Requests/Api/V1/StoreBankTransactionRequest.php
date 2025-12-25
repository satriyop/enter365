<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreBankTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'transaction_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:100'],
            'debit' => ['required_without:credit', 'integer', 'min:0'],
            'credit' => ['required_without:debit', 'integer', 'min:0'],
            'balance' => ['nullable', 'integer'],
            'external_id' => ['nullable', 'string', 'max:100'],
            'import_batch' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'account_id.required' => 'Akun bank wajib dipilih.',
            'account_id.exists' => 'Akun bank tidak ditemukan.',
            'transaction_date.required' => 'Tanggal transaksi wajib diisi.',
            'description.required' => 'Deskripsi wajib diisi.',
            'debit.required_without' => 'Debit atau kredit wajib diisi.',
            'credit.required_without' => 'Debit atau kredit wajib diisi.',
        ];
    }
}
