<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFiscalPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'lock_sales_until' => ['nullable', 'date'],
            'lock_purchases_until' => ['nullable', 'date'],
            'lock_tax_until' => ['nullable', 'date'],
            'lock_everything_until' => ['nullable', 'date'],
            'hard_lock_until' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'lock_sales_until.date' => 'Tanggal kunci penjualan tidak valid.',
            'lock_purchases_until.date' => 'Tanggal kunci pembelian tidak valid.',
            'lock_tax_until.date' => 'Tanggal kunci pajak tidak valid.',
            'lock_everything_until.date' => 'Tanggal kunci semua jurnal tidak valid.',
            'hard_lock_until.date' => 'Tanggal hard lock tidak valid.',
        ];
    }
}
