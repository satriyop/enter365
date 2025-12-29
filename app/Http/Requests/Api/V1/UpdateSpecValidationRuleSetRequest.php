<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSpecValidationRuleSetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $ruleSetId = $this->route('spec_rule_set')?->id ?? $this->route('specRuleSet')?->id;

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('spec_validation_rule_sets', 'code')->ignore($ruleSetId),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Kode rule set harus diisi.',
            'code.unique' => 'Kode rule set sudah digunakan.',
            'code.max' => 'Kode rule set maksimal 50 karakter.',
            'name.required' => 'Nama rule set harus diisi.',
            'name.max' => 'Nama rule set maksimal 100 karakter.',
        ];
    }
}
