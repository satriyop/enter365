<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Inventory\InventoryServiceInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Manufacturing\WorkOrders\WorkOrderDomainFactory;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\BusinessRuleException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\MaterialConsumption;
use App\Models\Manufacturing\MaterialRequisitionItem;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderItem;
use App\Services\Accounting\AccountingPolicyManager;
use App\Services\Base\BaseService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Service for work order material management.
 *
 * Handles material reservation, consumption, and status tracking.
 */
class WorkOrderMaterialService extends BaseService
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        private AccountingPolicyManager $policyManager,
        private WorkOrderDomainFactory $domainFactory,
        private InventoryServiceInterface $inventoryService,
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Reserve materials from inventory.
     */
    public function reserveMaterials(WorkOrder $wo): void
    {
        $this->executeInTransaction('reserve_materials', function () use ($wo) {
            $warehouse = $this->resolveWarehouse($wo);

            foreach ($wo->materialItems as $item) {
                if (! $item->product_id) {
                    continue;
                }

                $product = $item->product ?? Product::query()->find($item->product_id);
                if ($product === null || $warehouse === null) {
                    throw BusinessRuleException::operationNotAllowed(
                        'reservasi material',
                        'Produk atau gudang tidak ditemukan',
                    );
                }

                $qty = (int) ceil((float) $item->quantity_required);

                try {
                    $this->inventoryService->reserve($product, $warehouse, $qty);
                } catch (InsufficientStockException $e) {
                    throw BusinessRuleException::insufficientStock(
                        $product->name,
                        (float) $qty,
                        (float) ($e->getContext()['available'] ?? 0),
                    );
                }

                $item->quantity_reserved = $item->quantity_required;
                $item->save();
            }
        }, ['work_order_id' => $wo->id]);
    }

    /**
     * Release reserved materials.
     */
    public function releaseMaterials(WorkOrder $wo): void
    {
        $this->executeInTransaction('release_materials', function () use ($wo) {
            $warehouse = $this->resolveWarehouse($wo);

            foreach ($wo->materialItems as $item) {
                if (! $item->product_id || $item->quantity_reserved <= 0) {
                    continue;
                }

                $product = $item->product ?? Product::query()->find($item->product_id);
                if ($product === null || $warehouse === null) {
                    throw BusinessRuleException::operationNotAllowed(
                        'pelepasan reservasi material',
                        'Produk atau gudang tidak ditemukan',
                    );
                }

                $this->inventoryService->release(
                    $product,
                    $warehouse,
                    (int) ceil((float) $item->quantity_reserved),
                );

                $item->quantity_reserved = 0;
                $item->save();
            }
        }, ['work_order_id' => $wo->id]);
    }

    /**
     * Consume materials (deduct from inventory).
     *
     * Creates MaterialConsumption rows for remaining untracked qty and invokes
     * the configured ManufacturingCostStrategy for each new consumption.
     */
    public function consumeMaterials(WorkOrder $wo): void
    {
        $this->executeInTransaction('consume_materials', function () use ($wo) {
            $wo->loadMissing(['materialItems.product']);
            $warehouse = $this->resolveWarehouse($wo);
            $strategy = $this->policyManager->manufacturing();

            foreach ($wo->materialItems as $item) {
                if (! $item->product_id) {
                    continue;
                }

                $required = (int) ceil((float) $item->quantity_required);
                // Materials already issued via MR must not be deducted again from stock
                $alreadyIssued = $this->quantityIssuedViaMaterialRequisitions($wo, (int) $item->product_id);
                $quantityToConsume = max(0, $required - (int) floor($alreadyIssued));
                $remainingForCost = max(0.0, $item->getRemainingQuantity());

                $product = $item->product ?? Product::query()->find($item->product_id);
                if ($product === null) {
                    continue;
                }

                $unitCost = (int) ($item->unit_cost ?: ($product->purchase_price ?? 0));

                $reserved = (int) ceil((float) $item->quantity_reserved);

                if ($warehouse !== null && $reserved > 0) {
                    $this->inventoryService->release($product, $warehouse, $reserved);
                }

                if ($warehouse !== null && $quantityToConsume > 0) {
                    try {
                        $this->inventoryService->stockOut(
                            $product,
                            $warehouse,
                            $quantityToConsume,
                            "Konsumsi untuk WO: {$wo->wo_number}",
                            WorkOrder::class,
                            $wo->id,
                        );
                    } catch (InsufficientStockException $e) {
                        throw BusinessRuleException::insufficientStock(
                            $product->name,
                            (float) $quantityToConsume,
                            (float) ($e->getContext()['available'] ?? 0),
                        );
                    }
                }

                // Track remaining cost basis only (avoid double-counting prior recordConsumption)
                if ($remainingForCost > 0) {
                    $consumption = new MaterialConsumption([
                        'work_order_id' => $wo->id,
                        'work_order_item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'quantity_consumed' => $remainingForCost,
                        'quantity_scrapped' => 0,
                        'unit' => $item->unit ?? $product->unit ?? 'pcs',
                        'unit_cost' => $unitCost,
                        'consumed_date' => now(),
                        'consumed_by' => $this->getUserId(),
                        'notes' => "Konsumsi otomatis saat penyelesaian WO: {$wo->wo_number}",
                    ]);
                    $consumption->calculateTotalCost();
                    $consumption->save();

                    $strategy->onMaterialConsumption($consumption);
                }

                // Update item as fully consumed (required qty, including MR-issued portion)
                $item->quantity_consumed = $required;
                $item->quantity_reserved = 0;
                $item->actual_unit_cost = $unitCost;
                $item->total_actual_cost = (int) $item->consumptions()->sum('total_cost')
                    ?: (int) round($required * $unitCost);
                $item->save();
            }
        }, ['work_order_id' => $wo->id]);
    }

    private function resolveWarehouse(WorkOrder $wo): ?Warehouse
    {
        return $wo->warehouse ?? Warehouse::query()->find($wo->warehouse_id);
    }

    /**
     * Total quantity already issued from material requisitions for this WO product.
     */
    private function quantityIssuedViaMaterialRequisitions(WorkOrder $wo, int $productId): float
    {
        return (float) MaterialRequisitionItem::query()
            ->where('product_id', $productId)
            ->whereHas('materialRequisition', function ($query) use ($wo) {
                $query->where('work_order_id', $wo->id)
                    ->whereNull('deleted_at');
            })
            ->sum('quantity_issued');
    }

    /**
     * Record material consumption.
     *
     * @param  array<array<string, mixed>>  $consumptions
     */
    public function recordConsumption(WorkOrder $wo, array $consumptions): void
    {
        if ($wo->status !== DocumentStatus::InProgress) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Work Order',
                'catat konsumsi material',
                $wo->status->value,
                'dalam proses'
            );
        }

        $this->executeInTransaction('record_consumption', function () use ($wo, $consumptions) {
            $strategy = $this->policyManager->manufacturing();

            $workOrderItems = WorkOrderItem::query()
                ->whereIn(
                    'id',
                    collect($consumptions)->pluck('work_order_item_id')->filter()->unique()->all()
                )
                ->get()
                ->keyBy('id');
            $products = Product::query()
                ->whereIn('id', collect($consumptions)->pluck('product_id')->unique()->all())
                ->get()
                ->keyBy('id');

            foreach ($consumptions as $consumptionData) {
                $woItem = isset($consumptionData['work_order_item_id'])
                    ? $workOrderItems->get($consumptionData['work_order_item_id'])
                    : null;

                $product = $products->get($consumptionData['product_id']);

                if (! $product) {
                    throw (new ModelNotFoundException)->setModel(Product::class, [$consumptionData['product_id']]);
                }

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

                $strategy->onMaterialConsumption($consumption);

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
            $this->domainFactory->applyActualCosts($wo);
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
                'product_name' => $item->product->name ?? $item->description,
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
