<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\ComponentBrandMapping;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateBomFromTemplateRequest extends FormRequest
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
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'target_brand' => ['nullable', 'string', Rule::in(array_keys(ComponentBrandMapping::getBrands()))],
            'name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'output_quantity' => ['nullable', 'numeric', 'min:0.01'],
            'quantity_overrides' => ['nullable', 'array'],
            'quantity_overrides.*' => ['numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.required' => 'Produk output harus dipilih.',
            'product_id.exists' => 'Produk tidak ditemukan.',
            'target_brand.in' => 'Brand tidak valid.',
            'output_quantity.min' => 'Kuantitas output harus lebih dari 0.',
        ];
    }
}
