<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Inventory\Product;
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
            'sales_tax_ids' => ['nullable', 'array'],
            'sales_tax_ids.*' => ['integer', 'exists:tax_records,id'],
            'purchase_tax_ids' => ['nullable', 'array'],
            'purchase_tax_ids.*' => ['integer', 'exists:tax_records,id'],
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
            'purchase_control_policy' => ['nullable', Rule::in([Product::CONTROL_POLICY_ORDERED, Product::CONTROL_POLICY_RECEIVED])],
            'purchase_description' => ['nullable', 'string'],
            'vendor_pricelists' => ['nullable', 'array'],
            'vendor_pricelists.*.contact_id' => ['required', 'integer', 'exists:contacts,id'],
            'vendor_pricelists.*.min_qty' => ['numeric', 'min:0'],
            'vendor_pricelists.*.unit' => ['nullable', 'string', 'max:20'],
            'vendor_pricelists.*.price' => ['required', 'integer', 'min:0'],
            'vendor_pricelists.*.currency' => ['nullable', 'string', 'size:3'],
            'vendor_pricelists.*.lead_time_days' => ['integer', 'min:0'],
            'vendor_pricelists.*.vendor_product_code' => ['nullable', 'string', 'max:100'],
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
