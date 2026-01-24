<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Inventory\StockOpnameItem
 */
class StockOpnameItemResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   stock_opname_id: int,
     *   product_id: int,
     *   product?: array{id: int, sku: string, name: string, unit: string},
     *   system_quantity: float,
     *   system_cost: int,
     *   system_value: int,
     *   counted_quantity: float|null,
     *   is_counted: bool,
     *   counted_at: string|null,
     *   variance_quantity: float,
     *   variance_value: int,
     *   variance_percentage: float|null,
     *   has_variance: bool,
     *   has_positive_variance: bool,
     *   has_negative_variance: bool,
     *   notes: string|null,
     *   created_at: string,
     *   updated_at: string
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stock_opname_id' => $this->stock_opname_id,
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'sku' => $this->product->sku,
                'name' => $this->product->name,
                'unit' => $this->product->unit,
            ]),

            // System quantities (snapshot)
            'system_quantity' => $this->system_quantity,
            'system_cost' => $this->system_cost,
            'system_value' => $this->system_value,

            // Counted quantities
            'counted_quantity' => $this->counted_quantity,
            'is_counted' => $this->isCounted(),
            'counted_at' => $this->counted_at?->toIso8601String(),

            // Variance
            'variance_quantity' => $this->variance_quantity,
            'variance_value' => $this->variance_value,
            'variance_percentage' => $this->isCounted() ? $this->getVariancePercentage() : null,
            'has_variance' => $this->hasVariance(),
            'has_positive_variance' => $this->hasPositiveVariance(),
            'has_negative_variance' => $this->hasNegativeVariance(),

            'notes' => $this->notes,

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
