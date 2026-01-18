<?php

declare(strict_types=1);

namespace App\Domain\Sales\Invoices\Events;

use App\Models\Sales\Invoice;

class InvoiceVoided
{
    public function __construct(
        public readonly int $invoiceId,
        public readonly string $invoiceNumber,
        public readonly int $customerId,
        public readonly int $totalAmount,
        public readonly string $currency,
        public readonly ?int $userId,
        public readonly \Carbon\Carbon $voidedAt,
        public readonly ?string $reason = null
    ) {}

    public static function fromInvoice(Invoice $invoice, ?int $userId = null, ?string $reason = null): self
    {
        return new self(
            invoiceId: $invoice->id,
            invoiceNumber: $invoice->invoice_number,
            customerId: $invoice->contact_id,
            totalAmount: $invoice->total_amount,
            currency: $invoice->currency,
            userId: $userId,
            voidedAt: now(),
            reason: $reason
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
            'voided_at' => $this->voidedAt->toIso8601String(),
            'reason' => $this->reason,
        ];
    }
}
