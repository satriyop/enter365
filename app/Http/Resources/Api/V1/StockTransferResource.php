<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Inventory\StockTransfer
 */
class StockTransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transfer_number' => $this->transfer_number,
            'operation_type' => $this->operation_type,
            'from_warehouse_id' => $this->from_warehouse_id,
            'from_warehouse' => $this->whenLoaded('fromWarehouse', fn () => [
                'id' => $this->fromWarehouse->id,
                'code' => $this->fromWarehouse->code,
                'name' => $this->fromWarehouse->name,
            ]),
            'to_warehouse_id' => $this->to_warehouse_id,
            'to_warehouse' => $this->whenLoaded('toWarehouse', fn () => [
                'id' => $this->toWarehouse->id,
                'code' => $this->toWarehouse->code,
                'name' => $this->toWarehouse->name,
            ]),
            'contact_id' => $this->contact_id,
            'contact' => $this->whenLoaded('contact', fn () => $this->contact ? [
                'id' => $this->contact->id,
                'name' => $this->contact->name,
                'code' => $this->contact->code,
            ] : null),
            'scheduled_date' => $this->scheduled_date?->toDateString(),
            'source_document' => $this->source_document,
            'status' => new StatusResource($this->status),
            'notes' => $this->notes,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'items' => StockTransferItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
