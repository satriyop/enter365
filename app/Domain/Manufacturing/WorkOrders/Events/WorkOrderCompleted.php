<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing\WorkOrders\Events;

use App\Models\Manufacturing\WorkOrder;

readonly class WorkOrderCompleted
{
    public function __construct(
        public readonly int $workOrderId,
        public readonly string $workOrderNumber,
        public readonly int $projectId,
        public readonly ?int $userId,
        public readonly \Carbon\Carbon $completedAt,
    ) {}

    public static function fromWorkOrder(WorkOrder $workOrder, ?int $userId = null): self
    {
        return new self(
            workOrderId: $workOrder->id,
            workOrderNumber: $workOrder->wo_number,
            projectId: $workOrder->project_id ?? 0,
            userId: $userId,
            completedAt: $workOrder->completed_at ?? now(),
        );
    }
}
