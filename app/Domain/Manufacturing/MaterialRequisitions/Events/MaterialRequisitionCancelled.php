<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing\MaterialRequisitions\Events;

use App\Models\Manufacturing\MaterialRequisition;

readonly class MaterialRequisitionCancelled
{
    public function __construct(
        public readonly int $materialRequisitionId,
        public readonly string $requisitionNumber,
        public readonly int $workOrderId,
        public readonly ?int $userId,
        public readonly \Carbon\Carbon $cancelledAt,
        public readonly ?string $reason,
    ) {}

    public static function fromMaterialRequisition(MaterialRequisition $mr, ?int $userId = null): self
    {
        return new self(
            materialRequisitionId: $mr->id,
            requisitionNumber: $mr->requisition_number,
            workOrderId: $mr->work_order_id ?? 0,
            userId: $userId,
            cancelledAt: now(),
            reason: null,
        );
    }
}
