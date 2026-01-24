<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Inventory\ProductStock
 */
class ProductStockResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   product_id: int,
     *   product?: ProductResource,
     *   warehouse_id: int,
     *   warehouse?: WarehouseResource,
     *   quantity: float,
     *   average_cost: int,
     *   total_value: int,
     *   created_at: string|null,
     *   updated_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product' => new ProductResource($this->whenLoaded('product')),
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),
            'quantity' => $this->quantity,
            'average_cost' => $this->average_cost,
            'total_value' => $this->total_value,

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
