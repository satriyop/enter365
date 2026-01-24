<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Manufacturing\ComponentBrandMapping;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Manufacturing\ComponentBrandMapping
 */
class ComponentBrandMappingResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   component_standard_id: int,
     *   brand: string,
     *   brand_label: string,
     *   product_id: int|null,
     *   product?: array{id: int, name: string, sku: string, purchase_price: int, selling_price: int, current_stock: float},
     *   brand_sku: string|null,
     *   is_preferred: bool,
     *   is_verified: bool,
     *   price_factor: float,
     *   variant_specs: array<string, mixed>|null,
     *   notes: string|null,
     *   verified_by: int|null,
     *   verified_at: string|null,
     *   created_at: string|null,
     *   updated_at: string|null
     * }
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
            'variant_specs' => (array) ($this->variant_specs ?? []),
            'notes' => $this->notes,
            'verified_by' => $this->verified_by,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
