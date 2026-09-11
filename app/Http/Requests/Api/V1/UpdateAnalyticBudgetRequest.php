<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\AnalyticBudget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAnalyticBudgetRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:200'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date'],
            'status' => ['sometimes', 'string', Rule::in([
                AnalyticBudget::STATUS_DRAFT,
                AnalyticBudget::STATUS_OPEN,
                AnalyticBudget::STATUS_CLOSED,
            ])],
            'notes' => ['nullable', 'string'],
            'lines' => ['sometimes', 'array', 'min:1'],
            'lines.*.analytic_account_id' => ['required_with:lines', 'integer', 'exists:analytic_accounts,id'],
            'lines.*.planned_amount' => ['required_with:lines', 'integer'],
        ];
    }
}
