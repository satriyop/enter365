<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\AnalyticPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnalyticPlanRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:30', 'unique:analytic_plans,code'],
            'name' => ['required', 'string', 'max:200'],
            'parent_id' => ['nullable', 'integer', 'exists:analytic_plans,id'],
            'default_applicability' => ['nullable', 'string', Rule::in(AnalyticPlan::applicabilities())],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Kode rencana analitik wajib diisi.',
            'code.unique' => 'Kode rencana analitik sudah digunakan.',
            'name.required' => 'Nama rencana analitik wajib diisi.',
        ];
    }
}
