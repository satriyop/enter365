<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Inventory\InventoryMovement
 */
class InventoryMovementResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   movement_number: string,
     *   product_id: int,
     *   product?: ProductResource,
     *   warehouse_id: int,
     *   warehouse?: WarehouseResource,
     *   type: string,
     *   type_label: string,
     *   quantity: float,
     *   quantity_before: float,
     *   quantity_after: float,
     *   unit_cost: int,
     *   total_cost: int,
     *   reference_type: string|null,
     *   reference_id: int|null,
     *   transfer_warehouse_id: int|null,
     *   transfer_warehouse?: WarehouseResource,
     *   movement_date: string|null,
     *   notes: string|null,
     *   created_by: int|null,
     *   created_by_user?: array{id: int, name: string},
     *   created_at: string|null,
     *   updated_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'movement_number' => $this->movement_number,
            'product_id' => $this->product_id,
            'product' => new ProductResource($this->whenLoaded('product')),
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),
            'type' => $this->type,
            'type_label' => $this->type_label,
            'quantity' => $this->quantity,
            'quantity_before' => $this->quantity_before,
            'quantity_after' => $this->quantity_after,
            'unit_cost' => $this->unit_cost,
            'total_cost' => $this->total_cost,

            // Reference
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,

            // Transfer info
            'transfer_warehouse_id' => $this->transfer_warehouse_id,
            'transfer_warehouse' => new WarehouseResource($this->whenLoaded('transferWarehouse')),

            'movement_date' => $this->movement_date?->toDateString(),
            'notes' => $this->notes,

            // Audit
            'created_by' => $this->created_by,
            'created_by_user' => $this->whenLoaded('createdByUser', fn () => [
                'id' => $this->createdByUser->id,
                'name' => $this->createdByUser->name,
            ]),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
