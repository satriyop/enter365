<?php

declare(strict_types=1);

namespace App\Domain\Sales\DownPayments\Events;

use App\Models\Sales\DownPayment;

readonly class DownPaymentCreated
{
    public function __construct(
        public int $downPaymentId,
        public string $dpNumber,
        public string $type,
        public int $contactId,
        public int $amount,
        public int $userId,
        public \Carbon\Carbon $createdAt
    ) {}

    public static function fromDownPayment(DownPayment $dp, int $userId): self
    {
        return new self(
            downPaymentId: $dp->id,
            dpNumber: $dp->dp_number,
            type: $dp->type,
            contactId: $dp->contact_id,
            amount: $dp->amount,
            userId: $userId,
            createdAt: now()
        );
    }
}
