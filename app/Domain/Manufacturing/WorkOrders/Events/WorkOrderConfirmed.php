<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing\WorkOrders\Events;

use App\Models\Manufacturing\WorkOrder;

readonly class WorkOrderConfirmed
{
    public function __construct(
        public readonly int $workOrderId,
        public readonly string $workOrderNumber,
        public readonly int $projectId,
        public readonly ?int $userId,
        public readonly \Carbon\Carbon $confirmedAt,
    ) {}

    public static function fromWorkOrder(WorkOrder $workOrder, ?int $userId = null): self
    {
        return new self(
            workOrderId: $workOrder->id,
            workOrderNumber: $workOrder->wo_number,
            projectId: $workOrder->project_id ?? 0,
            userId: $userId,
            confirmedAt: $workOrder->confirmed_at ?? now(),
        );
    }
}
