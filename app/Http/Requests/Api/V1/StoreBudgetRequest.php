<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\Budget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'fiscal_period_id' => ['required', 'exists:fiscal_periods,id'],
            'type' => ['required', Rule::in([Budget::TYPE_ANNUAL, Budget::TYPE_QUARTERLY, Budget::TYPE_MONTHLY])],
            'notes' => ['nullable', 'string'],

            // Budget lines
            'lines' => ['nullable', 'array'],
            'lines.*.account_id' => ['required_with:lines', 'exists:accounts,id'],
            'lines.*.annual_amount' => ['required_with:lines', 'integer', 'min:0'],
            'lines.*.jan_amount' => ['nullable', 'integer', 'min:0'],
            'lines.*.feb_amount' => ['nullable', 'integer', 'min:0'],
            'lines.*.mar_amount' => ['nullable', 'integer', 'min:0'],
            'lines.*.apr_amount' => ['nullable', 'integer', 'min:0'],
            'lines.*.may_amount' => ['nullable', 'integer', 'min:0'],
            'lines.*.jun_amount' => ['nullable', 'integer', 'min:0'],
            'lines.*.jul_amount' => ['nullable', 'integer', 'min:0'],
            'lines.*.aug_amount' => ['nullable', 'integer', 'min:0'],
            'lines.*.sep_amount' => ['nullable', 'integer', 'min:0'],
            'lines.*.oct_amount' => ['nullable', 'integer', 'min:0'],
            'lines.*.nov_amount' => ['nullable', 'integer', 'min:0'],
            'lines.*.dec_amount' => ['nullable', 'integer', 'min:0'],
            'lines.*.notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama anggaran wajib diisi.',
            'fiscal_period_id.required' => 'Periode fiskal wajib dipilih.',
            'fiscal_period_id.exists' => 'Periode fiskal tidak ditemukan.',
            'type.required' => 'Tipe anggaran wajib dipilih.',
            'type.in' => 'Tipe anggaran tidak valid.',
            'lines.*.account_id.required_with' => 'Akun wajib dipilih untuk setiap baris.',
            'lines.*.account_id.exists' => 'Akun tidak ditemukan.',
            'lines.*.annual_amount.required_with' => 'Jumlah tahunan wajib diisi.',
        ];
    }
}
