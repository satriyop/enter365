<?php

declare(strict_types=1);

namespace App\Listeners\Manufacturing;

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Manufacturing\SubcontractorWorkOrders\Events\SubcontractorWorkOrderAssigned;
use App\Domain\Manufacturing\SubcontractorWorkOrders\Events\SubcontractorWorkOrderCancelled;
use App\Domain\Manufacturing\SubcontractorWorkOrders\Events\SubcontractorWorkOrderCompleted;
use App\Domain\Manufacturing\SubcontractorWorkOrders\Events\SubcontractorWorkOrderStarted;
use App\Domain\Manufacturing\SubcontractorWorkOrders\Events\SubcontractorWorkOrderStatusChanged;
use Illuminate\Events\Dispatcher;

/**
 * Handles all subcontractor work order-related events.
 */
class SubcontractorWorkOrderEventSubscriber
{
    public function __construct(
        private ContextualLoggerInterface $logger
    ) {}

    public function handleStatusChanged(SubcontractorWorkOrderStatusChanged $event): void
    {
        $this->logger->logOperation('subcontractor_work_order.status_changed', [
            'subcontractor_work_order_id' => $event->subcontractorWorkOrderId,
            'from' => $event->fromStatus->value,
            'to' => $event->toStatus->value,
            'user_id' => $event->userId,
        ]);
    }

    public function handleAssigned(SubcontractorWorkOrderAssigned $event): void
    {
        $this->logger->logOperation('subcontractor_work_order.assigned', [
            'subcontractor_work_order_id' => $event->subcontractorWorkOrderId,
            'sc_wo_number' => $event->scWoNumber,
            'subcontractor_id' => $event->subcontractorId,
            'project_id' => $event->projectId,
            'assigned_at' => $event->assignedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleStarted(SubcontractorWorkOrderStarted $event): void
    {
        $this->logger->logOperation('subcontractor_work_order.started', [
            'subcontractor_work_order_id' => $event->subcontractorWorkOrderId,
            'sc_wo_number' => $event->scWoNumber,
            'started_at' => $event->startedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleCompleted(SubcontractorWorkOrderCompleted $event): void
    {
        $this->logger->logOperation('subcontractor_work_order.completed', [
            'subcontractor_work_order_id' => $event->subcontractorWorkOrderId,
            'sc_wo_number' => $event->scWoNumber,
            'completed_at' => $event->completedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleCancelled(SubcontractorWorkOrderCancelled $event): void
    {
        $this->logger->logOperation('subcontractor_work_order.cancelled', [
            'subcontractor_work_order_id' => $event->subcontractorWorkOrderId,
            'sc_wo_number' => $event->scWoNumber,
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
            SubcontractorWorkOrderStatusChanged::class => 'handleStatusChanged',
            SubcontractorWorkOrderAssigned::class => 'handleAssigned',
            SubcontractorWorkOrderStarted::class => 'handleStarted',
            SubcontractorWorkOrderCompleted::class => 'handleCompleted',
            SubcontractorWorkOrderCancelled::class => 'handleCancelled',
        ];
    }
}
