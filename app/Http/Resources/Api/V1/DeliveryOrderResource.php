<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Sales\DeliveryOrder
 */
class DeliveryOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   do_number: string,
     *   invoice_id: int|null,
     *   invoice?: array{id: int, invoice_number: string, total_amount: int},
     *   contact_id: int,
     *   contact?: array{id: int, name: string, address: string|null, phone: string|null},
     *   warehouse_id: int,
     *   warehouse?: array{id: int, name: string},
     *   do_date: string,
     *   shipping_date: string|null,
     *   received_date: string|null,
     *   shipping_address: string,
     *   shipping_method: string|null,
     *   tracking_number: string|null,
     *   driver_name: string|null,
     *   vehicle_number: string|null,
     *   notes: string|null,
     *   status: StatusResource,
     *   received_by: string|null,
     *   delivery_notes: string|null,
     *   items?: \Illuminate\Http\Resources\Json\AnonymousResourceCollection,
     *   items_count?: int,
     *   total_quantity?: float,
     *   total_delivered?: float,
     *   delivery_progress?: float,
     *   created_by: int|null,
     *   creator?: array{id: int, name: string},
     *   confirmed_by: int|null,
     *   confirmed_at: string|null,
     *   shipped_by: int|null,
     *   shipped_at: string|null,
     *   delivered_by: int|null,
     *   delivered_at: string|null,
     *   created_at: string,
     *   updated_at: string
     * }
     */
    public function toArray(Request $request): array
    {        return [
            'id' => $this->id,
            'do_number' => $this->do_number,
            'invoice_id' => $this->invoice_id,
            'invoice' => $this->whenLoaded('invoice', fn () => [
                'id' => $this->invoice->id,
                'invoice_number' => $this->invoice->invoice_number,
                'total_amount' => $this->invoice->total_amount,
            ]),
            'contact_id' => $this->contact_id,
            'contact' => $this->whenLoaded('contact', fn () => [
                'id' => $this->contact->id,
                'name' => $this->contact->name,
                'address' => $this->contact->address,
                'phone' => $this->contact->phone,
            ]),
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
            ]),
            'do_date' => $this->do_date->format('Y-m-d'),
            'shipping_date' => $this->shipping_date?->format('Y-m-d'),
            'received_date' => $this->received_date?->format('Y-m-d'),
            'shipping_address' => $this->shipping_address,
            'shipping_method' => $this->shipping_method,
            'tracking_number' => $this->tracking_number,
            'driver_name' => $this->driver_name,
            'vehicle_number' => $this->vehicle_number,
            'notes' => $this->notes,
            'status' => new StatusResource($this->status),
            'received_by' => $this->received_by,
            'delivery_notes' => $this->delivery_notes,
            'items' => DeliveryOrderItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),
            'total_quantity' => $this->when($this->relationLoaded('items'), fn () => $this->getTotalQuantity()),
            'total_delivered' => $this->when($this->relationLoaded('items'), fn () => $this->getTotalDeliveredQuantity()),
            'delivery_progress' => $this->when($this->relationLoaded('items'), fn () => $this->getDeliveryProgress()),
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'confirmed_by' => $this->confirmed_by,
            'confirmed_at' => $this->confirmed_at?->format('Y-m-d H:i:s'),
            'shipped_by' => $this->shipped_by,
            'shipped_at' => $this->shipped_at?->format('Y-m-d H:i:s'),
            'delivered_by' => $this->delivered_by,
            'delivered_at' => $this->delivered_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
