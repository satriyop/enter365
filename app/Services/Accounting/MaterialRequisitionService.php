<?php

namespace App\Services\Accounting;

use App\Models\Accounting\MaterialRequisition;
use App\Models\Accounting\MaterialRequisitionItem;
use App\Models\Accounting\ProductStock;
use App\Models\Accounting\WorkOrder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MaterialRequisitionService
{
    /**
     * Create material requisition from work order.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(WorkOrder $wo, array $data = []): MaterialRequisition
    {
        return DB::transaction(function () use ($wo, $data) {
            $mr = new MaterialRequisition([
                'work_order_id' => $wo->id,
                'warehouse_id' => $data['warehouse_id'] ?? $wo->warehouse_id,
                'status' => MaterialRequisition::STATUS_DRAFT,
                'requested_date' => $data['requested_date'] ?? now(),
                'required_date' => $data['required_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'requested_by' => $data['requested_by'] ?? auth()->id(),
            ]);
            $mr->requisition_number = MaterialRequisition::generateRequisitionNumber();
            $mr->save();

            // Populate items from work order
            $this->populateFromWorkOrder($mr, $wo);

            return $mr->fresh(['items', 'workOrder']);
        });
    }

    /**
     * Update material requisition.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(MaterialRequisition $mr, array $data): MaterialRequisition
    {
        if (! $mr->canBeEdited()) {
            throw new InvalidArgumentException('Material requisition hanya dapat diedit dalam status draft.');
        }

        return DB::transaction(function () use ($mr, $data) {
            $mr->fill($data);
            $mr->save();

            // Update items if provided
            if (isset($data['items'])) {
                $mr->items()->delete();

                foreach ($data['items'] as $itemData) {
                    $this->createItem($mr, $itemData);
                }

                $mr->updateTotals();
                $mr->save();
            }

            return $mr->fresh(['items', 'workOrder']);
        });
    }

    /**
     * Delete material requisition.
     */
    public function delete(MaterialRequisition $mr): bool
    {
        if (! $mr->canBeEdited()) {
            throw new InvalidArgumentException('Hanya material requisition draft yang dapat dihapus.');
        }

        return DB::transaction(function () use ($mr) {
            $mr->items()->delete();

            return $mr->delete();
        });
    }

    /**
     * Approve material requisition.
     */
    public function approve(MaterialRequisition $mr, ?int $userId = null): MaterialRequisition
    {
        if (! $mr->canBeApproved()) {
            throw new InvalidArgumentException('Material requisition tidak dapat disetujui.');
        }

        return DB::transaction(function () use ($mr, $userId) {
            // Set approved quantities equal to requested
            foreach ($mr->items as $item) {
                $item->quantity_approved = $item->quantity_requested;
                $item->quantity_pending = $item->quantity_requested;
                $item->save();
            }

            $mr->status = MaterialRequisition::STATUS_APPROVED;
            $mr->approved_by = $userId ?? auth()->id();
            $mr->approved_at = now();
            $mr->save();

            return $mr->fresh(['items']);
        });
    }

    /**
     * Issue materials.
     *
     * @param  array<array<string, mixed>>  $items
     */
    public function issue(MaterialRequisition $mr, array $items, ?int $userId = null): MaterialRequisition
    {
        if (! $mr->canBeIssued()) {
            throw new InvalidArgumentException('Material requisition tidak dapat dikeluarkan. Pastikan sudah disetujui.');
        }

        return DB::transaction(function () use ($mr, $items, $userId) {
            foreach ($items as $issueData) {
                $mrItem = MaterialRequisitionItem::findOrFail($issueData['item_id']);

                if ($mrItem->material_requisition_id !== $mr->id) {
                    throw new InvalidArgumentException('Item tidak ditemukan dalam requisition ini.');
                }

                $quantityToIssue = $issueData['quantity'];

                if ($quantityToIssue > $mrItem->quantity_pending) {
                    throw new InvalidArgumentException(
                        'Tidak dapat mengeluarkan lebih dari yang pending. '.
                        "Pending: {$mrItem->quantity_pending}, Diminta: {$quantityToIssue}"
                    );
                }

                // Update product stock
                $stock = ProductStock::where('product_id', $mrItem->product_id)
                    ->where('warehouse_id', $mr->warehouse_id)
                    ->first();

                if ($stock) {
                    $availableQty = (float) $stock->quantity - (float) $stock->reserved_quantity;
                    if ($availableQty < $quantityToIssue) {
                        $product = $mrItem->product;
                        throw new InvalidArgumentException(
                            "Stok tidak mencukupi untuk {$product->name}. ".
                            "Tersedia: {$availableQty}, Diminta: {$quantityToIssue}"
                        );
                    }
                }

                // Update item
                $mrItem->quantity_issued = (float) $mrItem->quantity_issued + $quantityToIssue;
                $mrItem->calculatePendingQuantity();
                $mrItem->save();
            }

            // Update MR status
            $mr->issued_by = $userId ?? auth()->id();
            $mr->issued_at = now();

            if ($mr->isFullyIssued()) {
                $mr->status = MaterialRequisition::STATUS_ISSUED;
            } else {
                $mr->status = MaterialRequisition::STATUS_PARTIAL;
            }

            $mr->save();

            return $mr->fresh(['items']);
        });
    }

    /**
     * Cancel material requisition.
     */
    public function cancel(MaterialRequisition $mr): MaterialRequisition
    {
        if (! $mr->canBeCancelled()) {
            throw new InvalidArgumentException('Material requisition tidak dapat dibatalkan.');
        }

        $mr->status = MaterialRequisition::STATUS_CANCELLED;
        $mr->save();

        return $mr->fresh();
    }

    /**
     * Populate items from work order.
     */
    public function populateFromWorkOrder(MaterialRequisition $mr, WorkOrder $wo): void
    {
        foreach ($wo->materialItems as $woItem) {
            if (! $woItem->product_id) {
                continue;
            }

            $remainingQty = $woItem->getRemainingQuantity();
            if ($remainingQty <= 0) {
                continue;
            }

            MaterialRequisitionItem::create([
                'material_requisition_id' => $mr->id,
                'work_order_item_id' => $woItem->id,
                'product_id' => $woItem->product_id,
                'quantity_requested' => $remainingQty,
                'quantity_approved' => 0,
                'quantity_issued' => 0,
                'quantity_pending' => 0,
                'unit' => $woItem->unit,
            ]);
        }

        $mr->updateTotals();
        $mr->save();
    }

    /**
     * Create requisition item.
     *
     * @param  array<string, mixed>  $data
     */
    private function createItem(MaterialRequisition $mr, array $data): MaterialRequisitionItem
    {
        return MaterialRequisitionItem::create([
            'material_requisition_id' => $mr->id,
            'work_order_item_id' => $data['work_order_item_id'] ?? null,
            'product_id' => $data['product_id'],
            'quantity_requested' => $data['quantity_requested'] ?? $data['quantity'] ?? 0,
            'quantity_approved' => 0,
            'quantity_issued' => 0,
            'quantity_pending' => 0,
            'unit' => $data['unit'] ?? null,
            'warehouse_location' => $data['warehouse_location'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
    }
}
