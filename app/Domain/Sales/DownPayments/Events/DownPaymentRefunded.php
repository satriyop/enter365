<?php

declare(strict_types=1);

namespace App\Domain\Sales\DownPayments\Events;

use App\Models\Sales\DownPayment;
use App\Models\Shared\Payment;

readonly class DownPaymentRefunded
{
    public function __construct(
        public int $downPaymentId,
        public string $dpNumber,
        public int $refundPaymentId,
        public int $refundAmount,
        public int $contactId,
        public int $userId,
        public \Carbon\Carbon $refundedAt
    ) {}

    public static function fromDownPayment(DownPayment $dp, Payment $payment, int $refundAmount, int $userId): self
    {
        return new self(
            downPaymentId: $dp->id,
            dpNumber: $dp->dp_number,
            refundPaymentId: $payment->id,
            refundAmount: $refundAmount,
            contactId: $dp->contact_id,
            userId: $userId,
            refundedAt: now()
        );
    }
}
