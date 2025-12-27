<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Accounting\ComponentBrandMapping;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ComponentBrandMapping
 */
class ComponentBrandMappingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'component_standard_id' => $this->component_standard_id,
            'brand' => $this->brand,
            'brand_label' => ComponentBrandMapping::getBrands()[$this->brand] ?? ucfirst($this->brand),
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
                'purchase_price' => $this->product->purchase_price,
                'selling_price' => $this->product->selling_price,
                'current_stock' => $this->product->current_stock,
            ]),
            'brand_sku' => $this->brand_sku,
            'is_preferred' => $this->is_preferred,
            'is_verified' => $this->is_verified,
            'price_factor' => (float) $this->price_factor,
            'variant_specs' => $this->variant_specs,
            'notes' => $this->notes,
            'verified_by' => $this->verified_by,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
