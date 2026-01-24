<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Purchasing\GoodsReceiptNote
 */
class GoodsReceiptNoteResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   grn_number: string,
     *   purchase_order_id: int,
     *   purchase_order?: array{id: int, po_number: string, contact: array{id: int, name: string}|null},
     *   warehouse_id: int,
     *   warehouse?: array{id: int, code: string, name: string},
     *   receipt_date: string,
     *   status: StatusResource,
     *   supplier_do_number: string|null,
     *   supplier_invoice_number: string|null,
     *   vehicle_number: string|null,
     *   driver_name: string|null,
     *   received_by: int|null,
     *   received_by_user?: array{id: int, name: string},
     *   checked_by: int|null,
     *   checked_by_user?: array{id: int, name: string},
     *   notes: string|null,
     *   total_items: int,
     *   total_quantity_ordered: float,
     *   total_quantity_received: float,
     *   total_quantity_rejected: float,
     *   receiving_progress: float,
     *   items?: \Illuminate\Http\Resources\Json\AnonymousResourceCollection,
     *   can_edit: bool,
     *   can_delete: bool,
     *   can_complete: bool,
     *   can_cancel: bool,
     *   completed_at: string|null,
     *   cancelled_at: string|null,
     *   created_by: int|null,
     *   created_at: string,
     *   updated_at: string
     * }
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
            'status' => new StatusResource($this->status),

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
