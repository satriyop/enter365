<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Project;
use App\Models\Accounting\ProjectCost;
use Illuminate\Support\Collection;

class ProjectReportService
{
    /**
     * Get project profitability summary for all projects.
     *
     * @return array{
     *     report_name: string,
     *     period: array{start: string|null, end: string|null},
     *     projects: Collection,
     *     totals: array{
     *         total_contract: int,
     *         total_revenue: int,
     *         total_costs: int,
     *         total_profit: int,
     *         average_margin: float,
     *         projects_count: int,
     *         profitable_count: int,
     *         loss_count: int
     *     }
     * }
     */
    public function getProjectProfitabilitySummary(
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $status = null
    ): array {
        $query = Project::query()
            ->with(['contact', 'costs', 'revenues'])
            ->orderBy('created_at', 'desc');

        if ($startDate) {
            $query->where('start_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('start_date', '<=', $endDate);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $projects = $query->get();

        $projectData = $projects->map(function (Project $project) {
            $costBreakdown = $this->getCostBreakdownForProject($project);

            return [
                'id' => $project->id,
                'project_number' => $project->project_number,
                'name' => $project->name,
                'customer' => $project->contact?->name,
                'status' => $project->status,
                'start_date' => $project->start_date?->format('Y-m-d'),
                'end_date' => $project->end_date?->format('Y-m-d'),
                'contract_amount' => $project->contract_amount ?? 0,
                'total_revenue' => $project->total_revenue ?? 0,
                'costs' => $costBreakdown,
                'gross_profit' => $project->gross_profit ?? 0,
                'profit_margin' => (float) ($project->profit_margin ?? 0),
                'budget_amount' => $project->budget_amount ?? 0,
                'budget_variance' => ($project->budget_amount ?? 0) - ($project->total_cost ?? 0),
                'budget_utilization' => $project->getBudgetUtilization(),
                'is_over_budget' => $project->isOverBudget(),
                'progress_percentage' => (float) ($project->progress_percentage ?? 0),
            ];
        });

        $totalContract = $projects->sum('contract_amount');
        $totalRevenue = $projects->sum('total_revenue');
        $totalCosts = $projects->sum('total_cost');
        $totalProfit = $projects->sum('gross_profit');
        $profitableCount = $projects->filter(fn ($p) => ($p->gross_profit ?? 0) > 0)->count();
        $lossCount = $projects->filter(fn ($p) => ($p->gross_profit ?? 0) < 0)->count();

        return [
            'report_name' => 'Laporan Profitabilitas Proyek',
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'projects' => $projectData,
            'totals' => [
                'total_contract' => $totalContract,
                'total_revenue' => $totalRevenue,
                'total_costs' => $totalCosts,
                'total_profit' => $totalProfit,
                'average_margin' => $totalRevenue > 0
                    ? round(($totalProfit / $totalRevenue) * 100, 2)
                    : 0,
                'projects_count' => $projects->count(),
                'profitable_count' => $profitableCount,
                'loss_count' => $lossCount,
            ],
        ];
    }

    /**
     * Get detailed profitability report for a single project.
     *
     * @return array{
     *     report_name: string,
     *     project: array,
     *     cost_breakdown: array,
     *     revenue_breakdown: array,
     *     timeline: array,
     *     kpis: array
     * }
     */
    public function getProjectProfitabilityDetail(Project $project): array
    {
        $project->load(['contact', 'costs.product', 'revenues.invoice', 'invoices', 'bills', 'workOrders']);

        $costBreakdown = $this->getCostBreakdownForProject($project);
        $revenueBreakdown = $this->getRevenueBreakdownForProject($project);

        // Get monthly cost trend (database-agnostic)
        $monthlyCosts = $project->costs()
            ->get()
            ->groupBy(fn ($cost) => $cost->cost_date?->format('Y-m'))
            ->map(fn ($costs) => $costs->sum('total_cost'))
            ->sortKeys();

        return [
            'report_name' => 'Laporan Detail Profitabilitas Proyek',
            'project' => [
                'id' => $project->id,
                'project_number' => $project->project_number,
                'name' => $project->name,
                'description' => $project->description,
                'customer' => $project->contact ? [
                    'id' => $project->contact->id,
                    'name' => $project->contact->name,
                    'code' => $project->contact->code,
                ] : null,
                'status' => $project->status,
                'priority' => $project->priority,
                'location' => $project->location,
                'manager_id' => $project->manager_id,
            ],
            'financials' => [
                'contract_amount' => $project->contract_amount ?? 0,
                'budget_amount' => $project->budget_amount ?? 0,
                'total_revenue' => $project->total_revenue ?? 0,
                'total_cost' => $project->total_cost ?? 0,
                'gross_profit' => $project->gross_profit ?? 0,
                'profit_margin' => (float) ($project->profit_margin ?? 0),
                'budget_variance' => $project->getBudgetVariance(),
                'budget_utilization' => $project->getBudgetUtilization(),
                'is_over_budget' => $project->isOverBudget(),
            ],
            'cost_breakdown' => $costBreakdown,
            'revenue_breakdown' => $revenueBreakdown,
            'timeline' => [
                'planned_start' => $project->start_date?->format('Y-m-d'),
                'planned_end' => $project->end_date?->format('Y-m-d'),
                'actual_start' => $project->actual_start_date?->format('Y-m-d'),
                'actual_end' => $project->actual_end_date?->format('Y-m-d'),
                'duration_days' => $project->getDurationDays(),
                'days_until_deadline' => $project->getDaysUntilDeadline(),
                'is_overdue' => $project->isOverdue(),
            ],
            'progress' => [
                'percentage' => (float) ($project->progress_percentage ?? 0),
                'work_orders_count' => $project->workOrders->count(),
                'work_orders_completed' => $project->workOrders->where('status', 'completed')->count(),
                'invoices_count' => $project->invoices->count(),
                'invoices_paid' => $project->invoices->where('status', 'paid')->count(),
            ],
            'monthly_costs' => $monthlyCosts,
            'kpis' => [
                'cost_per_progress' => ($project->progress_percentage ?? 0) > 0
                    ? round(($project->total_cost ?? 0) / $project->progress_percentage, 2)
                    : 0,
                'revenue_per_progress' => ($project->progress_percentage ?? 0) > 0
                    ? round(($project->total_revenue ?? 0) / $project->progress_percentage, 2)
                    : 0,
                'burn_rate' => $this->calculateBurnRate($project),
            ],
        ];
    }

    /**
     * Get cost analysis across all projects by cost type.
     *
     * @return array{
     *     report_name: string,
     *     period: array{start: string|null, end: string|null},
     *     by_type: array,
     *     by_project: Collection,
     *     totals: array
     * }
     */
    public function getProjectCostAnalysis(
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $query = ProjectCost::query()
            ->with('project');

        if ($startDate) {
            $query->where('cost_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('cost_date', '<=', $endDate);
        }

        // By type
        $byType = $query->clone()
            ->selectRaw('cost_type, SUM(total_cost) as total, COUNT(*) as count')
            ->groupBy('cost_type')
            ->get()
            ->mapWithKeys(fn ($item) => [
                $item->cost_type => [
                    'total' => (int) $item->total,
                    'count' => (int) $item->count,
                    'label' => ProjectCost::getCostTypes()[$item->cost_type] ?? $item->cost_type,
                ],
            ]);

        // By project
        $byProject = $query->clone()
            ->selectRaw('project_id, SUM(total_cost) as total')
            ->groupBy('project_id')
            ->with('project:id,project_number,name')
            ->get()
            ->map(fn ($item) => [
                'project_id' => $item->project_id,
                'project_number' => $item->project?->project_number,
                'project_name' => $item->project?->name,
                'total_cost' => (int) $item->total,
            ])
            ->sortByDesc('total_cost')
            ->values();

        $grandTotal = $query->sum('total_cost');

        return [
            'report_name' => 'Laporan Analisis Biaya Proyek',
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'by_type' => $byType,
            'by_project' => $byProject,
            'totals' => [
                'grand_total' => (int) $grandTotal,
                'cost_types_count' => $byType->count(),
                'projects_count' => $byProject->count(),
            ],
        ];
    }

    /**
     * Get cost breakdown by type for a project.
     *
     * @return array{
     *     material: int,
     *     labor: int,
     *     subcontractor: int,
     *     equipment: int,
     *     overhead: int,
     *     other: int,
     *     total: int
     * }
     */
    private function getCostBreakdownForProject(Project $project): array
    {
        $breakdown = $project->costs()
            ->selectRaw('cost_type, SUM(total_cost) as total')
            ->groupBy('cost_type')
            ->pluck('total', 'cost_type');

        return [
            'material' => (int) ($breakdown[ProjectCost::TYPE_MATERIAL] ?? 0),
            'labor' => (int) ($breakdown[ProjectCost::TYPE_LABOR] ?? 0),
            'subcontractor' => (int) ($breakdown[ProjectCost::TYPE_SUBCONTRACTOR] ?? 0),
            'equipment' => (int) ($breakdown[ProjectCost::TYPE_EQUIPMENT] ?? 0),
            'overhead' => (int) ($breakdown[ProjectCost::TYPE_OVERHEAD] ?? 0),
            'other' => (int) ($breakdown[ProjectCost::TYPE_OTHER] ?? 0),
            'total' => $project->total_cost ?? 0,
        ];
    }

    /**
     * Get revenue breakdown by type for a project.
     *
     * @return array{items: Collection, total: int}
     */
    private function getRevenueBreakdownForProject(Project $project): array
    {
        $breakdown = $project->revenues()
            ->selectRaw('revenue_type, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('revenue_type')
            ->get()
            ->map(fn ($item) => [
                'type' => $item->revenue_type,
                'total' => (int) $item->total,
                'count' => (int) $item->count,
            ]);

        return [
            'items' => $breakdown,
            'total' => $project->total_revenue ?? 0,
        ];
    }

    /**
     * Calculate monthly burn rate for a project.
     */
    private function calculateBurnRate(Project $project): int
    {
        if (! $project->actual_start_date) {
            return 0;
        }

        $startDate = $project->actual_start_date;
        $endDate = $project->actual_end_date ?? now();
        $months = max(1, $startDate->diffInMonths($endDate));

        return (int) round(($project->total_cost ?? 0) / $months);
    }
}
