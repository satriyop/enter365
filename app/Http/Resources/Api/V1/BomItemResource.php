<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Manufacturing\BomItem
 */
class BomItemResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   bom_id: int,
     *   type: string,
     *   product_id: int|null,
     *   product?: array{id: int, name: string, sku: string},
     *   description: string,
     *   quantity: float,
     *   unit: string,
     *   unit_cost: int,
     *   total_cost: int,
     *   waste_percentage: float,
     *   effective_quantity: float,
     *   sort_order: int,
     *   notes: string|null,
     *   component_standard_id: int|null,
     *   created_at: string,
     *   updated_at: string
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bom_id' => $this->bom_id,
            'type' => $this->type,
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ]),
            'description' => $this->description,
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit,
            'unit_cost' => $this->unit_cost,
            'total_cost' => $this->total_cost,
            'waste_percentage' => (float) $this->waste_percentage,
            'effective_quantity' => $this->getEffectiveQuantity(),
            'sort_order' => $this->sort_order,
            'notes' => $this->notes,
            'component_standard_id' => $this->component_standard_id,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
