<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Accounting\Product
 */
class ProductResource extends JsonResource
{
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

            // Additional info
            'barcode' => $this->barcode,
            'brand' => $this->brand,
            'custom_fields' => $this->custom_fields,

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
