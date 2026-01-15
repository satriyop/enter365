<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Manufacturing\ComponentStandard;
use App\Models\Manufacturing\SpecValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSpecValidationRuleRequest extends FormRequest
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
        $ruleId = $this->route('rule')?->id;

        return [
            'category' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::in(array_keys(ComponentStandard::getCategories())),
            ],
            'spec_key' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('spec_validation_rules')
                    ->where('rule_set_id', $ruleSetId)
                    ->where('category', $this->input('category'))
                    ->ignore($ruleId),
            ],
            'validation_type' => [
                'sometimes',
                'required',
                Rule::in(array_keys(SpecValidationRule::getValidationTypes())),
            ],
            'threshold_value' => [
                'nullable',
                'numeric',
            ],
            'severity' => [
                'nullable',
                Rule::in(array_keys(SpecValidationRule::getSeverityLevels())),
            ],
            'message' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category.required' => 'Kategori harus dipilih.',
            'category.in' => 'Kategori tidak valid.',
            'spec_key.required' => 'Spec key harus diisi.',
            'spec_key.unique' => 'Rule untuk spec key ini sudah ada di kategori yang sama.',
            'validation_type.required' => 'Tipe validasi harus dipilih.',
            'validation_type.in' => 'Tipe validasi tidak valid.',
            'threshold_value.numeric' => 'Nilai threshold harus berupa angka.',
        ];
    }
}
