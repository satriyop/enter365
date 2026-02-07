<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountingPoliciesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gate checked in controller
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'inventory_method' => ['required', Rule::in(['perpetual', 'periodic', 'hybrid'])],
            'cogs_recognition' => ['required', Rule::in(['on_invoice', 'on_delivery', 'manual'])],
            'return_accounting' => ['required', Rule::in(['full_journal', 'inventory_only'])],
            'manufacturing_costing' => ['required', Rule::in(['project_based', 'job_costing', 'wip_accounting'])],
            'closing_strategy' => ['required', Rule::in(['direct', 'income_summary'])],
        ];
    }
}
