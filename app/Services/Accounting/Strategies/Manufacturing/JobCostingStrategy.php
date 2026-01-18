<?php

declare(strict_types=1);

namespace App\Services\Accounting\Strategies\Manufacturing;

use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Accounting\Strategies\ManufacturingCostStrategy;
use App\Models\Accounting\JournalEntry;
use App\Models\Manufacturing\MaterialConsumption;
use App\Models\Manufacturing\WorkOrder;

/**
 * Job costing strategy.
 *
 * Tracks costs per work order with journal entries.
 * Suitable for custom manufacturing where each job is unique.
 */
class JobCostingStrategy implements ManufacturingCostStrategy
{
    public function __construct(
        private JournalServiceInterface $journalService
    ) {}

    public function onWorkOrderStart(WorkOrder $workOrder): ?JournalEntry
    {
        // No journal on start - costs recorded as materials are consumed
        return null;
    }

    public function onMaterialConsume(MaterialConsumption $consumption): ?JournalEntry
    {
        // TODO: Implement material consumption journal
        // Dr Work in Progress (by work order/project)
        // Cr Raw Materials Inventory
        return null;
    }

    public function onWorkOrderComplete(WorkOrder $workOrder): ?JournalEntry
    {
        // TODO: Implement work order completion journal
        // Dr Finished Goods / COGS (depending on context)
        // Cr Work in Progress
        return null;
    }

    public function getIdentifier(): string
    {
        return 'job_costing';
    }
}
