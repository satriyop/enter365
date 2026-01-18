<?php

declare(strict_types=1);

namespace App\Domain\Sales\Invoices\Events;

use App\Models\Sales\Invoice;

readonly class InvoiceFullyPaid
{
    public function __construct(
        public readonly int $invoiceId,
        public readonly int $customerId,
        public readonly string $invoiceNumber,
        public readonly int $totalAmount,
        public readonly string $currency,
        public readonly int $userId,
        public readonly \Carbon\Carbon $paidAt
    ) {}

    public static function fromInvoice(Invoice $invoice, int $userId): self
    {
        return new self(
            invoiceId: $invoice->id,
            customerId: $invoice->contact_id,
            invoiceNumber: $invoice->invoice_number,
            totalAmount: $invoice->total_amount,
            currency: $invoice->currency,
            userId: $userId,
            paidAt: now()
        );
    }

    public function getTotalAmountAsMoney(): \App\Domain\Shared\ValueObjects\Money
    {
        return \App\Domain\Shared\ValueObjects\Money::of($this->totalAmount, $this->currency);
    }

    public function toArray(): array
    {
        return [
            'invoice_id' => $this->invoiceId,
            'customer_id' => $this->customerId,
            'invoice_number' => $this->invoiceNumber,
            'total_amount' => $this->totalAmount,
            'currency' => $this->currency,
            'user_id' => $this->userId,
            'paid_at' => $this->paidAt->toIso8601String(),
        ];
    }
}
