<?php

declare(strict_types=1);

namespace App\Services\Manufacturing\Subcontractor;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Enums\DocumentStatus;
use App\Models\Contacts\Contact;
use App\Models\Manufacturing\SubcontractorWorkOrder;
use App\Models\Shared\SubcontractorInvoice;
use App\Services\Base\BaseService;

/**
 * Handles subcontractor read/query operations.
 *
 * Extracted from SubcontractorService as part of the Coordinator Pattern refactoring.
 *
 * @see \App\Services\Manufacturing\SubcontractorService The coordinator service
 */
class SubcontractorQueryService extends BaseService
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Get subcontractor statistics.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(?int $subcontractorId = null): array
    {
        $woQuery = SubcontractorWorkOrder::query();
        $invQuery = SubcontractorInvoice::query();

        if ($subcontractorId) {
            $woQuery->where('subcontractor_id', $subcontractorId);
            $invQuery->where('subcontractor_id', $subcontractorId);
        }

        // WO statistics
        $woByStatus = [];
        foreach (SubcontractorWorkOrder::getStatuses() as $status => $label) {
            $woByStatus[$status] = (clone $woQuery)->where('status', $status)->count();
        }

        $totalAgreed = (clone $woQuery)->sum('agreed_amount');
        $totalActual = (clone $woQuery)->where('status', DocumentStatus::Completed)->sum('actual_amount');

        // Invoice statistics
        $invByStatus = [];
        foreach (SubcontractorInvoice::getStatuses() as $status => $label) {
            $invByStatus[$status] = (clone $invQuery)->where('status', $status)->count();
        }

        $totalInvoiced = (clone $invQuery)->sum('gross_amount');
        $pendingApproval = (clone $invQuery)->where('status', SubcontractorInvoice::STATUS_PENDING)->sum('net_amount');

        return [
            'work_orders' => [
                'total' => $woQuery->count(),
                'by_status' => $woByStatus,
                'total_agreed_amount' => (int) $totalAgreed,
                'total_actual_amount' => (int) $totalActual,
            ],
            'invoices' => [
                'total' => $invQuery->count(),
                'by_status' => $invByStatus,
                'total_invoiced' => (int) $totalInvoiced,
                'pending_approval' => (int) $pendingApproval,
            ],
        ];
    }

    /**
     * Get subcontractors list with statistics.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Contact>
     */
    public function getSubcontractors(): \Illuminate\Database\Eloquent\Collection
    {
        return Contact::query()
            ->where('is_subcontractor', true)
            ->where('is_active', true)
            ->withCount([
                'subcontractorWorkOrders as active_work_orders_count' => function ($q) {
                    $q->whereIn('status', [
                        DocumentStatus::Draft,
                        DocumentStatus::Assigned,
                        DocumentStatus::InProgress,
                    ]);
                },
                'subcontractorWorkOrders as completed_work_orders_count' => function ($q) {
                    $q->where('status', DocumentStatus::Completed);
                },
            ])
            ->orderBy('name')
            ->get();
    }
}
