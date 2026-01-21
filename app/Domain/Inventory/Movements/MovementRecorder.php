<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Movements;

use App\Contracts\Events\EventDispatcherInterface;
use App\Domain\Inventory\Movements\Events\InventoryAdjusted;
use App\Domain\Inventory\Movements\Events\InventoryIssued;
use App\Domain\Inventory\Movements\Events\InventoryReceived;
use App\Domain\Inventory\Movements\Events\InventoryTransferred;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\ProductStock;

/**
 * Records all inventory movements with proper event dispatching.
 *
 * Responsibilities:
 * - Validate movements before execution
 * - Update stock quantities
 * - Create movement records for audit trail
 * - Dispatch domain events
 */
class MovementRecorder
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private MovementValidator $validator
    ) {}

    /**
     * Record incoming inventory (purchase, production, return).
     */
    public function recordReceipt(
        int $productId,
        int $warehouseId,
        int $quantity,
        int $unitCost,
        string $type = InventoryMovement::TYPE_IN,
        ?string $notes = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $userId = null
    ): InventoryMovement {
        $this->validator->validateReceipt($productId, $warehouseId, $quantity);

        $stock = $this->getOrCreateStock($productId, $warehouseId);
        $previousQty = $stock->quantity;

        // Update stock using existing method
        $stock->addStock($quantity, $unitCost);

        // Record movement
        $movement = InventoryMovement::create([
            'movement_number' => InventoryMovement::generateMovementNumber($type),
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'type' => $type,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $quantity * $unitCost,
            'quantity_before' => $previousQty,
            'quantity_after' => $stock->quantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'movement_date' => now(),
            'notes' => $notes,
            'created_by' => $userId ?? auth()->id(),
        ]);

        $this->eventDispatcher->dispatch(
            new InventoryReceived(
                productId: $productId,
                warehouseId: $warehouseId,
                quantity: $quantity,
                movementId: $movement->id,
                userId: $userId
            )
        );

        return $movement;
    }

    /**
     * Record outgoing inventory (sale, consumption, return to supplier).
     */
    public function recordIssue(
        int $productId,
        int $warehouseId,
        int $quantity,
        string $type = InventoryMovement::TYPE_OUT,
        ?string $notes = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $userId = null
    ): InventoryMovement {
        $this->validator->validateIssue($productId, $warehouseId, $quantity);

        $stock = $this->getStock($productId, $warehouseId);
        $previousQty = $stock->quantity;
        $unitCost = $stock->average_cost;

        // Update stock using existing method
        $stock->removeStock($quantity);

        // Record movement
        $movement = InventoryMovement::create([
            'movement_number' => InventoryMovement::generateMovementNumber($type),
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'type' => $type,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $quantity * $unitCost,
            'quantity_before' => $previousQty,
            'quantity_after' => $stock->quantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'movement_date' => now(),
            'notes' => $notes,
            'created_by' => $userId ?? auth()->id(),
        ]);

        $this->eventDispatcher->dispatch(
            new InventoryIssued(
                productId: $productId,
                warehouseId: $warehouseId,
                quantity: $quantity,
                movementId: $movement->id,
                userId: $userId
            )
        );

        return $movement;
    }

    /**
     * Record inventory adjustment (positive or negative).
     */
    public function recordAdjustment(
        int $productId,
        int $warehouseId,
        int $adjustmentQuantity,
        ?string $notes = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $userId = null
    ): InventoryMovement {
        $this->validator->validateAdjustment($productId, $warehouseId, $adjustmentQuantity);

        $stock = $this->getOrCreateStock($productId, $warehouseId);
        $previousQty = $stock->quantity;

        // Update stock
        if ($adjustmentQuantity > 0) {
            $stock->addStock($adjustmentQuantity, $stock->average_cost);
        } else {
            $stock->removeStock(abs($adjustmentQuantity));
        }

        // Record movement
        $movement = InventoryMovement::create([
            'movement_number' => InventoryMovement::generateMovementNumber(InventoryMovement::TYPE_ADJUSTMENT),
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'type' => InventoryMovement::TYPE_ADJUSTMENT,
            'quantity' => abs($adjustmentQuantity),
            'unit_cost' => $stock->average_cost,
            'total_cost' => abs($adjustmentQuantity) * $stock->average_cost,
            'quantity_before' => $previousQty,
            'quantity_after' => $stock->quantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'movement_date' => now(),
            'notes' => $notes ?? ($adjustmentQuantity > 0 ? 'Adjustment (+)' : 'Adjustment (-)'),
            'created_by' => $userId ?? auth()->id(),
        ]);

        $this->eventDispatcher->dispatch(
            new InventoryAdjusted(
                productId: $productId,
                warehouseId: $warehouseId,
                adjustmentQuantity: $adjustmentQuantity,
                previousQuantity: $previousQty,
                newQuantity: $stock->quantity,
                movementId: $movement->id,
                userId: $userId
            )
        );

        return $movement;
    }

    /**
     * Record transfer between warehouses.
     *
     * @return array{out: InventoryMovement, in: InventoryMovement}
     */
    public function recordTransfer(
        int $productId,
        int $fromWarehouseId,
        int $toWarehouseId,
        int $quantity,
        ?string $notes = null,
        ?int $userId = null
    ): array {
        $this->validator->validateTransfer($productId, $fromWarehouseId, $toWarehouseId, $quantity);

        // Get source stock info for cost
        $sourceStock = $this->getStock($productId, $fromWarehouseId);
        $unitCost = $sourceStock->average_cost;

        // Issue from source warehouse
        $outMovement = $this->recordIssue(
            productId: $productId,
            warehouseId: $fromWarehouseId,
            quantity: $quantity,
            type: InventoryMovement::TYPE_TRANSFER_OUT,
            notes: $notes ?? "Transfer ke gudang #{$toWarehouseId}",
            referenceType: 'warehouse',
            referenceId: $toWarehouseId,
            userId: $userId
        );

        // Update outMovement with transfer warehouse
        $outMovement->update(['transfer_warehouse_id' => $toWarehouseId]);

        // Receive at destination warehouse
        $inMovement = $this->recordReceipt(
            productId: $productId,
            warehouseId: $toWarehouseId,
            quantity: $quantity,
            unitCost: $unitCost,
            type: InventoryMovement::TYPE_TRANSFER_IN,
            notes: $notes ?? "Transfer dari gudang #{$fromWarehouseId}",
            referenceType: 'warehouse',
            referenceId: $fromWarehouseId,
            userId: $userId
        );

        // Update inMovement with transfer warehouse
        $inMovement->update(['transfer_warehouse_id' => $fromWarehouseId]);

        $this->eventDispatcher->dispatch(
            new InventoryTransferred(
                productId: $productId,
                fromWarehouseId: $fromWarehouseId,
                toWarehouseId: $toWarehouseId,
                quantity: $quantity,
                outMovementId: $outMovement->id,
                inMovementId: $inMovement->id,
                userId: $userId
            )
        );

        return ['out' => $outMovement, 'in' => $inMovement];
    }

    private function getStock(int $productId, int $warehouseId): ProductStock
    {
        return ProductStock::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->firstOrFail();
    }

    private function getOrCreateStock(int $productId, int $warehouseId): ProductStock
    {
        return ProductStock::firstOrCreate(
            ['product_id' => $productId, 'warehouse_id' => $warehouseId],
            ['quantity' => 0, 'reserved_quantity' => 0, 'average_cost' => 0, 'total_value' => 0]
        );
    }
}
