<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreComponentBrandMappingRequest extends FormRequest
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
            'brand' => ['required', 'string', 'max:50'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'brand_sku' => ['required', 'string', 'max:100'],
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
            'brand.required' => 'Brand harus diisi.',
            'product_id.required' => 'Produk harus dipilih.',
            'product_id.exists' => 'Produk tidak ditemukan.',
            'brand_sku.required' => 'SKU brand harus diisi.',
        ];
    }
}
