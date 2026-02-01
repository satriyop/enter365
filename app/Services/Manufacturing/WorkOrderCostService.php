<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Contracts\Manufacturing\WorkOrderCostServiceInterface;
use App\Contracts\Sales\DeliveryOrderServiceInterface;
use App\Enums\DocumentStatus;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Sales\DeliveryOrder;

/**
 * Service for work order cost calculations and reporting.
 *
 * Handles cost summaries, statistics, and project cost updates.
 */
class WorkOrderCostService implements WorkOrderCostServiceInterface
{
    public function __construct(
        private DeliveryOrderServiceInterface $deliveryOrderService
    ) {}

    /**
     * Get cost summary.
     *
     * @return array{work_order_id: int, wo_number: string, estimated: array, actual: array, variance: array, variance_percentage: float}
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
     * Get statistics.
     *
     * @return array{total_count: int, by_status: array, by_type: array, total_estimated_cost: int, total_actual_cost: int, average_cost_variance: int}
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
            'average_cost_variance' => (int) WorkOrder::where('status', DocumentStatus::Completed)
                ->avg('cost_variance'),
        ];
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

        return $this->deliveryOrderService->createFromWorkOrder($wo);
    }
}
