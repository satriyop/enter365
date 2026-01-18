<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\PurchaseReturns\Events;

use App\Models\Purchasing\PurchaseReturn;

readonly class PurchaseReturnCancelled
{
    public function __construct(
        public readonly int $purchaseReturnId,
        public readonly string $returnNumber,
        public readonly int $contactId,
        public readonly int $billId,
        public readonly ?int $userId,
        public readonly \Carbon\Carbon $cancelledAt,
        public readonly ?string $reason,
    ) {}

    public static function fromPurchaseReturn(PurchaseReturn $pr, ?int $userId = null): self
    {
        return new self(
            purchaseReturnId: $pr->id,
            returnNumber: $pr->return_number,
            contactId: $pr->contact_id,
            billId: $pr->bill_id ?? 0,
            userId: $userId,
            cancelledAt: now(),
            reason: $pr->cancellation_reason ?? null,
        );
    }
}
