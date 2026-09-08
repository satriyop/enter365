<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Inventory\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Inventory\Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array{
     *   id: int,
     *   sku: string,
     *   name: string,
     *   description: string|null,
     *   type: string,
     *   type_label: string,
     *   category_id: int|null,
     *   category?: ProductCategoryResource,
     *   unit: string,
     *   purchase_price: int,
     *   selling_price: int,
     *   selling_price_with_tax: int,
     *   selling_tax_amount: int,
     *   tax_rate: float,
     *   is_taxable: bool,
     *   sales_taxes: list<array{id: int, code: string, name: string, rate: float, applicability: string}>,
     *   purchase_taxes: list<array{id: int, code: string, name: string, rate: float, applicability: string}>,
     *   profit_margin: float,
     *   markup: float,
     *   track_inventory: bool,
     *   min_stock: float,
     *   current_stock: float,
     *   is_low_stock: bool,
     *   is_out_of_stock: bool,
     *   inventory_account_id: int|null,
     *   inventory_account?: AccountResource,
     *   cogs_account_id: int|null,
     *   cogs_account?: AccountResource,
     *   sales_account_id: int|null,
     *   sales_account?: AccountResource,
     *   purchase_account_id: int|null,
     *   purchase_account?: AccountResource,
     *   is_active: bool,
     *   is_purchasable: bool,
     *   is_sellable: bool,
     *   purchase_control_policy: string,
     *   purchase_description: string|null,
     *   vendor_pricelists: list<array{id: int, contact_id: int, min_qty: float, unit: string, price: int, currency: string, lead_time_days: int, vendor_product_code: string|null}>,
     *   barcode: string|null,
     *   brand: string|null,
     *   custom_fields: array<string, mixed>|null,
     *   created_at: string|null,
     *   updated_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'type_label' => $this->type === 'product' ? 'Produk' : 'Jasa',
            'category_id' => $this->category_id,
            'category' => new ProductCategoryResource($this->whenLoaded('category')),
            'unit' => $this->unit,

            // Pricing
            'purchase_price' => $this->purchase_price,
            'selling_price' => $this->selling_price,
            'selling_price_with_tax' => $this->selling_price_with_tax,
            'selling_tax_amount' => $this->selling_tax_amount,
            'tax_rate' => (float) $this->tax_rate,
            'is_taxable' => $this->is_taxable,
            'sales_taxes' => $this->whenLoaded('salesTaxes', fn () => $this->salesTaxes->map(fn ($tax) => [
                'id' => $tax->id,
                'code' => $tax->code,
                'name' => $tax->name,
                'rate' => (float) $tax->rate,
                'applicability' => $tax->applicability,
            ])->values()->all(), []),
            'purchase_taxes' => $this->whenLoaded('purchaseTaxes', fn () => $this->purchaseTaxes->map(fn ($tax) => [
                'id' => $tax->id,
                'code' => $tax->code,
                'name' => $tax->name,
                'rate' => (float) $tax->rate,
                'applicability' => $tax->applicability,
            ])->values()->all(), []),
            'profit_margin' => $this->profit_margin,
            'markup' => $this->markup,

            // Inventory
            'track_inventory' => $this->track_inventory,
            'min_stock' => $this->min_stock,
            'current_stock' => $this->current_stock,
            'is_low_stock' => $this->isLowStock(),
            'is_out_of_stock' => $this->isOutOfStock(),

            // Accounting links
            'inventory_account_id' => $this->inventory_account_id,
            'inventory_account' => new AccountResource($this->whenLoaded('inventoryAccount')),
            'cogs_account_id' => $this->cogs_account_id,
            'cogs_account' => new AccountResource($this->whenLoaded('cogsAccount')),
            'sales_account_id' => $this->sales_account_id,
            'sales_account' => new AccountResource($this->whenLoaded('salesAccount')),
            'purchase_account_id' => $this->purchase_account_id,
            'purchase_account' => new AccountResource($this->whenLoaded('purchaseAccount')),

            // Status
            'is_active' => $this->is_active,
            'is_purchasable' => $this->is_purchasable,
            'is_sellable' => $this->is_sellable,
            'purchase_control_policy' => $this->purchase_control_policy ?? Product::CONTROL_POLICY_RECEIVED,
            'purchase_description' => $this->purchase_description,
            'vendor_pricelists' => $this->whenLoaded(
                'vendorPricelists',
                fn () => ProductVendorPricelistResource::collection($this->vendorPricelists),
                []
            ),

            // Additional info
            'barcode' => $this->barcode,
            'brand' => $this->brand,
            'custom_fields' => $this->custom_fields,

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
