<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNsfpRangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transaction_code' => ['required', 'string', 'size:3', 'regex:/^\d{3}$/'],
            'branch_code' => ['required', 'string', 'size:3', 'regex:/^\d{3}$/'],
            'year_code' => ['required', 'string', 'size:2', 'regex:/^\d{2}$/'],
            'range_start' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('nsfp_ranges')->where(function ($query) {
                    return $query
                        ->where('transaction_code', $this->input('transaction_code'))
                        ->where('branch_code', $this->input('branch_code'))
                        ->where('year_code', $this->input('year_code'))
                        ->whereNull('deleted_at');
                }),
            ],
            'range_end' => ['required', 'integer', 'gt:range_start'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'transaction_code.required' => 'Kode transaksi wajib diisi.',
            'transaction_code.size' => 'Kode transaksi harus 3 digit.',
            'transaction_code.regex' => 'Kode transaksi harus berupa angka.',
            'branch_code.required' => 'Kode cabang wajib diisi.',
            'branch_code.size' => 'Kode cabang harus 3 digit.',
            'branch_code.regex' => 'Kode cabang harus berupa angka.',
            'year_code.required' => 'Kode tahun wajib diisi.',
            'year_code.size' => 'Kode tahun harus 2 digit.',
            'year_code.regex' => 'Kode tahun harus berupa angka.',
            'range_start.required' => 'Nomor awal wajib diisi.',
            'range_start.unique' => 'Range NSFP dengan kombinasi kode dan nomor awal ini sudah ada.',
            'range_end.required' => 'Nomor akhir wajib diisi.',
            'range_end.gt' => 'Nomor akhir harus lebih besar dari nomor awal.',
        ];
    }
}
