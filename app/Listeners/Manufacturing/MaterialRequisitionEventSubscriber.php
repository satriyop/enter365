<?php

declare(strict_types=1);

namespace App\Listeners\Manufacturing;

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Manufacturing\MaterialRequisitions\Events\MaterialRequisitionApproved;
use App\Domain\Manufacturing\MaterialRequisitions\Events\MaterialRequisitionCancelled;
use App\Domain\Manufacturing\MaterialRequisitions\Events\MaterialRequisitionIssued;
use App\Domain\Manufacturing\MaterialRequisitions\Events\MaterialRequisitionStatusChanged;
use Illuminate\Events\Dispatcher;

/**
 * Handles all material requisition-related events.
 */
class MaterialRequisitionEventSubscriber
{
    public function __construct(
        private ContextualLoggerInterface $logger
    ) {}

    public function handleStatusChanged(MaterialRequisitionStatusChanged $event): void
    {
        $this->logger->logOperation('material_requisition.status_changed', [
            'material_requisition_id' => $event->materialRequisitionId,
            'from' => $event->fromStatus->value,
            'to' => $event->toStatus->value,
            'user_id' => $event->userId,
        ]);
    }

    public function handleApproved(MaterialRequisitionApproved $event): void
    {
        $this->logger->logOperation('material_requisition.approved', [
            'material_requisition_id' => $event->materialRequisitionId,
            'requisition_number' => $event->requisitionNumber,
            'work_order_id' => $event->workOrderId,
            'approved_at' => $event->approvedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleIssued(MaterialRequisitionIssued $event): void
    {
        $this->logger->logOperation('material_requisition.issued', [
            'material_requisition_id' => $event->materialRequisitionId,
            'requisition_number' => $event->requisitionNumber,
            'work_order_id' => $event->workOrderId,
            'issued_at' => $event->issuedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleCancelled(MaterialRequisitionCancelled $event): void
    {
        $this->logger->logOperation('material_requisition.cancelled', [
            'material_requisition_id' => $event->materialRequisitionId,
            'requisition_number' => $event->requisitionNumber,
            'work_order_id' => $event->workOrderId,
            'reason' => $event->reason,
            'cancelled_at' => $event->cancelledAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            MaterialRequisitionStatusChanged::class => 'handleStatusChanged',
            MaterialRequisitionApproved::class => 'handleApproved',
            MaterialRequisitionIssued::class => 'handleIssued',
            MaterialRequisitionCancelled::class => 'handleCancelled',
        ];
    }
}
