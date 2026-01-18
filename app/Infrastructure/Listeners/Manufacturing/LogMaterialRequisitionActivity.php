<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Manufacturing;

class LogMaterialRequisitionActivity
{
    public function handle(
        \App\Domain\Manufacturing\MaterialRequisitions\Events\MaterialRequisitionStatusChanged|
        \App\Domain\Manufacturing\MaterialRequisitions\Events\MaterialRequisitionApproved|
        \App\Domain\Manufacturing\MaterialRequisitions\Events\MaterialRequisitionIssued|
        \App\Domain\Manufacturing\MaterialRequisitions\Events\MaterialRequisitionCancelled $event
    ): void {
        if ($event instanceof \App\Domain\Manufacturing\MaterialRequisitions\Events\MaterialRequisitionStatusChanged) {
            \Log::info('Material Requisition status changed', [
                'material_requisition_id' => $event->materialRequisitionId,
                'from_status' => $event->fromStatus->value,
                'to_status' => $event->toStatus->value,
                'user_id' => $event->userId,
            ]);
        }

        if ($event instanceof \App\Domain\Manufacturing\MaterialRequisitions\Events\MaterialRequisitionApproved) {
            \Log::info('Material Requisition approved', [
                'material_requisition_id' => $event->materialRequisitionId,
                'requisition_number' => $event->requisitionNumber,
                'work_order_id' => $event->workOrderId,
                'user_id' => $event->userId,
            ]);
        }

        if ($event instanceof \App\Domain\Manufacturing\MaterialRequisitions\Events\MaterialRequisitionIssued) {
            \Log::info('Material Requisition issued', [
                'material_requisition_id' => $event->materialRequisitionId,
                'requisition_number' => $event->requisitionNumber,
                'work_order_id' => $event->workOrderId,
                'user_id' => $event->userId,
            ]);
        }

        if ($event instanceof \App\Domain\Manufacturing\MaterialRequisitions\Events\MaterialRequisitionCancelled) {
            \Log::info('Material Requisition cancelled', [
                'material_requisition_id' => $event->materialRequisitionId,
                'requisition_number' => $event->requisitionNumber,
                'work_order_id' => $event->workOrderId,
                'user_id' => $event->userId,
                'reason' => $event->reason,
            ]);
        }
    }
}
