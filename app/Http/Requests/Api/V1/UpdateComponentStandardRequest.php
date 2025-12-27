<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\ComponentStandard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateComponentStandardRequest extends FormRequest
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
        $standardId = $this->route('componentStandard')?->id ?? $this->route('component_standard');

        return [
            'code' => ['sometimes', 'string', 'max:100', Rule::unique('component_standards', 'code')->ignore($standardId)],
            'name' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', 'string', Rule::in(array_keys(ComponentStandard::getCategories()))],
            'subcategory' => ['nullable', 'string', 'max:50'],
            'specifications' => ['sometimes', 'array'],
            'standard' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
            'unit' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'Kode komponen sudah digunakan.',
            'category.in' => 'Kategori tidak valid.',
        ];
    }
}
