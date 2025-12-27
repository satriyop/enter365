<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateComponentBrandMappingRequest extends FormRequest
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
            'brand' => ['sometimes', 'string', 'max:50'],
            'product_id' => ['sometimes', 'integer', 'exists:products,id'],
            'brand_sku' => ['sometimes', 'string', 'max:100'],
            'is_preferred' => ['nullable', 'boolean'],
            'is_verified' => ['nullable', 'boolean'],
            'price_factor' => ['nullable', 'numeric', 'min:0.01', 'max:10'],
            'variant_specs' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.exists' => 'Produk tidak ditemukan.',
        ];
    }
}
