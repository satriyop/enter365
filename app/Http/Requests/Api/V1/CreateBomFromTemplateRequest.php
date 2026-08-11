<?php

namespace App\Http\Requests\Api\V1;

use App\Support\AddonExtensions;
use Illuminate\Foundation\Http\FormRequest;

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
        return array_merge([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'output_quantity' => ['nullable', 'numeric', 'min:0.01'],
            'quantity_overrides' => ['nullable', 'array'],
            'quantity_overrides.*' => ['numeric', 'min:0'],
        ], AddonExtensions::validationRules('create_bom_from_template'));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.required' => 'Produk output harus dipilih.',
            'product_id.exists' => 'Produk tidak ditemukan.',
            'output_quantity.min' => 'Kuantitas output harus lebih dari 0.',
        ];
    }
}
