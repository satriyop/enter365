<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class AddBomToVariantGroupRequest extends FormRequest
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
            'bom_id' => ['required', 'integer', 'exists:boms,id'],
            'variant_name' => ['nullable', 'string', 'max:100'],
            'variant_label' => ['nullable', 'string', 'max:255'],
            'is_primary_variant' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bom_id.required' => 'BOM harus dipilih.',
            'bom_id.exists' => 'BOM tidak ditemukan.',
        ];
    }
}
