<?php

declare(strict_types=1);

namespace App\Domain\Sales\Events;

use App\Models\Sales\Invoice;

readonly class PaymentVoided
{
    public function __construct(
        public readonly ?int $invoiceId,
        public readonly int $paymentId,
        public readonly int $amount,
        public readonly string $currency,
        public readonly int $customerId,
        public readonly int $userId,
        public readonly \Carbon\Carbon $voidedAt,
        public readonly ?string $reason = null
    ) {}

    public static function fromPayment(
        Invoice $invoice,
        int $paymentId,
        int $amount,
        int $userId,
        ?string $reason = null
    ): self {
        return new self(
            invoiceId: $invoice->id,
            paymentId: $paymentId,
            amount: $amount,
            currency: $invoice->currency,
            customerId: $invoice->contact_id,
            userId: $userId,
            voidedAt: now(),
            reason: $reason
        );
    }

    public function getAmountAsMoney(): \App\Domain\Shared\ValueObjects\Money
    {
        return \App\Domain\Shared\ValueObjects\Money::of($this->amount, $this->currency);
    }

    public function toArray(): array
    {
        return [
            'invoice_id' => $this->invoiceId,
            'payment_id' => $this->paymentId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'customer_id' => $this->customerId,
            'user_id' => $this->userId,
            'voided_at' => $this->voidedAt->toIso8601String(),
            'reason' => $this->reason,
        ];
    }
}
