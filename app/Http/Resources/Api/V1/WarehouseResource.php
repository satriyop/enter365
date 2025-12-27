<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Accounting\Warehouse
 */
class WarehouseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'address' => $this->address,
            'phone' => $this->phone,
            'contact_person' => $this->contact_person,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'notes' => $this->notes,

            // Related counts
            'product_stocks_count' => $this->whenCounted('productStocks'),
            'product_stocks' => ProductStockResource::collection($this->whenLoaded('productStocks')),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
