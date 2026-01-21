<?php

declare(strict_types=1);

namespace App\Domain\Sales\Invoices\Events;

use App\Models\Sales\Invoice;

readonly class InvoicePartiallyPaid
{
    public function __construct(
        public readonly int $invoiceId,
        public readonly int $customerId,
        public readonly string $invoiceNumber,
        public readonly int $paidAmount,
        public readonly int $totalAmount,
        public readonly int $outstandingAmount,
        public readonly string $currency,
        public readonly int $userId
    ) {}

    public static function fromInvoice(Invoice $invoice, int $userId): self
    {
        return new self(
            invoiceId: $invoice->id,
            customerId: $invoice->contact_id,
            invoiceNumber: $invoice->invoice_number,
            paidAmount: $invoice->paid_amount,
            totalAmount: $invoice->total_amount,
            outstandingAmount: $invoice->total_amount - $invoice->paid_amount,
            currency: $invoice->currency,
            userId: $userId
        );
    }

    public function getPaidAmountAsMoney(): \App\Domain\Shared\ValueObjects\Money
    {
        return \App\Domain\Shared\ValueObjects\Money::of($this->paidAmount, $this->currency);
    }

    public function getOutstandingAmountAsMoney(): \App\Domain\Shared\ValueObjects\Money
    {
        return \App\Domain\Shared\ValueObjects\Money::of($this->outstandingAmount, $this->currency);
    }

    public function toArray(): array
    {
        return [
            'invoice_id' => $this->invoiceId,
            'customer_id' => $this->customerId,
            'invoice_number' => $this->invoiceNumber,
            'paid_amount' => $this->paidAmount,
            'total_amount' => $this->totalAmount,
            'outstanding_amount' => $this->outstandingAmount,
            'currency' => $this->currency,
            'user_id' => $this->userId,
        ];
    }
}
