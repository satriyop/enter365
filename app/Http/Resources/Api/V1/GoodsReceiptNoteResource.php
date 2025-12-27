<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoodsReceiptNoteResource extends JsonResource
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
            'grn_number' => $this->grn_number,
            'purchase_order_id' => $this->purchase_order_id,
            'purchase_order' => $this->whenLoaded('purchaseOrder', fn () => [
                'id' => $this->purchaseOrder->id,
                'po_number' => $this->purchaseOrder->po_number,
                'contact' => $this->purchaseOrder->contact ? [
                    'id' => $this->purchaseOrder->contact->id,
                    'name' => $this->purchaseOrder->contact->name,
                ] : null,
            ]),
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'id' => $this->warehouse->id,
                'code' => $this->warehouse->code,
                'name' => $this->warehouse->name,
            ]),
            'receipt_date' => $this->receipt_date->toDateString(),
            'status' => $this->status,

            // Supplier references
            'supplier_do_number' => $this->supplier_do_number,
            'supplier_invoice_number' => $this->supplier_invoice_number,

            // Shipping info
            'vehicle_number' => $this->vehicle_number,
            'driver_name' => $this->driver_name,

            // Staff tracking
            'received_by' => $this->received_by,
            'received_by_user' => $this->whenLoaded('receivedByUser', fn () => [
                'id' => $this->receivedByUser->id,
                'name' => $this->receivedByUser->name,
            ]),
            'checked_by' => $this->checked_by,
            'checked_by_user' => $this->whenLoaded('checkedByUser', fn () => [
                'id' => $this->checkedByUser->id,
                'name' => $this->checkedByUser->name,
            ]),

            'notes' => $this->notes,

            // Summary
            'total_items' => $this->total_items,
            'total_quantity_ordered' => $this->total_quantity_ordered,
            'total_quantity_received' => $this->total_quantity_received,
            'total_quantity_rejected' => $this->total_quantity_rejected,
            'receiving_progress' => $this->getReceivingProgress(),

            // Items
            'items' => GoodsReceiptNoteItemResource::collection($this->whenLoaded('items')),

            // Workflow permissions
            'can_edit' => $this->canEdit(),
            'can_delete' => $this->canDelete(),
            'can_complete' => $this->canComplete(),
            'can_cancel' => $this->canCancel(),

            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
