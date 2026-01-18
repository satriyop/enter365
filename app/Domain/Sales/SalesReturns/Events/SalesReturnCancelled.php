<?php

declare(strict_types=1);

namespace App\Domain\Sales\SalesReturns\Events;

use App\Models\Sales\SalesReturn;

readonly class SalesReturnCancelled
{
    public function __construct(
        public readonly int $salesReturnId,
        public readonly string $returnNumber,
        public readonly int $contactId,
        public readonly int $invoiceId,
        public readonly ?int $userId,
        public readonly \Carbon\Carbon $cancelledAt,
        public readonly ?string $reason,
    ) {}

    public static function fromSalesReturn(SalesReturn $sr, ?int $userId = null): self
    {
        return new self(
            salesReturnId: $sr->id,
            returnNumber: $sr->return_number,
            contactId: $sr->contact_id,
            invoiceId: $sr->invoice_id ?? 0,
            userId: $userId,
            cancelledAt: now(),
            reason: null,
        );
    }
}
