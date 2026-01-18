<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\PurchaseReturns\Events;

use App\Enums\DocumentStatus;
use App\Models\Purchasing\PurchaseReturn;

readonly class PurchaseReturnStatusChanged
{
    public function __construct(
        public readonly int $purchaseReturnId,
        public readonly DocumentStatus $fromStatus,
        public readonly DocumentStatus $toStatus,
        public readonly ?int $userId,
    ) {}

    public static function fromPurchaseReturn(PurchaseReturn $pr, DocumentStatus $from, DocumentStatus $to, ?int $userId = null): self
    {
        return new self(
            purchaseReturnId: $pr->id,
            fromStatus: $from,
            toStatus: $to,
            userId: $userId,
        );
    }
}
