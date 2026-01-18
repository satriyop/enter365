<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing\WorkOrders\Events;

use App\Enums\DocumentStatus;
use App\Models\Manufacturing\WorkOrder;

readonly class WorkOrderStatusChanged
{
    public function __construct(
        public readonly int $workOrderId,
        public readonly DocumentStatus $fromStatus,
        public readonly DocumentStatus $toStatus,
        public readonly ?int $userId,
    ) {}

    public static function fromWorkOrder(WorkOrder $wo, DocumentStatus $from, DocumentStatus $to, ?int $userId = null): self
    {
        return new self(
            workOrderId: $wo->id,
            fromStatus: $from,
            toStatus: $to,
            userId: $userId,
        );
    }
}
