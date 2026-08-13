<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing\WorkOrders;

use App\Domain\Manufacturing\WorkOrders\Handlers\CostCalculationHandler;
use App\Domain\Manufacturing\WorkOrders\Handlers\FinishedGoodsHandler;
use App\Enums\DocumentStatus;
use App\Models\Manufacturing\WorkOrder;
use App\Services\Accounting\AccountingPolicyManager;
use App\Services\Manufacturing\WorkOrderCostService;
use App\Services\Manufacturing\WorkOrderMaterialService;

/**
 * Deep module: all side effects of finishing a work order.
 *
 * External seam for callers/tests remains WorkOrderServiceInterface::complete().
 * This class is the implementation behind that seam so inventory, costs, status,
 * finished goods, manufacturing strategy, project costs, and optional DO creation
 * live in one place (locality) with a single ordered sequence (leverage).
 *
 * Sequence (must stay in this order):
 * 1. Consume materials (MR-aware stock, consumptions, cost strategy on consume)
 * 2. Finalize actual costs on the WO
 * 3. Transition to completed (sets quantity_completed when needed)
 * 4. Receive finished goods
 * 5. Manufacturing accounting strategy on complete
 * 6. Project cost rollup + optional delivery order
 */
final class WorkOrderCompletion
{
    public function __construct(
        private WorkOrderMaterialService $materialService,
        private WorkOrderDomainFactory $domainFactory,
        private CostCalculationHandler $costCalculationHandler,
        private FinishedGoodsHandler $finishedGoodsHandler,
        private AccountingPolicyManager $policyManager,
        private WorkOrderCostService $costService,
    ) {}

    public function run(WorkOrder $workOrder, ?int $userId = null): WorkOrder
    {
        // 1. Materials — only path that may reduce raw stock / write consumptions
        $this->materialService->consumeMaterials($workOrder);

        // 2. Costs — production gets full item+total recalc; others still get totals
        if ($this->costCalculationHandler->shouldHandle($workOrder)) {
            $this->costCalculationHandler->handle($workOrder, $userId);
        } else {
            $this->domainFactory->applyActualCosts($workOrder);
        }

        // 3. Status — quantity_completed defaults applied in state machine
        $workOrder->transitionTo(DocumentStatus::Completed, $userId);
        $workOrder->refresh();

        // 4. Finished goods receipt (needs completed qty + costs)
        if ($this->finishedGoodsHandler->shouldHandle($workOrder)) {
            $this->finishedGoodsHandler->handle($workOrder, $userId);
        }

        // 5. Job costing / WIP strategy (project_based is often a no-op JE)
        $this->policyManager->manufacturing()->onWorkOrderComplete($workOrder->fresh());

        // 6. Downstream project + DO side effects
        if ($workOrder->project_id) {
            $this->costService->updateProjectCosts($workOrder);
        }

        $this->costService->createDeliveryOrderIfNeeded($workOrder);

        return $workOrder->fresh();
    }
}
