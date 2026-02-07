<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Events;

use App\Models\Purchasing\Bill;

readonly class PaymentSent
{
    public function __construct(
        public readonly int $billId,
        public readonly int $paymentId,
        public readonly int $amount,
        public readonly string $currency,
        public readonly int $supplierId,
        public readonly int $userId,
        public readonly \Carbon\Carbon $paidAt
    ) {}

    public static function fromPayment(
        Bill $bill,
        int $paymentId,
        int $amount,
        int $userId
    ): self {
        return new self(
            billId: $bill->id,
            paymentId: $paymentId,
            amount: $amount,
            currency: $bill->currency ?? 'IDR',
            supplierId: $bill->contact_id,
            userId: $userId,
            paidAt: now()
        );
    }
}
