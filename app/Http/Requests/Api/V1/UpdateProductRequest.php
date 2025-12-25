<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sku' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('products', 'sku')->ignore($this->route('product')),
            ],
            'name' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'type' => ['sometimes', Rule::in([Product::TYPE_PRODUCT, Product::TYPE_SERVICE])],
            'category_id' => ['nullable', 'exists:product_categories,id'],
            'unit' => ['sometimes', 'string', 'max:20'],
            'purchase_price' => ['sometimes', 'integer', 'min:0'],
            'selling_price' => ['sometimes', 'integer', 'min:0'],
            'tax_rate' => ['numeric', 'min:0', 'max:100'],
            'is_taxable' => ['boolean'],
            'track_inventory' => ['boolean'],
            'min_stock' => ['integer', 'min:0'],
            'inventory_account_id' => ['nullable', 'exists:accounts,id'],
            'cogs_account_id' => ['nullable', 'exists:accounts,id'],
            'sales_account_id' => ['nullable', 'exists:accounts,id'],
            'purchase_account_id' => ['nullable', 'exists:accounts,id'],
            'is_active' => ['boolean'],
            'is_purchasable' => ['boolean'],
            'is_sellable' => ['boolean'],
            'barcode' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('products', 'barcode')->ignore($this->route('product')),
            ],
            'brand' => ['nullable', 'string', 'max:100'],
            'custom_fields' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'Tipe produk harus product atau service.',
            'sku.unique' => 'SKU sudah digunakan.',
            'barcode.unique' => 'Barcode sudah digunakan.',
            'category_id.exists' => 'Kategori tidak ditemukan.',
        ];
    }
}
