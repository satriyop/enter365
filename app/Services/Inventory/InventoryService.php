<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Inventory\InventoryServiceInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Inventory\Movements\Events\InventoryAdjusted;
use App\Domain\Inventory\Movements\Events\InventoryIssued;
use App\Domain\Inventory\Movements\Events\InventoryReceived;
use App\Domain\Inventory\Movements\Events\InventoryTransferred;
use App\Exceptions\Domain\BusinessRuleException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use App\Services\Accounting\AccountingPolicyManager;
use App\Services\Base\BaseService;
use Illuminate\Support\Collection;

class InventoryService extends BaseService implements InventoryServiceInterface
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        private AccountingPolicyManager $policyManager
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Record stock in (purchase/receiving/production receipt).
     */
    public function stockIn(
        Product $product,
        Warehouse $warehouse,
        int $quantity,
        int $unitCost,
        ?string $notes = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        string $type = InventoryMovement::TYPE_IN,
        ?int $totalCost = null,
    ): InventoryMovement {
        return $this->executeInTransaction('stock_in', function () use ($product, $warehouse, $quantity, $unitCost, $notes, $referenceType, $referenceId, $type, $totalCost) {
            $stock = ProductStock::lockForStock($product, $warehouse);
            $quantityBefore = $stock->quantity;
            $exactTotal = $totalCost ?? ($quantity * $unitCost);
            $displayUnit = $quantity > 0 ? (int) round($exactTotal / $quantity) : 0;

            $this->policyManager->costing()->recordStockIn($stock, $quantity, $displayUnit, $referenceType, $referenceId, $exactTotal);

            // Create movement record
            $movement = InventoryMovement::create([
                'movement_number' => InventoryMovement::generateMovementNumber($type),
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'type' => $type,
                'quantity' => $quantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $stock->quantity,
                'unit_cost' => $displayUnit,
                'total_cost' => $exactTotal,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'movement_date' => now(),
                'notes' => $notes,
                'created_by' => $this->getUserId(),
            ]);

            // Sync product's current_stock
            $product->syncCurrentStock();

            $this->dispatch(new InventoryReceived(
                productId: $product->id,
                warehouseId: $warehouse->id,
                quantity: $quantity,
                movementId: $movement->id,
                userId: $this->getUserId(),
            ));

            return $movement;
        }, ['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => $quantity]);
    }

    public function reserve(Product $product, Warehouse $warehouse, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $this->executeInTransaction('reserve', function () use ($product, $warehouse, $quantity) {
            $stock = ProductStock::lockForStock($product, $warehouse);
            $available = $stock->getAvailableQuantity();

            if ($available < $quantity) {
                throw InsufficientStockException::forProduct(
                    $product,
                    $quantity,
                    $available,
                    $warehouse
                );
            }

            $stock->reserved_quantity = (int) $stock->reserved_quantity + $quantity;
            $stock->save();
        }, ['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => $quantity]);
    }

    public function release(Product $product, Warehouse $warehouse, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $this->executeInTransaction('release', function () use ($product, $warehouse, $quantity) {
            $stock = ProductStock::lockForStock($product, $warehouse);
            $stock->reserved_quantity = max(0, (int) $stock->reserved_quantity - $quantity);
            $stock->save();
        }, ['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => $quantity]);
    }

    public function issueAgainstReservation(
        Product $product,
        Warehouse $warehouse,
        int $quantity,
        int $reservedToConsume,
        ?string $notes = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): InventoryMovement {
        return $this->executeInTransaction('issue_against_reservation', function () use ($product, $warehouse, $quantity, $reservedToConsume, $notes, $referenceType, $referenceId) {
            $stock = ProductStock::lockForStock($product, $warehouse);

            $consumeReserved = min(
                max(0, $reservedToConsume),
                max(0, (int) $stock->reserved_quantity),
                max(0, $quantity),
            );
            $stock->reserved_quantity = max(0, (int) $stock->reserved_quantity - $consumeReserved);
            $stock->save();

            return $this->stockOut($product, $warehouse, $quantity, $notes, $referenceType, $referenceId);
        }, ['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => $quantity]);
    }

    /**
     * Record stock out (sale/delivery). Free stock only.
     */
    public function stockOut(
        Product $product,
        Warehouse $warehouse,
        int $quantity,
        ?string $notes = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): InventoryMovement {
        return $this->executeInTransaction('stock_out', function () use ($product, $warehouse, $quantity, $notes, $referenceType, $referenceId) {
            $stock = ProductStock::lockForStock($product, $warehouse);
            $available = $stock->getAvailableQuantity();

            if ($available < $quantity) {
                throw InsufficientStockException::forProduct(
                    $product,
                    $quantity,
                    $available,
                    $warehouse
                );
            }

            $quantityBefore = $stock->quantity;

            // Exact layer total — do not reconstruct as quantity × rounded unit cost
            $totalCost = $this->policyManager->costing()->recordStockOut($stock, $quantity);
            $unitCost = $quantity > 0 ? (int) round($totalCost / $quantity) : 0;

            // Create movement record
            $movement = InventoryMovement::create([
                'movement_number' => InventoryMovement::generateMovementNumber(InventoryMovement::TYPE_OUT),
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'type' => InventoryMovement::TYPE_OUT,
                'quantity' => -$quantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $stock->quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'movement_date' => now(),
                'notes' => $notes,
                'created_by' => $this->getUserId(),
            ]);

            // Sync product's current_stock
            $product->syncCurrentStock();

            $this->dispatch(new InventoryIssued(
                productId: $product->id,
                warehouseId: $warehouse->id,
                quantity: $quantity,
                movementId: $movement->id,
                userId: $this->getUserId(),
            ));

            return $movement;
        }, ['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => $quantity]);
    }

    /**
     * Record stock adjustment to an absolute on-hand quantity.
     */
    public function adjust(
        Product $product,
        Warehouse $warehouse,
        int $newQuantity,
        ?int $newUnitCost = null,
        ?string $notes = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): InventoryMovement {
        return $this->executeInTransaction('adjust', function () use ($product, $warehouse, $newQuantity, $newUnitCost, $notes, $referenceType, $referenceId) {
            $stock = ProductStock::lockForStock($product, $warehouse);

            return $this->applyLockedAdjustment(
                $product,
                $warehouse,
                $stock,
                $newQuantity - $stock->quantity,
                $newUnitCost,
                $notes,
                $referenceType,
                $referenceId,
            );
        }, ['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'new_quantity' => $newQuantity]);
    }

    public function adjustByDelta(
        Product $product,
        Warehouse $warehouse,
        int $delta,
        ?int $unitCost = null,
        ?string $notes = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): InventoryMovement {
        return $this->executeInTransaction('adjust_by_delta', function () use ($product, $warehouse, $delta, $unitCost, $notes, $referenceType, $referenceId) {
            $stock = ProductStock::lockForStock($product, $warehouse);

            return $this->applyLockedAdjustment(
                $product,
                $warehouse,
                $stock,
                $delta,
                $unitCost,
                $notes,
                $referenceType,
                $referenceId,
            );
        }, ['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'delta' => $delta]);
    }

    private function applyLockedAdjustment(
        Product $product,
        Warehouse $warehouse,
        ProductStock $stock,
        int $delta,
        ?int $unitCost,
        ?string $notes,
        ?string $referenceType,
        ?int $referenceId,
    ): InventoryMovement {
        $quantityBefore = $stock->quantity;
        $newQuantity = $quantityBefore + $delta;

        if ($newQuantity < 0) {
            throw BusinessRuleException::operationNotAllowed(
                'penyesuaian stok',
                'Stok tidak bisa negatif.'
            );
        }

        if ($newQuantity < (int) $stock->reserved_quantity) {
            throw BusinessRuleException::operationNotAllowed(
                'penyesuaian stok',
                "Penyesuaian akan membawa stok di bawah kuantitas yang sudah direservasi ({$stock->reserved_quantity})."
            );
        }

        $costing = $this->policyManager->costing();
        $appliedUnitCost = $unitCost ?? $costing->getCurrentUnitCost($stock);
        $valueDelta = $delta === 0 ? 0 : $costing->recordAdjustment($stock, $delta, $appliedUnitCost);
        $stock->refresh();

        $absValue = abs($valueDelta);
        $movementUnitCost = $delta !== 0
            ? (int) round($absValue / abs($delta))
            : $appliedUnitCost;

        $movement = InventoryMovement::create([
            'movement_number' => InventoryMovement::generateMovementNumber(InventoryMovement::TYPE_ADJUSTMENT),
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'type' => InventoryMovement::TYPE_ADJUSTMENT,
            'quantity' => $delta,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $stock->quantity,
            'unit_cost' => $movementUnitCost,
            'total_cost' => $absValue,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'movement_date' => now(),
            'notes' => $notes ?? 'Penyesuaian stok',
            'created_by' => $this->getUserId(),
        ]);

        $product->syncCurrentStock();

        $this->dispatch(new InventoryAdjusted(
            productId: $product->id,
            warehouseId: $warehouse->id,
            adjustmentQuantity: $delta,
            previousQuantity: $quantityBefore,
            newQuantity: $stock->quantity,
            movementId: $movement->id,
            userId: $this->getUserId(),
        ));

        return $movement;
    }

    /**
     * Transfer stock between warehouses.
     *
     * @return array{out: InventoryMovement, in: InventoryMovement}
     */
    public function transfer(
        Product $product,
        Warehouse $fromWarehouse,
        Warehouse $toWarehouse,
        int $quantity,
        ?string $notes = null
    ): array {
        return $this->executeInTransaction('transfer', function () use ($product, $fromWarehouse, $toWarehouse, $quantity, $notes) {
            if ($fromWarehouse->id === $toWarehouse->id) {
                throw BusinessRuleException::operationNotAllowed(
                    'transfer stok',
                    'Gudang asal dan tujuan tidak boleh sama.'
                );
            }

            $stocks = $this->lockStocksInWarehouseOrder($product, $fromWarehouse, $toWarehouse);
            $fromStock = $stocks[$fromWarehouse->id];
            $toStock = $stocks[$toWarehouse->id];
            $available = $fromStock->getAvailableQuantity();

            if ($available < $quantity) {
                throw InsufficientStockException::forTransfer(
                    $product,
                    $fromWarehouse,
                    $quantity,
                    $available
                );
            }

            $costingStrategy = $this->policyManager->costing();
            $fromQuantityBefore = $fromStock->quantity;
            $toQuantityBefore = $toStock->quantity;

            $totalCost = $costingStrategy->recordStockOut($fromStock, $quantity);
            $unitCost = $quantity > 0 ? (int) round($totalCost / $quantity) : 0;
            $costingStrategy->recordStockIn($toStock, $quantity, $unitCost, null, null, $totalCost);

            $transferNumber = InventoryMovement::generateMovementNumber(InventoryMovement::TYPE_TRANSFER_OUT);

            // Create outgoing movement
            $outMovement = InventoryMovement::create([
                'movement_number' => $transferNumber,
                'product_id' => $product->id,
                'warehouse_id' => $fromWarehouse->id,
                'type' => InventoryMovement::TYPE_TRANSFER_OUT,
                'quantity' => -$quantity,
                'quantity_before' => $fromQuantityBefore,
                'quantity_after' => $fromStock->quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'transfer_warehouse_id' => $toWarehouse->id,
                'movement_date' => now(),
                'notes' => $notes ?? "Transfer ke {$toWarehouse->name}",
                'created_by' => $this->getUserId(),
            ]);

            // Create incoming movement
            $inMovement = InventoryMovement::create([
                'movement_number' => InventoryMovement::generateMovementNumber(InventoryMovement::TYPE_TRANSFER_IN),
                'product_id' => $product->id,
                'warehouse_id' => $toWarehouse->id,
                'type' => InventoryMovement::TYPE_TRANSFER_IN,
                'quantity' => $quantity,
                'quantity_before' => $toQuantityBefore,
                'quantity_after' => $toStock->quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'transfer_warehouse_id' => $fromWarehouse->id,
                'movement_date' => now(),
                'notes' => $notes ?? "Transfer dari {$fromWarehouse->name}",
                'created_by' => $this->getUserId(),
            ]);

            // Sync product's current_stock
            $product->syncCurrentStock();

            $this->dispatch(new InventoryTransferred(
                productId: $product->id,
                fromWarehouseId: $fromWarehouse->id,
                toWarehouseId: $toWarehouse->id,
                quantity: $quantity,
                outMovementId: $outMovement->id,
                inMovementId: $inMovement->id,
                userId: $this->getUserId(),
            ));

            return ['out' => $outMovement, 'in' => $inMovement];
        }, ['product_id' => $product->id, 'from_warehouse_id' => $fromWarehouse->id, 'to_warehouse_id' => $toWarehouse->id], 3);
    }

    /**
     * Lock stock rows in ascending warehouse_id order to avoid AB-BA deadlocks.
     *
     * @return array<int, ProductStock>
     */
    private function lockStocksInWarehouseOrder(Product $product, Warehouse ...$warehouses): array
    {
        $stocks = [];

        collect($warehouses)
            ->unique('id')
            ->sortBy('id')
            ->each(function (Warehouse $warehouse) use ($product, &$stocks): void {
                $stocks[$warehouse->id] = ProductStock::lockForStock($product, $warehouse);
            });

        return $stocks;
    }

    /**
     * Process inventory for a posted invoice (stock out).
     */
    public function processInvoice(Invoice $invoice, Warehouse $warehouse): void
    {
        foreach ($invoice->items as $item) {
            if (! $item->product || ! $item->product->track_inventory) {
                continue;
            }

            $this->stockOut(
                $item->product,
                $warehouse,
                (int) $item->quantity,
                "Penjualan: {$invoice->invoice_number}",
                Invoice::class,
                $invoice->id
            );
        }
    }

    /**
     * Process inventory for a posted bill (stock in).
     */
    public function processBill(Bill $bill, Warehouse $warehouse): void
    {
        foreach ($bill->items as $item) {
            if (! $item->product || ! $item->product->track_inventory) {
                continue;
            }

            $this->stockIn(
                $item->product,
                $warehouse,
                (int) $item->quantity,
                $item->inventoryUnitCost(),
                "Pembelian: {$bill->bill_number}",
                Bill::class,
                $bill->id
            );
        }
    }

    /**
     * Get stock card (kartu stok) for a product.
     *
     * @return Collection<int, InventoryMovement>
     */
    public function getStockCard(
        Product $product,
        ?Warehouse $warehouse = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): Collection {
        $query = InventoryMovement::query()
            ->where('product_id', $product->id)
            ->with(['warehouse', 'createdByUser']);

        if ($warehouse) {
            $query->where('warehouse_id', $warehouse->id);
        }

        if ($startDate) {
            $query->where('movement_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('movement_date', '<=', $endDate);
        }

        return $query->orderBy('movement_date')->orderBy('id')->get();
    }

    /**
     * Get stock valuation report.
     */
    public function getStockValuation(?Warehouse $warehouse = null): Collection
    {
        $query = ProductStock::query()
            ->with(['product', 'warehouse'])
            ->where('quantity', '>', 0);

        if ($warehouse) {
            $query->where('warehouse_id', $warehouse->id);
        }

        return $query->get()->map(fn ($stock) => (object) [
            'product_id' => $stock->product_id,
            'product_sku' => $stock->product->sku,
            'product_name' => $stock->product->name,
            'warehouse_id' => $stock->warehouse_id,
            'warehouse_name' => $stock->warehouse->name,
            'quantity' => $stock->quantity,
            'average_cost' => $stock->average_cost,
            'total_value' => $stock->total_value,
        ]);
    }

    /**
     * Get inventory summary.
     */
    public function getInventorySummary(?Warehouse $warehouse = null): array
    {
        $query = ProductStock::query();

        if ($warehouse) {
            $query->where('warehouse_id', $warehouse->id);
        }

        $totalValue = $query->sum('total_value');
        $totalItems = $query->count();
        $totalQuantity = $query->sum('quantity');

        $lowStockCount = Product::query()
            ->where('track_inventory', true)
            ->where('is_active', true)
            ->whereColumn('current_stock', '<=', 'min_stock')
            ->count();

        $outOfStockCount = Product::query()
            ->where('track_inventory', true)
            ->where('is_active', true)
            ->where('current_stock', '<=', 0)
            ->count();

        return [
            'total_value' => $totalValue,
            'total_items' => $totalItems,
            'total_quantity' => $totalQuantity,
            'low_stock_count' => $lowStockCount,
            'out_of_stock_count' => $outOfStockCount,
        ];
    }

    /**
     * Get movement summary for a period.
     */
    public function getMovementSummary(string $startDate, string $endDate, ?Warehouse $warehouse = null): array
    {
        $query = InventoryMovement::query()
            ->whereBetween('movement_date', [$startDate, $endDate.' 23:59:59']);

        if ($warehouse) {
            $query->where('warehouse_id', $warehouse->id);
        }

        $movements = $query->get();

        return [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'stock_in' => [
                'count' => $movements->where('type', InventoryMovement::TYPE_IN)->count(),
                'quantity' => $movements->where('type', InventoryMovement::TYPE_IN)->sum('quantity'),
                'value' => $movements->where('type', InventoryMovement::TYPE_IN)->sum('total_cost'),
            ],
            'stock_out' => [
                'count' => $movements->where('type', InventoryMovement::TYPE_OUT)->count(),
                'quantity' => abs($movements->where('type', InventoryMovement::TYPE_OUT)->sum('quantity')),
                'value' => $movements->where('type', InventoryMovement::TYPE_OUT)->sum('total_cost'),
            ],
            'adjustments' => [
                'count' => $movements->where('type', InventoryMovement::TYPE_ADJUSTMENT)->count(),
            ],
            'transfers' => [
                'count' => $movements->whereIn('type', [
                    InventoryMovement::TYPE_TRANSFER_IN,
                    InventoryMovement::TYPE_TRANSFER_OUT,
                ])->count() / 2, // Divide by 2 because each transfer creates 2 records
            ],
        ];
    }
}
