<?php

declare(strict_types=1);

namespace App\Contracts\Accounting\Strategies;

use App\Models\Accounting\JournalEntry;
use App\Models\Manufacturing\MaterialConsumption;
use App\Models\Manufacturing\WorkOrder;

/**
 * Strategy for manufacturing cost accounting.
 *
 * Implementations determine how manufacturing costs are tracked:
 * - Project based: Costs flow to project_costs table
 * - Job costing: Costs accumulate per work order
 * - WIP accounting: Full work-in-progress journal entries
 */
interface ManufacturingCostStrategy
{
    /**
     * Handle cost tracking when work order starts.
     *
     * WIP accounting: Dr Work in Progress, Cr ?
     * Job costing: Track in work_order estimated costs
     * Project based: No action (costs flow to project)
     */
    public function onWorkOrderStart(WorkOrder $workOrder): ?JournalEntry;

    /**
     * Handle cost tracking when materials are consumed.
     *
     * WIP accounting: Dr WIP, Cr Raw Materials
     * Job costing: Update work_order actual_material_cost
     * Project based: Create ProjectCost record
     */
    public function onMaterialConsumption(MaterialConsumption $consumption): ?JournalEntry;

    /**
     * Handle cost tracking when work order completes.
     *
     * WIP accounting: Dr Finished Goods, Cr WIP
     * Job costing: Finalize work_order costs
     * Project based: Summarize costs to project
     */
    public function onWorkOrderComplete(WorkOrder $workOrder): ?JournalEntry;

    /**
     * Calculate total manufacturing cost for a work order.
     */
    public function calculateTotalCost(WorkOrder $workOrder): int;

    /**
     * Get the strategy identifier.
     */
    public function getIdentifier(): string;
}
