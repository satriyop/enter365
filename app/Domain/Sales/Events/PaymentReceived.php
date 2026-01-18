<?php

declare(strict_types=1);

namespace App\Domain\Sales\Events;

use App\Models\Sales\Invoice;

readonly class PaymentReceived
{
    public function __construct(
        public readonly int $invoiceId,
        public readonly int $paymentId,
        public readonly int $amount,
        public readonly string $currency,
        public readonly int $customerId,
        public readonly int $userId,
        public readonly \Carbon\Carbon $paidAt
    ) {}

    public static function fromPayment(
        Invoice $invoice,
        int $paymentId,
        int $amount,
        int $userId
    ): self {
        return new self(
            invoiceId: $invoice->id,
            paymentId: $paymentId,
            amount: $amount,
            currency: $invoice->currency,
            customerId: $invoice->contact_id,
            userId: $userId,
            paidAt: now()
        );
    }

    public function getAmountAsMoney(): \App\Domain\Shared\ValueObjects\Money
    {
        return \App\Domain\Shared\ValueObjects\Money::of($this->amount, $this->currency);
    }

    public function isIDRCurrency(): bool
    {
        return $this->currency === 'IDR';
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
            'paid_at' => $this->paidAt->toIso8601String(),
        ];
    }
}
