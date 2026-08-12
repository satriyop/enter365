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
     * @return array{
     *   id: int,
     *   stock_opname_id: int,
     *   product_id: int,
     *   product?: array{id: int, sku: string, name: string, unit: string},
     *   system_quantity: float,
     *   actual_quantity: float,
     *   difference_quantity: float,
     *   unit_cost: int,
     *   difference_value: int,
     *   notes: string|null,
     *   created_at: string
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var \Carbon\Carbon|null $createdAt */
        $createdAt = $this->created_at;

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
            'system_quantity' => (float) $this->system_quantity,
            // Preserve null so SPA can show "Not counted" (PHP (float) null === 0.0)
            'actual_quantity' => $this->counted_quantity === null
                ? null
                : (float) $this->counted_quantity,
            'difference_quantity' => $this->counted_quantity === null
                ? null
                : (float) ($this->counted_quantity - $this->system_quantity),
            'unit_cost' => $this->system_cost,
            'difference_value' => $this->counted_quantity === null
                ? null
                : (int) round(($this->counted_quantity - $this->system_quantity) * $this->system_cost),
            'notes' => $this->notes,
            'created_at' => $createdAt?->toIso8601String(),
        ];
    }
}
