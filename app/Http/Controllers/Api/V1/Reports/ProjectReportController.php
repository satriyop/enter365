<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Api\V1\Controller;
use App\Models\Projects\Project;
use App\Services\Accounting\Reports\ReportServiceFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectReportController extends Controller
{
    public function __construct(
        private ReportServiceFactory $reports
    ) {}

    /**
     * Laporan Profitabilitas Proyek (Project Profitability Report).
     *
     * @response array{data: array{report_name: string, period: array{start: string|null, end: string|null}, projects: list<array{id: int, project_number: string, name: string, customer: string|null, status: string, start_date: string|null, end_date: string|null, contract_amount: int, total_revenue: int, costs: array{material: int, labor: int, subcontractor: int, equipment: int, overhead: int, other: int, total: int}, gross_profit: int, profit_margin: float, budget_amount: int, budget_variance: int, budget_utilization: float, is_over_budget: bool, progress_percentage: float}>, totals: array{total_contract: int, total_revenue: int, total_costs: int, total_profit: int, average_margin: float, projects_count: int, profitable_count: int, loss_count: int}}}
     */
    public function projectProfitability(Request $request): JsonResponse
    {
        $this->authorize('reports.project');

        $report = $this->reports->project()->getProjectProfitabilitySummary(
            $request->input('start_date'),
            $request->input('end_date'),
            $request->input('status')
        );

        return $this->success($report);
    }

    /**
     * Laporan Detail Profitabilitas Proyek.
     *
     * @response array{data: array{report_name: string, project: array{id: int, project_number: string, name: string, description: string|null, customer: array{id: int, name: string, code: string}|null, status: string, priority: string|null, location: string|null, manager_id: int|null}, financials: array{contract_amount: int, budget_amount: int, total_revenue: int, total_cost: int, gross_profit: int, profit_margin: float, budget_variance: int, budget_utilization: float, is_over_budget: bool}, cost_breakdown: array{material: int, labor: int, subcontractor: int, equipment: int, overhead: int, other: int, total: int}, revenue_breakdown: array{items: list<array{type: string, total: int, count: int}>, total: int}, timeline: array{planned_start: string|null, planned_end: string|null, actual_start: string|null, actual_end: string|null, duration_days: int, days_until_deadline: int|null, is_overdue: bool}, progress: array{percentage: float, work_orders_count: int, work_orders_completed: int, invoices_count: int, invoices_paid: int}, monthly_costs: array<string, int>, kpis: array{cost_per_progress: float, revenue_per_progress: float, burn_rate: int}}}
     */
    public function projectProfitabilityDetail(Project $project): JsonResponse
    {
        $this->authorize('reports.project');

        $report = $this->reports->project()->getProjectProfitabilityDetail($project);

        return $this->success($report);
    }

    /**
     * Laporan Analisis Biaya Proyek.
     *
     * @response array{data: array{report_name: string, period: array{start: string|null, end: string|null}, by_type: array<string, array{total: int, count: int, label: string}>, by_project: list<array{project_id: int, project_number: string|null, project_name: string|null, total_cost: int}>, totals: array{grand_total: int, cost_types_count: int, projects_count: int}}}
     */
    public function projectCostAnalysis(Request $request): JsonResponse
    {
        $this->authorize('reports.project');

        $report = $this->reports->project()->getProjectCostAnalysis(
            $request->input('start_date'),
            $request->input('end_date')
        );

        return $this->success($report);
    }
}
