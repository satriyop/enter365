<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Manufacturing\WorkOrderServiceInterface;
use App\Domain\Manufacturing\WorkOrders\WorkOrderDomainFactory;
use App\Enums\DocumentStatus;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderItem;
use App\Models\Projects\Project;
use App\Services\Accounting\AccountingPolicyManager;
use App\Services\Base\BaseService;

class WorkOrderService extends BaseService implements WorkOrderServiceInterface
{
    public function __construct(
        private WorkOrderMaterialService $materialService,
        private WorkOrderCostService $costService,
        private WorkOrderNumberGenerator $numberGenerator,
        private WorkOrderDomainFactory $domainFactory,
        private AccountingPolicyManager $policyManager,
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Create a new work order.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): WorkOrder
    {
        return $this->executeInTransaction('create', function () use ($data) {
            $project = isset($data['project_id']) ? Project::find($data['project_id']) : null;

            $wo = new WorkOrder($data);
            $wo->wo_number = $this->numberGenerator->generate($project);
            $wo->status = DocumentStatus::Draft;
            $wo->save();

            // Create items if provided
            if (! empty($data['items'])) {
                $this->createItems($wo, $data['items']);
            }

            return $wo->fresh(['items', 'project', 'product', 'bom']);
        }, ['product_id' => $data['product_id'] ?? null, 'project_id' => $data['project_id'] ?? null]);
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
        return $this->executeInTransaction('create_from_bom', function () use ($bom, $data) {
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
                'created_by' => $data['created_by'] ?? $this->getUserId(),
            ]);
            $wo->wo_number = $this->numberGenerator->generate($project);
            $wo->status = DocumentStatus::Draft;
            $wo->save();

            // Explode BOM and create items
            $this->createItemsFromBom($wo, $bom, $quantity);

            // Calculate estimated costs
            $this->domainFactory->applyEstimatedCosts($wo);
            $wo->save();

            return $wo->fresh(['items', 'project', 'product', 'bom']);
        }, ['bom_id' => $bom->id, 'quantity' => $data['quantity'] ?? 1]);
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
            throw \App\Exceptions\Domain\DocumentLockedException::cannotEdit($wo, 'Work order hanya dapat diedit dalam status draft.');
        }

        return $this->executeInTransaction('update', function () use ($wo, $data) {
            $wo->fill($data);
            $wo->save();

            // Update items if provided
            if (isset($data['items'])) {
                $wo->items()->delete();
                $this->createItems($wo, $data['items']);
                $this->domainFactory->applyEstimatedCosts($wo);
                $wo->save();
            }

            return $wo->fresh(['items', 'project', 'product', 'bom']);
        }, ['work_order_id' => $wo->id]);
    }

    /**
     * Delete work order.
     */
    public function delete(WorkOrder $wo): bool
    {
        if (! $wo->canBeEdited()) {
            throw \App\Exceptions\Domain\DocumentLockedException::cannotDelete($wo, 'Hanya work order draft yang dapat dihapus.');
        }

        return $this->executeInTransaction('delete', function () use ($wo) {
            $wo->items()->delete();
            $wo->subWorkOrders()->delete();

            return $wo->delete();
        }, ['work_order_id' => $wo->id]);
    }

    /**
     * Confirm work order and reserve materials.
     */
    public function confirm(WorkOrder $wo, ?int $userId = null): WorkOrder
    {
        if (! $this->domainFactory->stateMachine($wo)->canConfirm()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Work Order',
                'dikonfirmasi',
                $wo->status->value,
                'draft dengan item'
            );
        }

        return $this->executeInTransaction('confirm', function () use ($wo, $userId) {
            $this->materialService->reserveMaterials($wo);

            $wo->transitionTo(DocumentStatus::Confirmed, $userId);

            return $wo->fresh();
        }, ['work_order_id' => $wo->id]);
    }

    /**
     * Start work order.
     */
    public function start(WorkOrder $wo, ?int $userId = null): WorkOrder
    {
        if (! $this->domainFactory->stateMachine($wo)->canStart()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Work Order',
                'dimulai',
                $wo->status->value,
                'confirmed'
            );
        }

        return $this->executeInTransaction('start', function () use ($wo, $userId) {
            $wo->transitionTo(DocumentStatus::InProgress, $userId);

            // Strategy may track WIP on start (currently no-op for all methods)
            $this->policyManager->manufacturing()->onWorkOrderStart($wo->fresh());

            return $wo->fresh();
        }, ['work_order_id' => $wo->id]);
    }

    /**
     * Complete work order.
     */
    public function complete(WorkOrder $wo, ?int $userId = null): WorkOrder
    {
        if (! $this->domainFactory->stateMachine($wo)->canComplete()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Work Order',
                'diselesaikan',
                $wo->status->value,
                'in progress'
            );
        }

        return $this->executeInTransaction('complete', function () use ($wo, $userId) {
            $this->materialService->consumeMaterials($wo);

            $this->domainFactory->applyActualCosts($wo);

            $wo->transitionTo(DocumentStatus::Completed, $userId);

            $wo->refresh();

            // Job costing / WIP: transfer accumulated WIP → finished goods
            // Project based: no JE (project financials updated below)
            $this->policyManager->manufacturing()->onWorkOrderComplete($wo);

            if ($wo->project_id) {
                $this->costService->updateProjectCosts($wo);
            }

            $this->costService->createDeliveryOrderIfNeeded($wo);

            return $wo->fresh();
        }, ['work_order_id' => $wo->id]);
    }

    /**
     * Cancel work order.
     */
    public function cancel(WorkOrder $wo, ?string $reason = null, ?int $userId = null): WorkOrder
    {
        if (! $this->domainFactory->stateMachine($wo)->canCancel()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Work Order',
                'dibatalkan',
                $wo->status->value,
                'draft, confirmed, atau in progress'
            );
        }

        return $this->executeInTransaction('cancel', function () use ($wo, $reason, $userId) {
            if (in_array($wo->status, [DocumentStatus::Confirmed, DocumentStatus::InProgress])) {
                $this->materialService->releaseMaterials($wo);
            }

            $wo->transitionTo(DocumentStatus::Cancelled, $userId, [
                'cancellation_reason' => $reason,
            ]);

            return $wo->fresh();
        }, ['work_order_id' => $wo->id]);
    }

    /**
     * Record output quantity.
     */
    public function recordOutput(WorkOrder $wo, float $quantity, float $scrapped = 0): WorkOrder
    {
        if ($wo->status !== DocumentStatus::InProgress) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Work Order',
                'mencatat output',
                $wo->status->value,
                'in progress'
            );
        }

        $wo->quantity_completed = (string) ((float) $wo->quantity_completed + $quantity);
        $wo->quantity_scrapped = (string) ((float) $wo->quantity_scrapped + $scrapped);
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
