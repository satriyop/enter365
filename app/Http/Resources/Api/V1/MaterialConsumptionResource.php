<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialConsumptionResource extends JsonResource
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
            'work_order_id' => $this->work_order_id,
            'work_order_item_id' => $this->work_order_item_id,

            // Quantities
            'quantity_consumed' => (float) $this->quantity_consumed,
            'quantity_scrapped' => (float) $this->quantity_scrapped,
            'total_quantity' => $this->getTotalQuantity(),
            'scrap_reason' => $this->scrap_reason,
            'unit' => $this->unit,

            // Costs
            'unit_cost' => $this->unit_cost,
            'total_cost' => $this->total_cost,

            // Details
            'consumed_date' => $this->consumed_date?->toDateString(),
            'batch_number' => $this->batch_number,
            'notes' => $this->notes,

            // Relationships
            'product_id' => $this->product_id,
            'product' => $this->when($this->relationLoaded('product'), function () {
                return $this->product ? [
                    'id' => $this->product->id,
                    'sku' => $this->product->sku,
                    'name' => $this->product->name,
                    'unit' => $this->product->unit,
                ] : null;
            }),

            'consumed_by' => $this->consumed_by,
            'consumer' => $this->when($this->relationLoaded('consumer'), function () {
                return $this->consumer ? [
                    'id' => $this->consumer->id,
                    'name' => $this->consumer->name,
                ] : null;
            }),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
