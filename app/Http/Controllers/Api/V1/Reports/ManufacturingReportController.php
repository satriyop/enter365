<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Api\V1\Controller;
use App\Models\Contacts\Contact;
use App\Models\Manufacturing\WorkOrder;
use App\Services\Accounting\Reports\ReportServiceFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManufacturingReportController extends Controller
{
    public function __construct(
        private ReportServiceFactory $reports
    ) {}

    /**
     * Laporan Biaya Work Order.
     */
    public function workOrderCosts(Request $request): JsonResponse
    {
        $this->authorize('reports.manufacturing');

        $report = $this->reports->workOrder()->getWorkOrderCostSummary(
            $request->input('start_date'),
            $request->input('end_date'),
            $request->input('status'),
            $request->input('project_id')
        );

        return $this->success($report);
    }

    /**
     * Laporan Detail Biaya Work Order.
     */
    public function workOrderCostDetail(WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('reports.manufacturing');

        $report = $this->reports->workOrder()->getWorkOrderCostDetail($workOrder);

        return $this->success($report);
    }

    /**
     * Laporan Variansi Biaya.
     *
     * @response array{data: array{report_name: string, period: array{start: string|null, end: string|null}, over_budget: list<array{id: int, wo_number: string, name: string, project_number: string|null, status: string, estimated: int, actual: int, variance: int, variance_percent: float}>, under_budget: list<array{id: int, wo_number: string, name: string, project_number: string|null, status: string, estimated: int, actual: int, variance: int, variance_percent: float}>, on_budget: list<array{id: int, wo_number: string, name: string, project_number: string|null, status: string, estimated: int, actual: int, variance: int, variance_percent: float}>, summary: array{total_work_orders: int, over_budget_count: int, under_budget_count: int, on_budget_count: int, total_estimated: int, total_actual: int, total_variance: int, overall_variance_percent: float}}}
     */
    public function costVariance(Request $request): JsonResponse
    {
        $this->authorize('reports.manufacturing');

        $report = $this->reports->workOrder()->getCostVarianceReport(
            $request->input('start_date'),
            $request->input('end_date')
        );

        return $this->success($report);
    }

    /**
     * Laporan Subkontraktor.
     *
     * @response array{data: array{report_name: string, period: array{start: string|null, end: string|null}, subcontractors: list<array{id: int, code: string, name: string, work_orders: array{total: int, completed: int, in_progress: int, draft: int}, financials: array{total_agreed: int, total_actual: int, total_invoiced: int, total_paid: int, outstanding: int, retention_held: int}, performance: array{on_time_completion: float, average_completion_days: int}}>, totals: array{total_subcontractors: int, total_agreed: int, total_paid: int, total_outstanding: int, total_retention: int}}}
     */
    public function subcontractorSummary(Request $request): JsonResponse
    {
        $this->authorize('reports.manufacturing');

        $report = $this->reports->subcontractor()->getSubcontractorSummary(
            $request->input('start_date'),
            $request->input('end_date')
        );

        return $this->success($report);
    }

    /**
     * Laporan Detail Subkontraktor.
     *
     * @response array{data: array{report_name: string, subcontractor: array{id: int, code: string, name: string, phone: string|null, email: string|null, hourly_rate: int|null, daily_rate: int|null}, period: array{start: string|null, end: string|null}, work_orders: list<array{id: int, sc_wo_number: string, name: string, project_number: string|null, project_name: string|null, status: string, agreed_amount: int, actual_amount: int, retention_amount: int, amount_invoiced: int, amount_paid: int, scheduled_start: string|null, scheduled_end: string|null, actual_start: string|null, actual_end: string|null, completion_percentage: float}>, invoices: list<array{id: int, invoice_number: string, invoice_date: string|null, amount: int, status: string, sc_wo_number: string|null}>, summary: array{total_work_orders: int, completed_work_orders: int, total_agreed: int, total_actual: int, total_invoiced: int, total_paid: int, outstanding: int, retention_held: int}}}
     */
    public function subcontractorDetail(Request $request, Contact $contact): JsonResponse
    {
        $this->authorize('reports.manufacturing');

        $report = $this->reports->subcontractor()->getSubcontractorDetail(
            $contact,
            $request->input('start_date'),
            $request->input('end_date')
        );

        return $this->success($report);
    }

    /**
     * Laporan Retensi Subkontraktor.
     *
     * @response array{data: array{report_name: string, retentions: list<array{id: int, sc_wo_number: string, name: string, subcontractor_name: string|null, project_number: string|null, status: string, agreed_amount: int, retention_percent: float, retention_amount: int, scheduled_end: string|null, actual_end: string|null, is_releasable: bool}>, by_subcontractor: list<array{subcontractor: string, total_retention: int, work_orders_count: int, releasable_amount: int}>, totals: array{total_retention_held: int, releasable_amount: int, pending_amount: int, work_orders_count: int}}}
     */
    public function subcontractorRetention(): JsonResponse
    {
        $this->authorize('reports.manufacturing');

        $report = $this->reports->subcontractor()->getRetentionSummary();

        return $this->success($report);
    }
}
