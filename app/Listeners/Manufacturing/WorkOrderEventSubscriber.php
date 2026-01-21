<?php

declare(strict_types=1);

namespace App\Listeners\Manufacturing;

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Manufacturing\WorkOrders\Events\WorkOrderCancelled;
use App\Domain\Manufacturing\WorkOrders\Events\WorkOrderCompleted;
use App\Domain\Manufacturing\WorkOrders\Events\WorkOrderConfirmed;
use App\Domain\Manufacturing\WorkOrders\Events\WorkOrderStarted;
use App\Domain\Manufacturing\WorkOrders\Events\WorkOrderStatusChanged;
use Illuminate\Events\Dispatcher;

/**
 * Handles all work order-related events.
 */
class WorkOrderEventSubscriber
{
    public function __construct(
        private ContextualLoggerInterface $logger
    ) {}

    public function handleStatusChanged(WorkOrderStatusChanged $event): void
    {
        $this->logger->logOperation('work_order.status_changed', [
            'work_order_id' => $event->workOrderId,
            'from' => $event->fromStatus->value,
            'to' => $event->toStatus->value,
            'user_id' => $event->userId,
        ]);
    }

    public function handleConfirmed(WorkOrderConfirmed $event): void
    {
        $this->logger->logOperation('work_order.confirmed', [
            'work_order_id' => $event->workOrderId,
            'wo_number' => $event->workOrderNumber,
            'project_id' => $event->projectId,
            'confirmed_at' => $event->confirmedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleStarted(WorkOrderStarted $event): void
    {
        $this->logger->logOperation('work_order.started', [
            'work_order_id' => $event->workOrderId,
            'wo_number' => $event->workOrderNumber,
            'started_at' => $event->startedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleCompleted(WorkOrderCompleted $event): void
    {
        $this->logger->logOperation('work_order.completed', [
            'work_order_id' => $event->workOrderId,
            'wo_number' => $event->workOrderNumber,
            'completed_at' => $event->completedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleCancelled(WorkOrderCancelled $event): void
    {
        $this->logger->logOperation('work_order.cancelled', [
            'work_order_id' => $event->workOrderId,
            'wo_number' => $event->workOrderNumber,
            'reason' => $event->reason,
            'user_id' => $event->userId,
        ]);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            WorkOrderStatusChanged::class => 'handleStatusChanged',
            WorkOrderConfirmed::class => 'handleConfirmed',
            WorkOrderStarted::class => 'handleStarted',
            WorkOrderCompleted::class => 'handleCompleted',
            WorkOrderCancelled::class => 'handleCancelled',
        ];
    }
}
