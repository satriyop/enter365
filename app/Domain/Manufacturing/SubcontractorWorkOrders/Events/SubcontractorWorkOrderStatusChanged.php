<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing\SubcontractorWorkOrders\Events;

use App\Enums\DocumentStatus;

class SubcontractorWorkOrderStatusChanged
{
    public function __construct(
        public readonly int $subcontractorWorkOrderId,
        public readonly DocumentStatus $fromStatus,
        public readonly DocumentStatus $toStatus,
        public readonly ?int $userId
    ) {}
}
