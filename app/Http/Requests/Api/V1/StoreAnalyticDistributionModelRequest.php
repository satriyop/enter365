<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnalyticDistributionModelRequest extends FormRequest
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
            'partner_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'account_prefix' => ['nullable', 'string', 'max:30'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'analytic_distribution' => ['required', 'array', 'min:1'],
            'analytic_distribution.*' => ['numeric', 'min:0', 'max:100'],
            'sequence' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama model distribusi wajib diisi.',
            'analytic_distribution.required' => 'Distribusi analitik wajib diisi.',
        ];
    }
}
