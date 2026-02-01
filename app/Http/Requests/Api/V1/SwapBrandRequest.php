<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SwapBrandRequest extends FormRequest
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
            'target_brand' => ['required', 'string'],
            'create_variant' => ['nullable', 'boolean'],
            'variant_group_id' => ['nullable', 'integer', 'exists:bom_variant_groups,id'],
        ];
    }
}
