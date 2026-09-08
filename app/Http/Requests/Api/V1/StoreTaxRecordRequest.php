<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Tax\TaxRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaxRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'unique:tax_records,code'],
            'name' => ['required', 'string', 'max:200'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'applicability' => ['required', Rule::in(TaxRecord::applicabilities())],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode pajak wajib diisi.',
            'code.unique' => 'Kode pajak sudah digunakan.',
            'name.required' => 'Nama pajak wajib diisi.',
            'rate.required' => 'Tarif pajak wajib diisi.',
            'applicability.in' => 'Pajak harus untuk penjualan, pembelian, atau keduanya.',
        ];
    }
}
