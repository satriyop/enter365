<?php

namespace App\Services\Accounting;

use App\Models\Accounting\GoodsReceiptNote;
use App\Models\Accounting\GoodsReceiptNoteItem;
use App\Models\Accounting\PurchaseOrder;
use App\Models\Accounting\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GoodsReceiptNoteService
{
    public function __construct(
        private InventoryService $inventoryService,
        private PurchaseOrderService $purchaseOrderService
    ) {}

    /**
     * Create a new GRN.
     */
    public function create(array $data): GoodsReceiptNote
    {
        return DB::transaction(function () use ($data) {
            $grn = GoodsReceiptNote::create([
                'grn_number' => GoodsReceiptNote::generateGrnNumber(),
                'purchase_order_id' => $data['purchase_order_id'],
                'warehouse_id' => $data['warehouse_id'],
                'receipt_date' => $data['receipt_date'] ?? now()->toDateString(),
                'status' => GoodsReceiptNote::STATUS_DRAFT,
                'supplier_do_number' => $data['supplier_do_number'] ?? null,
                'supplier_invoice_number' => $data['supplier_invoice_number'] ?? null,
                'vehicle_number' => $data['vehicle_number'] ?? null,
                'driver_name' => $data['driver_name'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'] ?? auth()->id(),
            ]);

            return $grn;
        });
    }

    /**
     * Create a GRN from a Purchase Order.
     */
    public function createFromPurchaseOrder(PurchaseOrder $po, array $data): GoodsReceiptNote
    {
        if (! $po->canReceive()) {
            throw new \InvalidArgumentException('PO tidak dapat menerima barang pada status ini.');
        }

        return DB::transaction(function () use ($po, $data) {
            $grn = $this->create([
                'purchase_order_id' => $po->id,
                'warehouse_id' => $data['warehouse_id'],
                'receipt_date' => $data['receipt_date'] ?? now()->toDateString(),
                'supplier_do_number' => $data['supplier_do_number'] ?? null,
                'supplier_invoice_number' => $data['supplier_invoice_number'] ?? null,
                'vehicle_number' => $data['vehicle_number'] ?? null,
                'driver_name' => $data['driver_name'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'] ?? auth()->id(),
            ]);

            // Create GRN items from PO items with remaining quantities
            foreach ($po->items as $poItem) {
                $remainingQty = $poItem->getQuantityRemaining();

                if ($remainingQty > 0) {
                    GoodsReceiptNoteItem::create([
                        'goods_receipt_note_id' => $grn->id,
                        'purchase_order_item_id' => $poItem->id,
                        'product_id' => $poItem->product_id,
                        'quantity_ordered' => $remainingQty,
                        'quantity_received' => 0,
                        'quantity_rejected' => 0,
                        'unit_price' => $poItem->unit_price,
                    ]);
                }
            }

            $grn->updateTotals();

            return $grn->fresh(['items', 'purchaseOrder']);
        });
    }

    /**
     * Update a GRN.
     */
    public function update(GoodsReceiptNote $grn, array $data): GoodsReceiptNote
    {
        if (! $grn->canEdit()) {
            throw new \InvalidArgumentException('GRN tidak dapat diubah pada status ini.');
        }

        $grn->update([
            'receipt_date' => $data['receipt_date'] ?? $grn->receipt_date,
            'supplier_do_number' => $data['supplier_do_number'] ?? $grn->supplier_do_number,
            'supplier_invoice_number' => $data['supplier_invoice_number'] ?? $grn->supplier_invoice_number,
            'vehicle_number' => $data['vehicle_number'] ?? $grn->vehicle_number,
            'driver_name' => $data['driver_name'] ?? $grn->driver_name,
            'notes' => $data['notes'] ?? $grn->notes,
        ]);

        return $grn->fresh();
    }

    /**
     * Delete a GRN.
     */
    public function delete(GoodsReceiptNote $grn): void
    {
        if (! $grn->canDelete()) {
            throw new \InvalidArgumentException('GRN tidak dapat dihapus pada status ini.');
        }

        $grn->items()->delete();
        $grn->delete();
    }

    /**
     * Update a GRN item.
     */
    public function updateItem(GoodsReceiptNoteItem $item, array $data): GoodsReceiptNoteItem
    {
        $grn = $item->goodsReceiptNote;

        if (! $grn->canEdit()) {
            throw new \InvalidArgumentException('Item tidak dapat diubah pada status ini.');
        }

        // Validate received quantity
        if (isset($data['quantity_received'])) {
            $maxAllowed = $item->quantity_ordered;
            if ($data['quantity_received'] > $maxAllowed) {
                throw new \InvalidArgumentException("Jumlah terima tidak boleh melebihi {$maxAllowed}.");
            }
        }

        // Validate rejected quantity
        $totalQty = ($data['quantity_received'] ?? $item->quantity_received) + ($data['quantity_rejected'] ?? $item->quantity_rejected);
        if ($totalQty > $item->quantity_ordered) {
            throw new \InvalidArgumentException('Jumlah terima + ditolak tidak boleh melebihi jumlah pesan.');
        }

        $item->update([
            'quantity_received' => $data['quantity_received'] ?? $item->quantity_received,
            'quantity_rejected' => $data['quantity_rejected'] ?? $item->quantity_rejected,
            'rejection_reason' => $data['rejection_reason'] ?? $item->rejection_reason,
            'quality_notes' => $data['quality_notes'] ?? $item->quality_notes,
            'lot_number' => $data['lot_number'] ?? $item->lot_number,
            'expiry_date' => $data['expiry_date'] ?? $item->expiry_date,
        ]);

        $grn->updateTotals();

        return $item->fresh();
    }

    /**
     * Start receiving workflow.
     */
    public function startReceiving(GoodsReceiptNote $grn, int $userId): GoodsReceiptNote
    {
        if ($grn->status !== GoodsReceiptNote::STATUS_DRAFT) {
            throw new \InvalidArgumentException('GRN harus dalam status draft untuk memulai penerimaan.');
        }

        if ($grn->items()->count() === 0) {
            throw new \InvalidArgumentException('GRN tidak memiliki item untuk diterima.');
        }

        $grn->update([
            'status' => GoodsReceiptNote::STATUS_RECEIVING,
            'received_by' => $userId,
        ]);

        return $grn->fresh();
    }

    /**
     * Complete the GRN and update inventory.
     */
    public function complete(GoodsReceiptNote $grn, int $userId): GoodsReceiptNote
    {
        if (! $grn->canComplete()) {
            throw new \InvalidArgumentException('GRN tidak dapat diselesaikan. Pastikan ada item yang telah diterima.');
        }

        return DB::transaction(function () use ($grn, $userId) {
            $warehouse = Warehouse::findOrFail($grn->warehouse_id);
            $purchaseOrder = $grn->purchaseOrder;

            // Process each item with received quantity
            foreach ($grn->items()->where('quantity_received', '>', 0)->get() as $item) {
                $product = $item->product;

                // Only create inventory movement for products that track inventory
                if ($product && $product->track_inventory) {
                    $this->inventoryService->stockIn(
                        $product,
                        $warehouse,
                        $item->quantity_received,
                        $item->unit_price,
                        "GRN: {$grn->grn_number}",
                        GoodsReceiptNote::class,
                        $grn->id
                    );
                }

                // Update PO item received quantity
                $poItem = $item->purchaseOrderItem;
                if ($poItem) {
                    $poItem->receive($item->quantity_received);
                }
            }

            // Update PO receiving status
            $purchaseOrder->updateReceivingStatus();
            $purchaseOrder->save();

            $grn->update([
                'status' => GoodsReceiptNote::STATUS_COMPLETED,
                'checked_by' => $userId,
                'completed_at' => now(),
            ]);

            return $grn->fresh();
        });
    }

    /**
     * Cancel a GRN.
     */
    public function cancel(GoodsReceiptNote $grn, int $userId): GoodsReceiptNote
    {
        if (! $grn->canCancel()) {
            throw new \InvalidArgumentException('GRN tidak dapat dibatalkan pada status ini.');
        }

        $grn->update([
            'status' => GoodsReceiptNote::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        return $grn->fresh();
    }

    /**
     * Get GRNs for a Purchase Order.
     */
    public function getForPurchaseOrder(PurchaseOrder $po): Collection
    {
        return GoodsReceiptNote::where('purchase_order_id', $po->id)
            ->with(['items', 'warehouse', 'receivedByUser'])
            ->orderByDesc('created_at')
            ->get();
    }
}
