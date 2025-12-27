<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\Budget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'type' => ['sometimes', Rule::in([Budget::TYPE_ANNUAL, Budget::TYPE_QUARTERLY, Budget::TYPE_MONTHLY])],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama anggaran wajib diisi.',
            'type.in' => 'Tipe anggaran tidak valid.',
        ];
    }
}
