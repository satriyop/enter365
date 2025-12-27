<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Bom;
use App\Models\Accounting\BomItem;
use App\Models\Accounting\DeliveryOrder;
use App\Models\Accounting\InventoryMovement;
use App\Models\Accounting\MaterialConsumption;
use App\Models\Accounting\Product;
use App\Models\Accounting\ProductStock;
use App\Models\Accounting\Project;
use App\Models\Accounting\WorkOrder;
use App\Models\Accounting\WorkOrderItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WorkOrderService
{
    /**
     * Create a new work order.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): WorkOrder
    {
        return DB::transaction(function () use ($data) {
            $project = isset($data['project_id']) ? Project::find($data['project_id']) : null;

            $wo = new WorkOrder($data);
            $wo->wo_number = WorkOrder::generateWoNumber($project);
            $wo->status = WorkOrder::STATUS_DRAFT;
            $wo->save();

            // Create items if provided
            if (! empty($data['items'])) {
                $this->createItems($wo, $data['items']);
            }

            return $wo->fresh(['items', 'project', 'product', 'bom']);
        });
    }

    /**
     * Create work order from project.
     *
     * @param  array<string, mixed>  $data
     */
    public function createFromProject(Project $project, array $data): WorkOrder
    {
        $data['project_id'] = $project->id;

        if (! isset($data['name'])) {
            $data['name'] = 'Work Order - '.$project->name;
        }

        return $this->create($data);
    }

    /**
     * Create work order from BOM.
     *
     * @param  array<string, mixed>  $data
     */
    public function createFromBom(Bom $bom, array $data): WorkOrder
    {
        return DB::transaction(function () use ($bom, $data) {
            $project = isset($data['project_id']) ? Project::find($data['project_id']) : null;
            $quantity = $data['quantity'] ?? 1;

            $wo = new WorkOrder([
                'bom_id' => $bom->id,
                'product_id' => $bom->product_id,
                'project_id' => $data['project_id'] ?? null,
                'type' => WorkOrder::TYPE_PRODUCTION,
                'name' => $data['name'] ?? $bom->name,
                'description' => $data['description'] ?? $bom->description,
                'quantity_ordered' => $quantity,
                'priority' => $data['priority'] ?? WorkOrder::PRIORITY_NORMAL,
                'planned_start_date' => $data['planned_start_date'] ?? null,
                'planned_end_date' => $data['planned_end_date'] ?? null,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'] ?? auth()->id(),
            ]);
            $wo->wo_number = WorkOrder::generateWoNumber($project);
            $wo->status = WorkOrder::STATUS_DRAFT;
            $wo->save();

            // Explode BOM and create items
            $this->createItemsFromBom($wo, $bom, $quantity);

            // Calculate estimated costs
            $wo->calculateEstimatedCosts();
            $wo->save();

            return $wo->fresh(['items', 'project', 'product', 'bom']);
        });
    }

    /**
     * Create sub work order.
     *
     * @param  array<string, mixed>  $data
     */
    public function createSubWorkOrder(WorkOrder $parent, array $data): WorkOrder
    {
        $data['parent_work_order_id'] = $parent->id;
        $data['project_id'] = $parent->project_id;
        $data['warehouse_id'] = $data['warehouse_id'] ?? $parent->warehouse_id;

        return $this->create($data);
    }

    /**
     * Update work order.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(WorkOrder $wo, array $data): WorkOrder
    {
        if (! $wo->canBeEdited()) {
            throw new InvalidArgumentException('Work order hanya dapat diedit dalam status draft.');
        }

        return DB::transaction(function () use ($wo, $data) {
            $wo->fill($data);
            $wo->save();

            // Update items if provided
            if (isset($data['items'])) {
                $wo->items()->delete();
                $this->createItems($wo, $data['items']);
                $wo->calculateEstimatedCosts();
                $wo->save();
            }

            return $wo->fresh(['items', 'project', 'product', 'bom']);
        });
    }

    /**
     * Delete work order.
     */
    public function delete(WorkOrder $wo): bool
    {
        if (! $wo->canBeEdited()) {
            throw new InvalidArgumentException('Hanya work order draft yang dapat dihapus.');
        }

        return DB::transaction(function () use ($wo) {
            $wo->items()->delete();
            $wo->subWorkOrders()->delete();

            return $wo->delete();
        });
    }

    /**
     * Confirm work order and reserve materials.
     */
    public function confirm(WorkOrder $wo, ?int $userId = null): WorkOrder
    {
        if (! $wo->canBeConfirmed()) {
            throw new InvalidArgumentException('Work order tidak dapat dikonfirmasi. Pastikan memiliki item dan dalam status draft.');
        }

        return DB::transaction(function () use ($wo, $userId) {
            // Check and reserve materials
            $this->reserveMaterials($wo);

            $wo->status = WorkOrder::STATUS_CONFIRMED;
            $wo->confirmed_by = $userId ?? auth()->id();
            $wo->confirmed_at = now();
            $wo->save();

            return $wo->fresh();
        });
    }

    /**
     * Start work order.
     */
    public function start(WorkOrder $wo, ?int $userId = null): WorkOrder
    {
        if (! $wo->canBeStarted()) {
            throw new InvalidArgumentException('Work order hanya dapat dimulai setelah dikonfirmasi.');
        }

        $wo->status = WorkOrder::STATUS_IN_PROGRESS;
        $wo->started_by = $userId ?? auth()->id();
        $wo->started_at = now();
        $wo->actual_start_date = now();
        $wo->save();

        return $wo->fresh();
    }

    /**
     * Complete work order.
     */
    public function complete(WorkOrder $wo, ?int $userId = null): WorkOrder
    {
        if (! $wo->canBeCompleted()) {
            throw new InvalidArgumentException('Work order hanya dapat diselesaikan saat dalam proses.');
        }

        // Check if all sub-WOs are completed
        $incompleteSubWos = $wo->subWorkOrders()
            ->whereNotIn('status', [WorkOrder::STATUS_COMPLETED, WorkOrder::STATUS_CANCELLED])
            ->count();

        if ($incompleteSubWos > 0) {
            throw new InvalidArgumentException('Semua sub-work order harus diselesaikan terlebih dahulu.');
        }

        return DB::transaction(function () use ($wo, $userId) {
            // Consume materials (deduct from inventory)
            $this->consumeMaterials($wo);

            // Calculate actual costs
            $wo->calculateActualCosts();

            $wo->status = WorkOrder::STATUS_COMPLETED;
            $wo->completed_by = $userId ?? auth()->id();
            $wo->completed_at = now();
            $wo->actual_end_date = now();

            // Set quantity completed if not already set
            if ($wo->quantity_completed <= 0) {
                $wo->quantity_completed = $wo->quantity_ordered;
            }

            $wo->save();

            // Update project costs if linked
            if ($wo->project_id) {
                $this->updateProjectCosts($wo);
            }

            // Auto-create delivery order if project has customer
            $this->createDeliveryOrderIfNeeded($wo);

            return $wo->fresh();
        });
    }

    /**
     * Cancel work order.
     */
    public function cancel(WorkOrder $wo, ?string $reason = null, ?int $userId = null): WorkOrder
    {
        if (! $wo->canBeCancelled()) {
            throw new InvalidArgumentException('Work order tidak dapat dibatalkan.');
        }

        return DB::transaction(function () use ($wo, $reason, $userId) {
            // Release reserved materials if confirmed/in_progress
            if (in_array($wo->status, [WorkOrder::STATUS_CONFIRMED, WorkOrder::STATUS_IN_PROGRESS])) {
                $this->releaseMaterials($wo);
            }

            $wo->status = WorkOrder::STATUS_CANCELLED;
            $wo->cancelled_by = $userId ?? auth()->id();
            $wo->cancelled_at = now();
            $wo->cancellation_reason = $reason;
            $wo->save();

            return $wo->fresh();
        });
    }

    /**
     * Record output quantity.
     */
    public function recordOutput(WorkOrder $wo, float $quantity, float $scrapped = 0): WorkOrder
    {
        if ($wo->status !== WorkOrder::STATUS_IN_PROGRESS) {
            throw new InvalidArgumentException('Output hanya dapat dicatat saat work order dalam proses.');
        }

        $wo->quantity_completed = (float) $wo->quantity_completed + $quantity;
        $wo->quantity_scrapped = (float) $wo->quantity_scrapped + $scrapped;
        $wo->save();

        return $wo->fresh();
    }

    /**
     * Record material consumption.
     *
     * @param  array<array<string, mixed>>  $consumptions
     */
    public function recordConsumption(WorkOrder $wo, array $consumptions): void
    {
        if ($wo->status !== WorkOrder::STATUS_IN_PROGRESS) {
            throw new InvalidArgumentException('Konsumsi material hanya dapat dicatat saat work order dalam proses.');
        }

        DB::transaction(function () use ($wo, $consumptions) {
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
                    'consumed_by' => auth()->id(),
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
        });
    }

    /**
     * Get cost summary.
     *
     * @return array<string, mixed>
     */
    public function getCostSummary(WorkOrder $wo): array
    {
        $wo->load(['items', 'consumptions']);

        return [
            'work_order_id' => $wo->id,
            'wo_number' => $wo->wo_number,
            'estimated' => [
                'material' => $wo->estimated_material_cost,
                'labor' => $wo->estimated_labor_cost,
                'overhead' => $wo->estimated_overhead_cost,
                'total' => $wo->estimated_total_cost,
            ],
            'actual' => [
                'material' => $wo->actual_material_cost,
                'labor' => $wo->actual_labor_cost,
                'overhead' => $wo->actual_overhead_cost,
                'total' => $wo->actual_total_cost,
            ],
            'variance' => [
                'material' => $wo->estimated_material_cost - $wo->actual_material_cost,
                'labor' => $wo->estimated_labor_cost - $wo->actual_labor_cost,
                'overhead' => $wo->estimated_overhead_cost - $wo->actual_overhead_cost,
                'total' => $wo->cost_variance,
            ],
            'variance_percentage' => $wo->estimated_total_cost > 0
                ? round(($wo->cost_variance / $wo->estimated_total_cost) * 100, 2)
                : 0,
        ];
    }

    /**
     * Get material status.
     *
     * @return array<string, mixed>
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
     * Get statistics.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(?string $startDate = null, ?string $endDate = null): array
    {
        $query = WorkOrder::query();

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $byStatus = [];
        foreach (WorkOrder::getStatuses() as $status => $label) {
            $byStatus[$status] = (clone $query)->where('status', $status)->count();
        }

        $byType = [];
        foreach (WorkOrder::getTypes() as $type => $label) {
            $byType[$type] = (clone $query)->where('type', $type)->count();
        }

        return [
            'total_count' => $query->count(),
            'by_status' => $byStatus,
            'by_type' => $byType,
            'total_estimated_cost' => (int) WorkOrder::sum('estimated_total_cost'),
            'total_actual_cost' => (int) WorkOrder::sum('actual_total_cost'),
            'average_cost_variance' => (int) WorkOrder::where('status', WorkOrder::STATUS_COMPLETED)
                ->avg('cost_variance'),
        ];
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
     * Update project costs from work order.
     */
    public function updateProjectCosts(WorkOrder $wo): void
    {
        $project = $wo->project;
        if (! $project) {
            return;
        }

        $project->calculateFinancials();
        $project->save();
    }

    /**
     * Create delivery order if needed.
     */
    public function createDeliveryOrderIfNeeded(WorkOrder $wo): ?DeliveryOrder
    {
        // Only create DO if WO is linked to a project with a customer
        if (! $wo->project_id) {
            return null;
        }

        $project = $wo->project;
        if (! $project || ! $project->contact_id) {
            return null;
        }

        // Check if DO service exists and create DO
        if (! class_exists(DeliveryOrderService::class)) {
            return null;
        }

        $doService = app(DeliveryOrderService::class);

        return $doService->createFromWorkOrder($wo);
    }

    /**
     * Create work order items.
     *
     * @param  array<array<string, mixed>>  $items
     */
    private function createItems(WorkOrder $wo, array $items, ?int $parentItemId = null, int $level = 0): void
    {
        $sortOrder = 0;

        foreach ($items as $itemData) {
            $item = new WorkOrderItem([
                'work_order_id' => $wo->id,
                'parent_item_id' => $parentItemId,
                'type' => $itemData['type'] ?? WorkOrderItem::TYPE_MATERIAL,
                'product_id' => $itemData['product_id'] ?? null,
                'description' => $itemData['description'],
                'quantity_required' => $itemData['quantity'] ?? $itemData['quantity_required'] ?? 1,
                'unit' => $itemData['unit'] ?? null,
                'unit_cost' => $itemData['unit_cost'] ?? 0,
                'sort_order' => $sortOrder++,
                'level' => $level,
                'notes' => $itemData['notes'] ?? null,
            ]);
            $item->calculateEstimatedCost();
            $item->save();

            // Handle child items if provided
            if (! empty($itemData['children'])) {
                $this->createItems($wo, $itemData['children'], $item->id, $level + 1);
            }
        }
    }

    /**
     * Create items from BOM with hierarchy.
     */
    private function createItemsFromBom(WorkOrder $wo, Bom $bom, float $quantity, ?int $parentItemId = null, int $level = 0): void
    {
        $multiplier = $quantity / (float) $bom->output_quantity;
        $sortOrder = 0;

        foreach ($bom->items as $bomItem) {
            $effectiveQty = $bomItem->getEffectiveQuantity() * $multiplier;

            $item = new WorkOrderItem([
                'work_order_id' => $wo->id,
                'bom_item_id' => $bomItem->id,
                'parent_item_id' => $parentItemId,
                'type' => $bomItem->type,
                'product_id' => $bomItem->product_id,
                'description' => $bomItem->description,
                'quantity_required' => $effectiveQty,
                'unit' => $bomItem->unit,
                'unit_cost' => $bomItem->unit_cost,
                'sort_order' => $sortOrder++,
                'level' => $level,
                'notes' => $bomItem->notes,
            ]);
            $item->calculateEstimatedCost();
            $item->save();

            // Check if this product has its own BOM (for multi-level BOM)
            if ($bomItem->product_id && $bomItem->type === BomItem::TYPE_MATERIAL) {
                $childBom = Bom::where('product_id', $bomItem->product_id)
                    ->where('status', Bom::STATUS_ACTIVE)
                    ->first();

                if ($childBom) {
                    $this->createItemsFromBom($wo, $childBom, $effectiveQty, $item->id, $level + 1);
                }
            }
        }
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
