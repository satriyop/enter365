<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnalyticBudgetRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:200'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.analytic_account_id' => ['required', 'integer', 'exists:analytic_accounts,id'],
            'lines.*.planned_amount' => ['required', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama anggaran analitik wajib diisi.',
            'date_from.required' => 'Tanggal mulai wajib diisi.',
            'date_to.required' => 'Tanggal akhir wajib diisi.',
            'lines.required' => 'Baris anggaran analitik wajib diisi.',
        ];
    }
}
