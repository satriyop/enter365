<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\PurchaseReturns\Events;

use App\Models\Purchasing\PurchaseReturn;

readonly class PurchaseReturnApproved
{
    public function __construct(
        public readonly int $purchaseReturnId,
        public readonly string $returnNumber,
        public readonly int $contactId,
        public readonly int $billId,
        public readonly ?int $userId,
        public readonly \Carbon\Carbon $approvedAt,
    ) {}

    public static function fromPurchaseReturn(PurchaseReturn $pr, ?int $userId = null): self
    {
        return new self(
            purchaseReturnId: $pr->id,
            returnNumber: $pr->return_number,
            contactId: $pr->contact_id,
            billId: $pr->bill_id ?? 0,
            userId: $userId,
            approvedAt: $pr->approved_at ?? now(),
        );
    }
}
