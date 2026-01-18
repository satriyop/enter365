<?php

declare(strict_types=1);

namespace App\Services\Accounting\Strategies\Manufacturing;

use App\Contracts\Accounting\Strategies\ManufacturingCostStrategy;
use App\Models\Accounting\JournalEntry;
use App\Models\Manufacturing\MaterialConsumption;
use App\Models\Manufacturing\WorkOrder;

/**
 * Project-based costing strategy.
 *
 * Costs flow directly to project_costs table.
 * No separate WIP accounting - the project IS the WIP.
 * Recommended for EPC/Solar businesses.
 */
class ProjectBasedCostingStrategy implements ManufacturingCostStrategy
{
    public function onWorkOrderStart(WorkOrder $workOrder): ?JournalEntry
    {
        // No journal entry - costs tracked in project_costs
        return null;
    }

    public function onMaterialConsume(MaterialConsumption $consumption): ?JournalEntry
    {
        // No journal entry - material costs flow to project via project_costs
        return null;
    }

    public function onWorkOrderComplete(WorkOrder $workOrder): ?JournalEntry
    {
        // No journal entry - project costing handles this
        return null;
    }

    public function getIdentifier(): string
    {
        return 'project_based';
    }
}
