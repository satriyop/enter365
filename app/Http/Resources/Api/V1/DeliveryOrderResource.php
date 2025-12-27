<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryOrderResource extends JsonResource
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
            'status' => $this->status,
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
