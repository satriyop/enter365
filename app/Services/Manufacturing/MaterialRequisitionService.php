<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Manufacturing\MaterialRequisitionServiceInterface;
use App\Enums\DocumentStatus;
use App\Models\Inventory\ProductStock;
use App\Models\Manufacturing\MaterialRequisition;
use App\Models\Manufacturing\MaterialRequisitionItem;
use App\Models\Manufacturing\WorkOrder;
use App\Services\Base\BaseService;
use InvalidArgumentException;

class MaterialRequisitionService extends BaseService implements MaterialRequisitionServiceInterface
{
    public function __construct(
        private MaterialRequisitionNumberGenerator $numberGenerator,
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Create a new material requisition.
     */
    public function create(WorkOrder $wo, array $data = []): MaterialRequisition
    {
        return $this->executeInTransaction('create', function () use ($wo, $data) {
            $mr = new MaterialRequisition([
                'work_order_id' => $wo->id,
                'warehouse_id' => $data['warehouse_id'] ?? $wo->warehouse_id,
                'requested_date' => $data['requested_date'] ?? now()->toDateString(),
                'required_date' => $data['required_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'requested_by' => $data['requested_by'] ?? $this->getUserId(),
            ]);
            $mr->requisition_number = $this->numberGenerator->generate();
            $mr->save();

            $this->populateFromWorkOrder($mr, $wo);

            return $mr->fresh(['items', 'workOrder']);
        }, ['work_order_id' => $wo->id]);
    }

    /**
     * Update an existing material requisition.
     */
    public function update(MaterialRequisition $mr, array $data): MaterialRequisition
    {
        if (! $mr->canBeEdited()) {
            throw new InvalidArgumentException('Material requisition hanya dapat diedit dalam status draft.');
        }

        return $this->executeInTransaction('update', function () use ($mr, $data) {
            $mr->fill($data);
            $mr->save();

            if (isset($data['items'])) {
                $mr->items()->delete();

                foreach ($data['items'] as $itemData) {
                    $this->createItem($mr, $itemData);
                }

                $mr->updateTotals();
                $mr->save();
            }

            return $mr->fresh(['items', 'workOrder']);
        }, ['requisition_id' => $mr->id]);
    }

    /**
     * Delete a material requisition.
     */
    public function delete(MaterialRequisition $mr): bool
    {
        if (! $mr->canBeEdited()) {
            throw new InvalidArgumentException('Hanya material requisition draft yang dapat dihapus.');
        }

        return $this->executeInTransaction('delete', function () use ($mr) {
            $mr->items()->delete();

            return $mr->delete();
        }, ['requisition_id' => $mr->id]);
    }

    /**
     * Approve material requisition.
     */
    public function approve(MaterialRequisition $mr, ?int $userId = null): MaterialRequisition
    {
        if (! $mr->stateMachine()->canApprove()) {
            throw new InvalidArgumentException('Material requisition tidak dapat disetujui.');
        }

        return $this->executeInTransaction('approve', function () use ($mr, $userId) {
            foreach ($mr->items as $item) {
                $item->quantity_approved = $item->quantity_requested;
                $item->quantity_pending = $item->quantity_requested;
                $item->save();
            }

            $mr->transitionTo(DocumentStatus::Approved, $userId);

            return $mr->fresh(['items']);
        }, ['requisition_id' => $mr->id]);
    }

    /**
     * Issue materials.
     */
    public function issue(MaterialRequisition $mr, array $items, ?int $userId = null): MaterialRequisition
    {
        if (! $mr->stateMachine()->canIssue()) {
            throw new InvalidArgumentException('Material requisition tidak dapat dikeluarkan. Pastikan sudah disetujui.');
        }

        return $this->executeInTransaction('issue', function () use ($mr, $items, $userId) {
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

                $mrItem->quantity_issued = (float) $mrItem->quantity_issued + $quantityToIssue;
                $mrItem->calculatePendingQuantity();
                $mrItem->save();
            }

            $mr->issued_by = $userId ?? $this->getUserId();
            $mr->issued_at = now();
            $mr->save();

            if ($mr->isFullyIssued()) {
                $mr->transitionTo(DocumentStatus::Issued, $userId);
            } else {
                $mr->transitionTo(DocumentStatus::Partial, $userId);
            }

            return $mr->fresh(['items']);
        }, ['requisition_id' => $mr->id]);
    }

    /**
     * Cancel material requisition.
     */
    public function cancel(MaterialRequisition $mr, ?string $reason = null, ?int $userId = null): MaterialRequisition
    {
        if (! $mr->stateMachine()->canCancel()) {
            throw new InvalidArgumentException('Material requisition tidak dapat dibatalkan.');
        }

        $mr->transitionTo(DocumentStatus::Cancelled, $userId, [
            'cancellation_reason' => $reason,
        ]);

        return $mr->fresh(['items', 'workOrder']);
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
