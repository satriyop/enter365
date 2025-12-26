<?php

namespace App\Services\Accounting;

use App\Models\Accounting\WorkOrder;
use Illuminate\Support\Collection;

class WorkOrderReportService
{
    /**
     * Get work order cost summary.
     *
     * @return array{
     *     report_name: string,
     *     period: array{start: string|null, end: string|null},
     *     work_orders: Collection,
     *     totals: array
     * }
     */
    public function getWorkOrderCostSummary(
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $status = null,
        ?int $projectId = null
    ): array {
        $query = WorkOrder::query()
            ->with(['project', 'product'])
            ->orderBy('created_at', 'desc');

        if ($startDate) {
            $query->where('planned_start_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('planned_start_date', '<=', $endDate);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $workOrders = $query->get();

        $workOrderData = $workOrders->map(function (WorkOrder $wo) {
            return [
                'id' => $wo->id,
                'wo_number' => $wo->wo_number,
                'name' => $wo->name,
                'project_number' => $wo->project?->project_number,
                'project_name' => $wo->project?->name,
                'product_name' => $wo->product?->name,
                'type' => $wo->type,
                'status' => $wo->status,
                'quantity_ordered' => (float) $wo->quantity_ordered,
                'quantity_completed' => (float) $wo->quantity_completed,
                'completion_percentage' => $wo->getCompletionPercentage(),
                'estimated_costs' => [
                    'material' => $wo->estimated_material_cost ?? 0,
                    'labor' => $wo->estimated_labor_cost ?? 0,
                    'overhead' => $wo->estimated_overhead_cost ?? 0,
                    'total' => $wo->estimated_total_cost ?? 0,
                ],
                'actual_costs' => [
                    'material' => $wo->actual_material_cost ?? 0,
                    'labor' => $wo->actual_labor_cost ?? 0,
                    'overhead' => $wo->actual_overhead_cost ?? 0,
                    'total' => $wo->actual_total_cost ?? 0,
                ],
                'variance' => [
                    'material' => ($wo->estimated_material_cost ?? 0) - ($wo->actual_material_cost ?? 0),
                    'labor' => ($wo->estimated_labor_cost ?? 0) - ($wo->actual_labor_cost ?? 0),
                    'overhead' => ($wo->estimated_overhead_cost ?? 0) - ($wo->actual_overhead_cost ?? 0),
                    'total' => $wo->cost_variance ?? 0,
                ],
                'cost_per_unit' => $wo->quantity_completed > 0
                    ? (int) round(($wo->actual_total_cost ?? 0) / (float) $wo->quantity_completed)
                    : 0,
            ];
        });

        $totalEstimated = $workOrders->sum('estimated_total_cost');
        $totalActual = $workOrders->sum('actual_total_cost');
        $totalVariance = $totalEstimated - $totalActual;

        return [
            'report_name' => 'Laporan Biaya Work Order',
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'work_orders' => $workOrderData,
            'totals' => [
                'work_orders_count' => $workOrders->count(),
                'total_estimated' => $totalEstimated,
                'total_actual' => $totalActual,
                'total_variance' => $totalVariance,
                'variance_percent' => $totalEstimated > 0
                    ? round(($totalVariance / $totalEstimated) * 100, 2)
                    : 0,
                'completed_count' => $workOrders->where('status', WorkOrder::STATUS_COMPLETED)->count(),
                'in_progress_count' => $workOrders->where('status', WorkOrder::STATUS_IN_PROGRESS)->count(),
            ],
        ];
    }

    /**
     * Get detailed cost breakdown for a single work order.
     *
     * @return array{
     *     report_name: string,
     *     work_order: array,
     *     cost_breakdown: array,
     *     material_details: Collection,
     *     timeline: array
     * }
     */
    public function getWorkOrderCostDetail(WorkOrder $workOrder): array
    {
        $workOrder->load(['project', 'product', 'items.product', 'consumptions.product']);

        // Get item-level breakdown
        $itemBreakdown = $workOrder->items->map(fn ($item) => [
            'id' => $item->id,
            'type' => $item->type,
            'product_name' => $item->product?->name ?? $item->description,
            'quantity_required' => (float) $item->quantity_required,
            'quantity_consumed' => (float) $item->quantity_consumed,
            'unit' => $item->unit,
            'estimated_cost' => $item->total_estimated_cost ?? 0,
            'actual_cost' => $item->total_actual_cost ?? 0,
            'variance' => ($item->total_estimated_cost ?? 0) - ($item->total_actual_cost ?? 0),
        ]);

        return [
            'report_name' => 'Laporan Detail Biaya Work Order',
            'work_order' => [
                'id' => $workOrder->id,
                'wo_number' => $workOrder->wo_number,
                'name' => $workOrder->name,
                'type' => $workOrder->type,
                'status' => $workOrder->status,
                'project' => $workOrder->project ? [
                    'id' => $workOrder->project->id,
                    'project_number' => $workOrder->project->project_number,
                    'name' => $workOrder->project->name,
                ] : null,
                'product' => $workOrder->product ? [
                    'id' => $workOrder->product->id,
                    'sku' => $workOrder->product->sku,
                    'name' => $workOrder->product->name,
                ] : null,
                'quantity_ordered' => (float) $workOrder->quantity_ordered,
                'quantity_completed' => (float) $workOrder->quantity_completed,
                'quantity_scrapped' => (float) $workOrder->quantity_scrapped,
                'completion_percentage' => $workOrder->getCompletionPercentage(),
            ],
            'cost_summary' => [
                'estimated' => [
                    'material' => $workOrder->estimated_material_cost ?? 0,
                    'labor' => $workOrder->estimated_labor_cost ?? 0,
                    'overhead' => $workOrder->estimated_overhead_cost ?? 0,
                    'total' => $workOrder->estimated_total_cost ?? 0,
                ],
                'actual' => [
                    'material' => $workOrder->actual_material_cost ?? 0,
                    'labor' => $workOrder->actual_labor_cost ?? 0,
                    'overhead' => $workOrder->actual_overhead_cost ?? 0,
                    'total' => $workOrder->actual_total_cost ?? 0,
                ],
                'variance' => [
                    'material' => ($workOrder->estimated_material_cost ?? 0) - ($workOrder->actual_material_cost ?? 0),
                    'labor' => ($workOrder->estimated_labor_cost ?? 0) - ($workOrder->actual_labor_cost ?? 0),
                    'overhead' => ($workOrder->estimated_overhead_cost ?? 0) - ($workOrder->actual_overhead_cost ?? 0),
                    'total' => $workOrder->cost_variance ?? 0,
                ],
                'cost_per_unit' => $workOrder->quantity_completed > 0
                    ? (int) round(($workOrder->actual_total_cost ?? 0) / (float) $workOrder->quantity_completed)
                    : 0,
            ],
            'item_breakdown' => $itemBreakdown,
            'timeline' => [
                'planned_start' => $workOrder->planned_start_date?->format('Y-m-d'),
                'planned_end' => $workOrder->planned_end_date?->format('Y-m-d'),
                'actual_start' => $workOrder->actual_start_date?->format('Y-m-d'),
                'actual_end' => $workOrder->actual_end_date?->format('Y-m-d'),
                'confirmed_at' => $workOrder->confirmed_at?->format('Y-m-d H:i:s'),
                'started_at' => $workOrder->started_at?->format('Y-m-d H:i:s'),
                'completed_at' => $workOrder->completed_at?->format('Y-m-d H:i:s'),
            ],
        ];
    }

    /**
     * Get cost variance report across all work orders.
     *
     * @return array{
     *     report_name: string,
     *     period: array,
     *     over_budget: Collection,
     *     under_budget: Collection,
     *     on_budget: Collection,
     *     summary: array
     * }
     */
    public function getCostVarianceReport(
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $query = WorkOrder::query()
            ->whereIn('status', [WorkOrder::STATUS_COMPLETED, WorkOrder::STATUS_IN_PROGRESS])
            ->where('estimated_total_cost', '>', 0)
            ->with(['project']);

        if ($startDate) {
            $query->where('planned_start_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('planned_start_date', '<=', $endDate);
        }

        $workOrders = $query->get();

        // Threshold: 5% variance is considered "on budget"
        $threshold = 0.05;

        $mapWo = fn (WorkOrder $wo) => [
            'id' => $wo->id,
            'wo_number' => $wo->wo_number,
            'name' => $wo->name,
            'project_number' => $wo->project?->project_number,
            'status' => $wo->status,
            'estimated' => $wo->estimated_total_cost,
            'actual' => $wo->actual_total_cost ?? 0,
            'variance' => $wo->cost_variance ?? 0,
            'variance_percent' => $wo->estimated_total_cost > 0
                ? round((($wo->cost_variance ?? 0) / $wo->estimated_total_cost) * 100, 2)
                : 0,
        ];

        $overBudget = $workOrders->filter(function ($wo) use ($threshold) {
            $variancePercent = $wo->estimated_total_cost > 0
                ? ($wo->cost_variance ?? 0) / $wo->estimated_total_cost
                : 0;

            return $variancePercent < -$threshold; // Over budget = negative variance
        })->map($mapWo)->values();

        $underBudget = $workOrders->filter(function ($wo) use ($threshold) {
            $variancePercent = $wo->estimated_total_cost > 0
                ? ($wo->cost_variance ?? 0) / $wo->estimated_total_cost
                : 0;

            return $variancePercent > $threshold; // Under budget = positive variance
        })->map($mapWo)->values();

        $onBudget = $workOrders->filter(function ($wo) use ($threshold) {
            $variancePercent = $wo->estimated_total_cost > 0
                ? abs(($wo->cost_variance ?? 0) / $wo->estimated_total_cost)
                : 0;

            return $variancePercent <= $threshold;
        })->map($mapWo)->values();

        $totalEstimated = $workOrders->sum('estimated_total_cost');
        $totalActual = $workOrders->sum('actual_total_cost');

        return [
            'report_name' => 'Laporan Variansi Biaya',
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'over_budget' => $overBudget,
            'under_budget' => $underBudget,
            'on_budget' => $onBudget,
            'summary' => [
                'total_work_orders' => $workOrders->count(),
                'over_budget_count' => $overBudget->count(),
                'under_budget_count' => $underBudget->count(),
                'on_budget_count' => $onBudget->count(),
                'total_estimated' => $totalEstimated,
                'total_actual' => $totalActual,
                'total_variance' => $totalEstimated - $totalActual,
                'overall_variance_percent' => $totalEstimated > 0
                    ? round((($totalEstimated - $totalActual) / $totalEstimated) * 100, 2)
                    : 0,
            ],
        ];
    }
}
