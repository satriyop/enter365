<?php

declare(strict_types=1);

namespace App\Domain\Sales\DownPayments\Events;

use App\Models\Sales\DownPayment;

readonly class DownPaymentCancelled
{
    public function __construct(
        public int $downPaymentId,
        public string $dpNumber,
        public int $contactId,
        public int $amount,
        public ?string $reason,
        public int $userId,
        public \Carbon\Carbon $cancelledAt
    ) {}

    public static function fromDownPayment(DownPayment $dp, ?string $reason, int $userId): self
    {
        return new self(
            downPaymentId: $dp->id,
            dpNumber: $dp->dp_number,
            contactId: $dp->contact_id,
            amount: $dp->amount,
            reason: $reason,
            userId: $userId,
            cancelledAt: now()
        );
    }
}
