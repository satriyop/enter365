<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Contact;
use App\Models\Accounting\SubcontractorInvoice;
use App\Models\Accounting\SubcontractorWorkOrder;
use Illuminate\Support\Collection;

class SubcontractorReportService
{
    /**
     * Get subcontractor summary report.
     *
     * @return array{
     *     report_name: string,
     *     period: array{start: string|null, end: string|null},
     *     subcontractors: Collection,
     *     totals: array
     * }
     */
    public function getSubcontractorSummary(
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        // Get all subcontractors with their work orders
        $subcontractors = Contact::query()
            ->where('is_subcontractor', true)
            ->with(['subcontractorWorkOrders' => function ($query) use ($startDate, $endDate) {
                if ($startDate) {
                    $query->where('scheduled_start_date', '>=', $startDate);
                }
                if ($endDate) {
                    $query->where('scheduled_start_date', '<=', $endDate);
                }
            }, 'subcontractorWorkOrders.invoices'])
            ->get();

        $subcontractorData = $subcontractors->map(function (Contact $sub) {
            $workOrders = $sub->subcontractorWorkOrders;

            $totalAgreed = $workOrders->sum('agreed_amount');
            $totalActual = $workOrders->sum('actual_amount');
            $totalInvoiced = $workOrders->sum('amount_invoiced');
            $totalPaid = $workOrders->sum('amount_paid');
            $totalRetention = $workOrders->sum('retention_amount');

            $completedCount = $workOrders->where('status', SubcontractorWorkOrder::STATUS_COMPLETED)->count();
            $totalCount = $workOrders->count();

            // Calculate average completion time for completed WOs
            $completedWOs = $workOrders->where('status', SubcontractorWorkOrder::STATUS_COMPLETED);
            $avgCompletionDays = 0;
            if ($completedWOs->isNotEmpty()) {
                $totalDays = $completedWOs->sum(function ($wo) {
                    if ($wo->actual_start_date && $wo->actual_end_date) {
                        return $wo->actual_start_date->diffInDays($wo->actual_end_date);
                    }

                    return 0;
                });
                $avgCompletionDays = (int) round($totalDays / $completedWOs->count());
            }

            return [
                'id' => $sub->id,
                'code' => $sub->code,
                'name' => $sub->name,
                'work_orders' => [
                    'total' => $totalCount,
                    'completed' => $completedCount,
                    'in_progress' => $workOrders->where('status', SubcontractorWorkOrder::STATUS_IN_PROGRESS)->count(),
                    'draft' => $workOrders->where('status', SubcontractorWorkOrder::STATUS_DRAFT)->count(),
                ],
                'financials' => [
                    'total_agreed' => $totalAgreed,
                    'total_actual' => $totalActual,
                    'total_invoiced' => $totalInvoiced,
                    'total_paid' => $totalPaid,
                    'outstanding' => $totalInvoiced - $totalPaid,
                    'retention_held' => $totalRetention,
                ],
                'performance' => [
                    'on_time_completion' => $totalCount > 0
                        ? round(($completedCount / $totalCount) * 100, 2)
                        : 0,
                    'average_completion_days' => $avgCompletionDays,
                ],
            ];
        })->filter(fn ($sub) => $sub['work_orders']['total'] > 0)->values();

        $totalAgreed = $subcontractorData->sum('financials.total_agreed');
        $totalPaid = $subcontractorData->sum('financials.total_paid');
        $totalOutstanding = $subcontractorData->sum('financials.outstanding');
        $totalRetention = $subcontractorData->sum('financials.retention_held');

        return [
            'report_name' => 'Laporan Subkontraktor',
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'subcontractors' => $subcontractorData,
            'totals' => [
                'total_subcontractors' => $subcontractorData->count(),
                'total_agreed' => $totalAgreed,
                'total_paid' => $totalPaid,
                'total_outstanding' => $totalOutstanding,
                'total_retention' => $totalRetention,
            ],
        ];
    }

    /**
     * Get detailed report for a single subcontractor.
     *
     * @return array{
     *     report_name: string,
     *     subcontractor: array,
     *     work_orders: Collection,
     *     invoices: Collection,
     *     summary: array
     * }
     */
    public function getSubcontractorDetail(
        Contact $subcontractor,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $query = SubcontractorWorkOrder::query()
            ->where('subcontractor_id', $subcontractor->id)
            ->with(['project', 'invoices']);

        if ($startDate) {
            $query->where('scheduled_start_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('scheduled_start_date', '<=', $endDate);
        }

        $workOrders = $query->orderBy('scheduled_start_date', 'desc')->get();

        $workOrderData = $workOrders->map(fn ($wo) => [
            'id' => $wo->id,
            'sc_wo_number' => $wo->sc_wo_number,
            'name' => $wo->name,
            'project_number' => $wo->project?->project_number,
            'project_name' => $wo->project?->name,
            'status' => $wo->status,
            'agreed_amount' => $wo->agreed_amount,
            'actual_amount' => $wo->actual_amount,
            'retention_amount' => $wo->retention_amount,
            'amount_invoiced' => $wo->amount_invoiced,
            'amount_paid' => $wo->amount_paid,
            'scheduled_start' => $wo->scheduled_start_date?->format('Y-m-d'),
            'scheduled_end' => $wo->scheduled_end_date?->format('Y-m-d'),
            'actual_start' => $wo->actual_start_date?->format('Y-m-d'),
            'actual_end' => $wo->actual_end_date?->format('Y-m-d'),
            'completion_percentage' => $wo->completion_percentage,
        ]);

        // Get all invoices
        $invoiceIds = $workOrders->pluck('id');
        $invoices = SubcontractorInvoice::whereIn('subcontractor_work_order_id', $invoiceIds)
            ->orderBy('invoice_date', 'desc')
            ->get()
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'invoice_date' => $inv->invoice_date?->format('Y-m-d'),
                'amount' => $inv->amount,
                'status' => $inv->status,
                'sc_wo_number' => $inv->subcontractorWorkOrder?->sc_wo_number,
            ]);

        $totalAgreed = $workOrders->sum('agreed_amount');
        $totalActual = $workOrders->sum('actual_amount');
        $totalInvoiced = $workOrders->sum('amount_invoiced');
        $totalPaid = $workOrders->sum('amount_paid');
        $totalRetention = $workOrders->sum('retention_amount');

        return [
            'report_name' => 'Laporan Detail Subkontraktor',
            'subcontractor' => [
                'id' => $subcontractor->id,
                'code' => $subcontractor->code,
                'name' => $subcontractor->name,
                'phone' => $subcontractor->phone,
                'email' => $subcontractor->email,
                'hourly_rate' => $subcontractor->hourly_rate,
                'daily_rate' => $subcontractor->daily_rate,
            ],
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'work_orders' => $workOrderData,
            'invoices' => $invoices,
            'summary' => [
                'total_work_orders' => $workOrders->count(),
                'completed_work_orders' => $workOrders->where('status', SubcontractorWorkOrder::STATUS_COMPLETED)->count(),
                'total_agreed' => $totalAgreed,
                'total_actual' => $totalActual,
                'total_invoiced' => $totalInvoiced,
                'total_paid' => $totalPaid,
                'outstanding' => $totalInvoiced - $totalPaid,
                'retention_held' => $totalRetention,
            ],
        ];
    }

    /**
     * Get retention summary report.
     *
     * @return array{
     *     report_name: string,
     *     retentions: Collection,
     *     totals: array
     * }
     */
    public function getRetentionSummary(): array
    {
        $workOrders = SubcontractorWorkOrder::query()
            ->where('retention_amount', '>', 0)
            ->with(['subcontractor', 'project'])
            ->orderBy('scheduled_end_date')
            ->get();

        $retentionData = $workOrders->map(fn ($wo) => [
            'id' => $wo->id,
            'sc_wo_number' => $wo->sc_wo_number,
            'name' => $wo->name,
            'subcontractor_name' => $wo->subcontractor?->name,
            'project_number' => $wo->project?->project_number,
            'status' => $wo->status,
            'agreed_amount' => $wo->agreed_amount,
            'retention_percent' => (float) $wo->retention_percent,
            'retention_amount' => $wo->retention_amount,
            'scheduled_end' => $wo->scheduled_end_date?->format('Y-m-d'),
            'actual_end' => $wo->actual_end_date?->format('Y-m-d'),
            'is_releasable' => $wo->status === SubcontractorWorkOrder::STATUS_COMPLETED,
        ]);

        // Group by subcontractor
        $bySubcontractor = $retentionData->groupBy('subcontractor_name')->map(function ($items, $name) {
            return [
                'subcontractor' => $name,
                'total_retention' => $items->sum('retention_amount'),
                'work_orders_count' => $items->count(),
                'releasable_amount' => $items->where('is_releasable', true)->sum('retention_amount'),
            ];
        })->values();

        $totalRetention = $workOrders->sum('retention_amount');
        $releasableAmount = $workOrders->where('status', SubcontractorWorkOrder::STATUS_COMPLETED)
            ->sum('retention_amount');

        return [
            'report_name' => 'Laporan Retensi Subkontraktor',
            'retentions' => $retentionData,
            'by_subcontractor' => $bySubcontractor,
            'totals' => [
                'total_retention_held' => $totalRetention,
                'releasable_amount' => $releasableAmount,
                'pending_amount' => $totalRetention - $releasableAmount,
                'work_orders_count' => $workOrders->count(),
            ],
        ];
    }
}
