<?php

declare(strict_types=1);

namespace App\Domain\Sales\Invoices\Events;

use App\Models\Sales\Invoice;

readonly class InvoiceOverdue
{
    public function __construct(
        public readonly int $invoiceId,
        public readonly int $customerId,
        public readonly string $invoiceNumber,
        public readonly int $outstandingAmount,
        public readonly string $currency,
        public readonly \Carbon\Carbon $dueDate,
        public readonly int $daysOverdue
    ) {}

    public static function fromInvoice(Invoice $invoice): self
    {
        $daysOverdue = (int) now()->diffInDays($invoice->due_date);

        return new self(
            invoiceId: $invoice->id,
            customerId: $invoice->contact_id,
            invoiceNumber: $invoice->invoice_number,
            outstandingAmount: $invoice->total_amount - $invoice->paid_amount,
            currency: $invoice->currency,
            dueDate: $invoice->due_date,
            daysOverdue: $daysOverdue
        );
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
            'outstanding_amount' => $this->outstandingAmount,
            'currency' => $this->currency,
            'due_date' => $this->dueDate->toIso8601String(),
            'days_overdue' => $this->daysOverdue,
        ];
    }
}
