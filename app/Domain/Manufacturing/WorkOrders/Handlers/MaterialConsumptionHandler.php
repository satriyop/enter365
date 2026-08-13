<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing\WorkOrders\Handlers;

use App\Models\Manufacturing\WorkOrder;
use App\Services\Manufacturing\WorkOrderMaterialService;

/**
 * Pipeline adapter for material consumption on WO complete.
 *
 * Does not re-implement stock/MR rules — delegates to WorkOrderMaterialService
 * so the live completion path and any pipeline composition stay one implementation.
 *
 * Priority: 10 (before costs and finished goods).
 */
class MaterialConsumptionHandler implements CompletionHandlerInterface
{
    public function __construct(
        private WorkOrderMaterialService $materialService,
    ) {}

    public function handle(WorkOrder $workOrder, ?int $userId = null): void
    {
        $this->materialService->consumeMaterials($workOrder);
    }

    public function priority(): int
    {
        return 10;
    }

    public function shouldHandle(WorkOrder $workOrder): bool
    {
        return $workOrder->warehouse_id !== null
            && $workOrder->materialItems()->exists();
    }
}
