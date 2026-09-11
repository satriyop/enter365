<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\AnalyticPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAnalyticPlanRequest extends FormRequest
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
        $plan = $this->route('analytic_plan') ?? $this->route('analyticPlan');
        $planId = is_object($plan) ? $plan->id : $plan;

        return [
            'code' => ['sometimes', 'string', 'max:30', Rule::unique('analytic_plans', 'code')->ignore($planId)],
            'name' => ['sometimes', 'string', 'max:200'],
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
            'code.unique' => 'Kode rencana analitik sudah digunakan.',
        ];
    }
}
