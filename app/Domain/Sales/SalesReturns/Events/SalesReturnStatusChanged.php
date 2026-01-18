<?php

declare(strict_types=1);

namespace App\Domain\Sales\SalesReturns\Events;

use App\Enums\DocumentStatus;
use App\Models\Sales\SalesReturn;

readonly class SalesReturnStatusChanged
{
    public function __construct(
        public readonly int $salesReturnId,
        public readonly DocumentStatus $fromStatus,
        public readonly DocumentStatus $toStatus,
        public readonly ?int $userId,
    ) {}

    public static function fromSalesReturn(SalesReturn $sr, DocumentStatus $from, DocumentStatus $to, ?int $userId = null): self
    {
        return new self(
            salesReturnId: $sr->id,
            fromStatus: $from,
            toStatus: $to,
            userId: $userId,
        );
    }
}
