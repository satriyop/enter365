<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreBudgetLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required', 'exists:accounts,id'],
            'annual_amount' => ['required_without_all:jan_amount,feb_amount,mar_amount,apr_amount,may_amount,jun_amount,jul_amount,aug_amount,sep_amount,oct_amount,nov_amount,dec_amount', 'integer', 'min:0'],
            'jan_amount' => ['nullable', 'integer', 'min:0'],
            'feb_amount' => ['nullable', 'integer', 'min:0'],
            'mar_amount' => ['nullable', 'integer', 'min:0'],
            'apr_amount' => ['nullable', 'integer', 'min:0'],
            'may_amount' => ['nullable', 'integer', 'min:0'],
            'jun_amount' => ['nullable', 'integer', 'min:0'],
            'jul_amount' => ['nullable', 'integer', 'min:0'],
            'aug_amount' => ['nullable', 'integer', 'min:0'],
            'sep_amount' => ['nullable', 'integer', 'min:0'],
            'oct_amount' => ['nullable', 'integer', 'min:0'],
            'nov_amount' => ['nullable', 'integer', 'min:0'],
            'dec_amount' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'account_id.required' => 'Akun wajib dipilih.',
            'account_id.exists' => 'Akun tidak ditemukan.',
            'annual_amount.required_without_all' => 'Jumlah tahunan atau jumlah bulanan wajib diisi.',
        ];
    }
}
