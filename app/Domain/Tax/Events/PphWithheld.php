<?php

declare(strict_types=1);

namespace App\Domain\Tax\Events;

use App\Enums\PphCategory;
use App\Models\Shared\Payment;

readonly class PphWithheld
{
    public function __construct(
        public int $paymentId,
        public int $billId,
        public PphCategory $category,
        public float $rate,
        public int $pphAmount,
        public int $supplierId,
        public int $userId,
        public \Carbon\Carbon $withheldAt
    ) {}

    public static function fromPayment(Payment $payment, int $userId): self
    {
        return new self(
            paymentId: $payment->id,
            billId: $payment->payable_id,
            category: $payment->pph_category,
            rate: (float) $payment->pph_rate,
            pphAmount: $payment->pph_amount,
            supplierId: $payment->contact_id,
            userId: $userId,
            withheldAt: now()
        );
    }
}
