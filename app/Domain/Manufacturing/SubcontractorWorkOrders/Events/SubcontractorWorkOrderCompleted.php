<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing\SubcontractorWorkOrders\Events;

use App\Models\Manufacturing\SubcontractorWorkOrder;

class SubcontractorWorkOrderCompleted
{
    public function __construct(
        public readonly int $subcontractorWorkOrderId,
        public readonly string $scWoNumber,
        public readonly int $subcontractorId,
        public readonly ?int $projectId,
        public readonly ?int $userId,
        public readonly \Carbon\Carbon $completedAt
    ) {}

    public static function fromSubcontractorWorkOrder(SubcontractorWorkOrder $scWo, ?int $userId = null): self
    {
        return new self(
            subcontractorWorkOrderId: $scWo->id,
            scWoNumber: $scWo->sc_wo_number,
            subcontractorId: $scWo->subcontractor_id,
            projectId: $scWo->project_id,
            userId: $userId,
            completedAt: now()
        );
    }
}
