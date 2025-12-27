<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MrpDemandResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mrp_run_id' => $this->mrp_run_id,

            // Product
            'product_id' => $this->product_id,
            'product' => $this->when($this->relationLoaded('product'), function () {
                return $this->product ? [
                    'id' => $this->product->id,
                    'sku' => $this->product->sku,
                    'name' => $this->product->name,
                    'unit' => $this->product->unit,
                    'procurement_type' => $this->product->procurement_type,
                ] : null;
            }),

            // Demand source
            'demand_source_type' => $this->getDemandSourceTypeName(),
            'demand_source_id' => $this->demand_source_id,
            'demand_source_number' => $this->demand_source_number,

            // Timing
            'required_date' => $this->required_date?->toDateString(),
            'week_bucket' => $this->week_bucket,

            // Quantities
            'quantity_required' => (float) $this->quantity_required,
            'quantity_on_hand' => (float) $this->quantity_on_hand,
            'quantity_on_order' => (float) $this->quantity_on_order,
            'quantity_reserved' => (float) $this->quantity_reserved,
            'quantity_available' => (float) $this->quantity_available,
            'quantity_short' => (float) $this->quantity_short,

            // Meta
            'has_shortage' => $this->hasShortage(),
            'bom_level' => $this->bom_level,
            'is_exploded' => $this->isExploded(),

            // Warehouse
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->when($this->relationLoaded('warehouse'), function () {
                return $this->warehouse ? [
                    'id' => $this->warehouse->id,
                    'name' => $this->warehouse->name,
                ] : null;
            }),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
