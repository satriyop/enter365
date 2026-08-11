<?php

namespace App\Http\Requests\Api\V1\ElectricalPanel;

use Illuminate\Foundation\Http\FormRequest;

class BulkAcceptSuggestionsRequest extends FormRequest
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
            'mappings' => ['required', 'array', 'min:1', 'max:100'],
            'mappings.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'mappings.*.component_standard_id' => ['required', 'integer', 'exists:component_standards,id'],
            'mappings.*.brand_sku' => ['nullable', 'string', 'max:100'],
            'mappings.*.is_preferred' => ['nullable', 'boolean'],
        ];
    }
}
