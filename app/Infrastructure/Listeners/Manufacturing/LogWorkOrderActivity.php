<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Manufacturing;

class LogWorkOrderActivity
{
    public function handle(
        \App\Domain\Manufacturing\WorkOrders\Events\WorkOrderStatusChanged|
        \App\Domain\Manufacturing\WorkOrders\Events\WorkOrderConfirmed|
        \App\Domain\Manufacturing\WorkOrders\Events\WorkOrderStarted|
        \App\Domain\Manufacturing\WorkOrders\Events\WorkOrderCompleted|
        \App\Domain\Manufacturing\WorkOrders\Events\WorkOrderCancelled $event
    ): void {
        if ($event instanceof \App\Domain\Manufacturing\WorkOrders\Events\WorkOrderStatusChanged) {
            \Log::info('Work Order status changed', [
                'work_order_id' => $event->workOrderId,
                'from_status' => $event->fromStatus->value,
                'to_status' => $event->toStatus->value,
                'user_id' => $event->userId,
            ]);
        }

        if ($event instanceof \App\Domain\Manufacturing\WorkOrders\Events\WorkOrderConfirmed) {
            \Log::info('Work Order confirmed', [
                'work_order_id' => $event->workOrderId,
                'work_order_number' => $event->workOrderNumber,
                'project_id' => $event->projectId,
                'user_id' => $event->userId,
            ]);
        }

        if ($event instanceof \App\Domain\Manufacturing\WorkOrders\Events\WorkOrderStarted) {
            \Log::info('Work Order started', [
                'work_order_id' => $event->workOrderId,
                'work_order_number' => $event->workOrderNumber,
                'project_id' => $event->projectId,
                'user_id' => $event->userId,
            ]);
        }

        if ($event instanceof \App\Domain\Manufacturing\WorkOrders\Events\WorkOrderCompleted) {
            \Log::info('Work Order completed', [
                'work_order_id' => $event->workOrderId,
                'work_order_number' => $event->workOrderNumber,
                'project_id' => $event->projectId,
                'user_id' => $event->userId,
            ]);
        }

        if ($event instanceof \App\Domain\Manufacturing\WorkOrders\Events\WorkOrderCancelled) {
            \Log::info('Work Order cancelled', [
                'work_order_id' => $event->workOrderId,
                'work_order_number' => $event->workOrderNumber,
                'project_id' => $event->projectId,
                'user_id' => $event->userId,
                'reason' => $event->reason,
            ]);
        }
    }
}
