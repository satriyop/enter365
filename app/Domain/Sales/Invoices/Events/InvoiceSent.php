<?php

declare(strict_types=1);

namespace App\Domain\Sales\Invoices\Events;

use App\Models\Sales\Invoice;

class InvoiceSent
{
    public function __construct(
        public readonly int $invoiceId,
        public readonly string $invoiceNumber,
        public readonly int $customerId,
        public readonly int $totalAmount,
        public readonly string $currency,
        public readonly ?int $userId,
        public readonly \Carbon\Carbon $sentAt
    ) {}

    public static function fromInvoice(Invoice $invoice, ?int $userId = null): self
    {
        return new self(
            invoiceId: $invoice->id,
            invoiceNumber: $invoice->invoice_number,
            customerId: $invoice->contact_id,
            totalAmount: $invoice->total_amount,
            currency: $invoice->currency,
            userId: $userId,
            sentAt: now()
        );
    }

    public function toArray(): array
    {
        return [
            'invoice_id' => $this->invoiceId,
            'invoice_number' => $this->invoiceNumber,
            'customer_id' => $this->customerId,
            'total_amount' => $this->totalAmount,
            'currency' => $this->currency,
            'user_id' => $this->userId,
            'sent_at' => $this->sentAt->toIso8601String(),
        ];
    }
}
