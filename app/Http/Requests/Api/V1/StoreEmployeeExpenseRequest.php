<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeExpenseRequest extends FormRequest
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
            'employee_id' => ['required', 'integer', 'exists:users,id'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'expense_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:500'],
            'amount' => ['required', 'integer', 'min:1'],
            'tax_amount' => ['sometimes', 'integer', 'min:0'],
            'expense_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'Karyawan wajib dipilih.',
            'expense_date.required' => 'Tanggal biaya wajib diisi.',
            'description.required' => 'Uraian biaya wajib diisi.',
            'amount.required' => 'Jumlah biaya wajib diisi.',
            'amount.min' => 'Jumlah biaya harus lebih dari 0.',
            'expense_account_id.required' => 'Akun biaya wajib dipilih.',
        ];
    }
}
