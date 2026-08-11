<?php

namespace App\Http\Requests\Api\V1\ElectricalPanel;

use Illuminate\Foundation\Http\FormRequest;

class StoreSpecValidationRuleSetRequest extends FormRequest
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
        return [
            'code' => ['required', 'string', 'max:50', 'unique:spec_validation_rule_sets,code'],
            'name' => ['required', 'string', 'max:100'],
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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'created_by' => auth()->id(),
        ]);
    }
}
