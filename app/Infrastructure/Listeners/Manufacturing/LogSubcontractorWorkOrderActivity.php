<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Manufacturing;

use App\Domain\Manufacturing\SubcontractorWorkOrders\Events\SubcontractorWorkOrderAssigned;
use App\Domain\Manufacturing\SubcontractorWorkOrders\Events\SubcontractorWorkOrderCancelled;
use App\Domain\Manufacturing\SubcontractorWorkOrders\Events\SubcontractorWorkOrderCompleted;
use App\Domain\Manufacturing\SubcontractorWorkOrders\Events\SubcontractorWorkOrderStarted;
use App\Domain\Manufacturing\SubcontractorWorkOrders\Events\SubcontractorWorkOrderStatusChanged;
use Illuminate\Support\Facades\Log;

class LogSubcontractorWorkOrderActivity
{
    public function handle(
        SubcontractorWorkOrderStatusChanged|SubcontractorWorkOrderAssigned|
        SubcontractorWorkOrderStarted|SubcontractorWorkOrderCompleted|
        SubcontractorWorkOrderCancelled $event
    ): void {
        if ($event instanceof SubcontractorWorkOrderStatusChanged) {
            Log::info('Subcontractor Work Order status changed', [
                'subcontractor_work_order_id' => $event->subcontractorWorkOrderId,
                'from_status' => $event->fromStatus->value,
                'to_status' => $event->toStatus->value,
                'user_id' => $event->userId,
            ]);

            return;
        }

        if ($event instanceof SubcontractorWorkOrderAssigned) {
            Log::info('Subcontractor Work Order assigned', [
                'subcontractor_work_order_id' => $event->subcontractorWorkOrderId,
                'sc_wo_number' => $event->scWoNumber,
                'subcontractor_id' => $event->subcontractorId,
                'project_id' => $event->projectId,
                'user_id' => $event->userId,
            ]);

            return;
        }

        if ($event instanceof SubcontractorWorkOrderStarted) {
            Log::info('Subcontractor Work Order started', [
                'subcontractor_work_order_id' => $event->subcontractorWorkOrderId,
                'sc_wo_number' => $event->scWoNumber,
                'subcontractor_id' => $event->subcontractorId,
                'project_id' => $event->projectId,
                'user_id' => $event->userId,
            ]);

            return;
        }

        if ($event instanceof SubcontractorWorkOrderCompleted) {
            Log::info('Subcontractor Work Order completed', [
                'subcontractor_work_order_id' => $event->subcontractorWorkOrderId,
                'sc_wo_number' => $event->scWoNumber,
                'subcontractor_id' => $event->subcontractorId,
                'project_id' => $event->projectId,
                'user_id' => $event->userId,
            ]);

            return;
        }

        if ($event instanceof SubcontractorWorkOrderCancelled) {
            Log::info('Subcontractor Work Order cancelled', [
                'subcontractor_work_order_id' => $event->subcontractorWorkOrderId,
                'sc_wo_number' => $event->scWoNumber,
                'subcontractor_id' => $event->subcontractorId,
                'project_id' => $event->projectId,
                'user_id' => $event->userId,
                'reason' => $event->reason,
            ]);
        }
    }
}
