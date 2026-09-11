<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountingLedgerRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:30', 'unique:accounting_ledgers,code'],
            'name' => ['required', 'string', 'max:200'],
            'currency_code' => ['nullable', 'string', 'size:3', 'exists:currencies,code'],
            'is_default' => ['sometimes', 'boolean'],
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
            'code.required' => 'Kode buku besar wajib diisi.',
            'code.unique' => 'Kode buku besar sudah digunakan.',
            'name.required' => 'Nama buku besar wajib diisi.',
            'currency_code.exists' => 'Mata uang tidak ditemukan.',
        ];
    }
}
