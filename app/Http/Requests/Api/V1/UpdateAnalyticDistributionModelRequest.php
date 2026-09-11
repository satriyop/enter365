<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAnalyticDistributionModelRequest extends FormRequest
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
            'partner_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'account_prefix' => ['nullable', 'string', 'max:30'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'analytic_distribution' => ['sometimes', 'array', 'min:1'],
            'analytic_distribution.*' => ['numeric', 'min:0', 'max:100'],
            'sequence' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ];
    }
}
