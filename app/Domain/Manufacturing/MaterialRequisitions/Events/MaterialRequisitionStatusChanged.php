<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing\MaterialRequisitions\Events;

use App\Enums\DocumentStatus;
use App\Models\Manufacturing\MaterialRequisition;

readonly class MaterialRequisitionStatusChanged
{
    public function __construct(
        public readonly int $materialRequisitionId,
        public readonly DocumentStatus $fromStatus,
        public readonly DocumentStatus $toStatus,
        public readonly ?int $userId,
    ) {}

    public static function fromMaterialRequisition(MaterialRequisition $mr, DocumentStatus $from, DocumentStatus $to, ?int $userId = null): self
    {
        return new self(
            materialRequisitionId: $mr->id,
            fromStatus: $from,
            toStatus: $to,
            userId: $userId,
        );
    }
}
