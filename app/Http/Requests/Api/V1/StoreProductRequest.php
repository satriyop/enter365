<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sku' => ['nullable', 'string', 'max:50', 'unique:products,sku'],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::in([Product::TYPE_PRODUCT, Product::TYPE_SERVICE])],
            'category_id' => ['nullable', 'exists:product_categories,id'],
            'unit' => ['required', 'string', 'max:20'],
            'purchase_price' => ['required', 'integer', 'min:0'],
            'selling_price' => ['required', 'integer', 'min:0'],
            'tax_rate' => ['numeric', 'min:0', 'max:100'],
            'is_taxable' => ['boolean'],
            'track_inventory' => ['boolean'],
            'min_stock' => ['integer', 'min:0'],
            'current_stock' => ['integer', 'min:0'],
            'inventory_account_id' => ['nullable', 'exists:accounts,id'],
            'cogs_account_id' => ['nullable', 'exists:accounts,id'],
            'sales_account_id' => ['nullable', 'exists:accounts,id'],
            'purchase_account_id' => ['nullable', 'exists:accounts,id'],
            'is_active' => ['boolean'],
            'is_purchasable' => ['boolean'],
            'is_sellable' => ['boolean'],
            'barcode' => ['nullable', 'string', 'max:50', 'unique:products,barcode'],
            'brand' => ['nullable', 'string', 'max:100'],
            'custom_fields' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama produk wajib diisi.',
            'type.required' => 'Tipe produk wajib diisi.',
            'type.in' => 'Tipe produk harus product atau service.',
            'unit.required' => 'Satuan wajib diisi.',
            'purchase_price.required' => 'Harga beli wajib diisi.',
            'selling_price.required' => 'Harga jual wajib diisi.',
            'sku.unique' => 'SKU sudah digunakan.',
            'barcode.unique' => 'Barcode sudah digunakan.',
            'category_id.exists' => 'Kategori tidak ditemukan.',
        ];
    }
}
