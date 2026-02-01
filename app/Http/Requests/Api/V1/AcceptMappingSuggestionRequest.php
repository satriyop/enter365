<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class AcceptMappingSuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'component_standard_id' => ['required', 'integer', 'exists:component_standards,id'],
            'brand_sku' => ['nullable', 'string', 'max:100'],
            'is_preferred' => ['nullable', 'boolean'],
        ];
    }
}
