<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Enums\DocumentStatus;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Manufacturing\MaterialConsumption;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderItem;
use App\Services\Base\AbstractApplicationService;
use InvalidArgumentException;

/**
 * Service for work order material management.
 *
 * Handles material reservation, consumption, and status tracking.
 */
class WorkOrderMaterialService extends AbstractApplicationService
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Reserve materials from inventory.
     */
    public function reserveMaterials(WorkOrder $wo): void
    {
        foreach ($wo->materialItems as $item) {
            if (! $item->product_id) {
                continue;
            }

            $warehouseId = $wo->warehouse_id;

            // Check available stock
            $stock = ProductStock::where('product_id', $item->product_id)
                ->where('warehouse_id', $warehouseId)
                ->first();

            $availableQty = $stock
                ? (float) $stock->quantity - (float) $stock->reserved_quantity
                : 0;

            if ($availableQty < (float) $item->quantity_required) {
                $product = $item->product;
                throw new InvalidArgumentException(
                    "Stok tidak mencukupi untuk {$product->name}. ".
                    "Dibutuhkan: {$item->quantity_required}, Tersedia: {$availableQty}"
                );
            }

            // Reserve the stock
            if ($stock) {
                $stock->reserved_quantity = (float) $stock->reserved_quantity + (float) $item->quantity_required;
                $stock->save();
            }

            $item->quantity_reserved = $item->quantity_required;
            $item->save();
        }
    }

    /**
     * Release reserved materials.
     */
    public function releaseMaterials(WorkOrder $wo): void
    {
        foreach ($wo->materialItems as $item) {
            if (! $item->product_id || $item->quantity_reserved <= 0) {
                continue;
            }

            $stock = ProductStock::where('product_id', $item->product_id)
                ->where('warehouse_id', $wo->warehouse_id)
                ->first();

            if ($stock) {
                $stock->reserved_quantity = max(0, (float) $stock->reserved_quantity - (float) $item->quantity_reserved);
                $stock->save();
            }

            $item->quantity_reserved = 0;
            $item->save();
        }
    }

    /**
     * Consume materials (deduct from inventory).
     */
    public function consumeMaterials(WorkOrder $wo): void
    {
        foreach ($wo->materialItems as $item) {
            if (! $item->product_id) {
                continue;
            }

            $quantityToConsume = (float) $item->quantity_required;

            $stock = ProductStock::where('product_id', $item->product_id)
                ->where('warehouse_id', $wo->warehouse_id)
                ->first();

            if ($stock) {
                // Release from reserved and deduct from quantity
                $stock->reserved_quantity = max(0, (float) $stock->reserved_quantity - (float) $item->quantity_reserved);
                $stock->quantity = max(0, (float) $stock->quantity - $quantityToConsume);
                $stock->save();

                // Create inventory movement
                InventoryMovement::create([
                    'movement_number' => InventoryMovement::generateMovementNumber(InventoryMovement::TYPE_OUT),
                    'product_id' => $item->product_id,
                    'warehouse_id' => $wo->warehouse_id,
                    'type' => InventoryMovement::TYPE_OUT,
                    'quantity' => (int) $quantityToConsume,
                    'quantity_before' => (int) ($stock->quantity + $quantityToConsume),
                    'quantity_after' => (int) $stock->quantity,
                    'unit_cost' => $item->unit_cost,
                    'total_cost' => (int) round($quantityToConsume * $item->unit_cost),
                    'reference_type' => WorkOrder::class,
                    'reference_id' => $wo->id,
                    'notes' => "Konsumsi untuk WO: {$wo->wo_number}",
                    'movement_date' => now(),
                ]);
            }

            // Update item as consumed
            $item->quantity_consumed = $quantityToConsume;
            $item->actual_unit_cost = $item->unit_cost;
            $item->total_actual_cost = (int) round($quantityToConsume * $item->unit_cost);
            $item->save();
        }
    }

    /**
     * Record material consumption.
     *
     * @param  array<array<string, mixed>>  $consumptions
     */
    public function recordConsumption(WorkOrder $wo, array $consumptions): void
    {
        if ($wo->status !== DocumentStatus::InProgress) {
            throw new InvalidArgumentException('Konsumsi material hanya dapat dicatat saat work order dalam proses.');
        }

        $this->executeInTransaction('record_consumption', function () use ($wo, $consumptions) {
            foreach ($consumptions as $consumptionData) {
                $woItem = isset($consumptionData['work_order_item_id'])
                    ? WorkOrderItem::find($consumptionData['work_order_item_id'])
                    : null;

                $product = Product::findOrFail($consumptionData['product_id']);

                $consumption = new MaterialConsumption([
                    'work_order_id' => $wo->id,
                    'work_order_item_id' => $woItem?->id,
                    'product_id' => $product->id,
                    'quantity_consumed' => $consumptionData['quantity_consumed'] ?? 0,
                    'quantity_scrapped' => $consumptionData['quantity_scrapped'] ?? 0,
                    'scrap_reason' => $consumptionData['scrap_reason'] ?? null,
                    'unit' => $consumptionData['unit'] ?? $product->unit,
                    'unit_cost' => $consumptionData['unit_cost'] ?? $product->purchase_price ?? 0,
                    'consumed_date' => $consumptionData['consumed_date'] ?? now(),
                    'batch_number' => $consumptionData['batch_number'] ?? null,
                    'consumed_by' => $this->getUserId(),
                    'notes' => $consumptionData['notes'] ?? null,
                ]);
                $consumption->calculateTotalCost();
                $consumption->save();

                // Update WO item consumed quantity
                if ($woItem) {
                    $woItem->quantity_consumed = (float) $woItem->quantity_consumed
                        + (float) $consumptionData['quantity_consumed']
                        + (float) ($consumptionData['quantity_scrapped'] ?? 0);
                    $woItem->calculateActualCost();
                    $woItem->save();
                }
            }

            // Recalculate WO actual costs
            $wo->calculateActualCosts();
            $wo->save();
        }, ['work_order_id' => $wo->id, 'items_count' => count($consumptions)]);
    }

    /**
     * Get material status.
     *
     * @return array{work_order_id: int, wo_number: string, materials: array, summary: array}
     */
    public function getMaterialStatus(WorkOrder $wo): array
    {
        $wo->load(['items.product']);

        $materials = [];

        foreach ($wo->materialItems as $item) {
            $materials[] = [
                'item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name ?? $item->description,
                'quantity_required' => $item->quantity_required,
                'quantity_reserved' => $item->quantity_reserved,
                'quantity_consumed' => $item->quantity_consumed,
                'quantity_remaining' => $item->getRemainingQuantity(),
                'unit' => $item->unit,
                'status' => $this->getItemStatus($item),
            ];
        }

        return [
            'work_order_id' => $wo->id,
            'wo_number' => $wo->wo_number,
            'materials' => $materials,
            'summary' => [
                'total_items' => count($materials),
                'fully_consumed' => collect($materials)->where('status', 'consumed')->count(),
                'partially_consumed' => collect($materials)->where('status', 'partial')->count(),
                'pending' => collect($materials)->where('status', 'pending')->count(),
            ],
        ];
    }

    /**
     * Get item consumption status.
     */
    private function getItemStatus(WorkOrderItem $item): string
    {
        if ($item->quantity_consumed >= $item->quantity_required) {
            return 'consumed';
        }
        if ($item->quantity_consumed > 0) {
            return 'partial';
        }

        return 'pending';
    }
}
