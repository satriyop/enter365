<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Contracts\Services\Domains\WorkOrderServiceInterface;
use App\Enums\DocumentStatus;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderItem;
use App\Models\Projects\Project;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Service for work order lifecycle management.
 *
 * Handles CRUD operations and status transitions, coordinating with
 * WorkOrderMaterialService and WorkOrderCostService for specialized operations.
 */
class WorkOrderService implements WorkOrderServiceInterface
{
    public function __construct(
        private WorkOrderMaterialService $materialService,
        private WorkOrderCostService $costService
    ) {}

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
            $wo->status = DocumentStatus::Draft;
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
            $wo->status = DocumentStatus::Draft;
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
            $this->materialService->reserveMaterials($wo);

            $wo->status = DocumentStatus::Confirmed;
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

        $wo->status = DocumentStatus::InProgress;
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
            ->whereNotIn('status', [DocumentStatus::Completed, DocumentStatus::Cancelled])
            ->count();

        if ($incompleteSubWos > 0) {
            throw new InvalidArgumentException('Semua sub-work order harus diselesaikan terlebih dahulu.');
        }

        return DB::transaction(function () use ($wo, $userId) {
            // Consume materials (deduct from inventory)
            $this->materialService->consumeMaterials($wo);

            // Calculate actual costs
            $wo->calculateActualCosts();

            $wo->status = DocumentStatus::Completed;
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
                $this->costService->updateProjectCosts($wo);
            }

            // Auto-create delivery order if project has customer
            $this->costService->createDeliveryOrderIfNeeded($wo);

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
            if (in_array($wo->status, [DocumentStatus::Confirmed, DocumentStatus::InProgress])) {
                $this->materialService->releaseMaterials($wo);
            }

            $wo->status = DocumentStatus::Cancelled;
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
        if ($wo->status !== DocumentStatus::InProgress) {
            throw new InvalidArgumentException('Output hanya dapat dicatat saat work order dalam proses.');
        }

        $wo->quantity_completed = (float) $wo->quantity_completed + $quantity;
        $wo->quantity_scrapped = (float) $wo->quantity_scrapped + $scrapped;
        $wo->save();

        return $wo->fresh();
    }

    /**
     * Record material consumption (delegates to material service).
     *
     * @param  array<array<string, mixed>>  $consumptions
     */
    public function recordConsumption(WorkOrder $wo, array $consumptions): void
    {
        $this->materialService->recordConsumption($wo, $consumptions);
    }

    /**
     * Get cost summary (delegates to cost service).
     *
     * @return array<string, mixed>
     */
    public function getCostSummary(WorkOrder $wo): array
    {
        return $this->costService->getCostSummary($wo);
    }

    /**
     * Get material status (delegates to material service).
     *
     * @return array<string, mixed>
     */
    public function getMaterialStatus(WorkOrder $wo): array
    {
        return $this->materialService->getMaterialStatus($wo);
    }

    /**
     * Get statistics (delegates to cost service).
     *
     * @return array<string, mixed>
     */
    public function getStatistics(?string $startDate = null, ?string $endDate = null): array
    {
        return $this->costService->getStatistics($startDate, $endDate);
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
                    ->where('status', DocumentStatus::Active)
                    ->first();

                if ($childBom) {
                    $this->createItemsFromBom($wo, $childBom, $effectiveQty, $item->id, $level + 1);
                }
            }
        }
    }
}
