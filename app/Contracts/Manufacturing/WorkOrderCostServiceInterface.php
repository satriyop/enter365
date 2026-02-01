<?php

declare(strict_types=1);

namespace App\Contracts\Manufacturing;

use App\Models\Manufacturing\WorkOrder;
use App\Models\Sales\DeliveryOrder;

/**
 * Interface for Work Order cost calculation and related operations.
 */
interface WorkOrderCostServiceInterface
{
    /**
     * Get cost summary for a work order.
     *
     * @return array<string, mixed>
     */
    public function getCostSummary(WorkOrder $wo): array;

    /**
     * Get work order cost statistics.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(?string $startDate = null, ?string $endDate = null): array;

    /**
     * Update project costs based on work order data.
     */
    public function updateProjectCosts(WorkOrder $wo): void;

    /**
     * Create a delivery order from completed work order if needed.
     */
    public function createDeliveryOrderIfNeeded(WorkOrder $wo): ?DeliveryOrder;
}
