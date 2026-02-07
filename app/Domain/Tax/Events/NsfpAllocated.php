<?php

declare(strict_types=1);

namespace App\Domain\Tax\Events;

use App\Models\Sales\Invoice;

class NsfpAllocated
{
    public function __construct(
        public readonly int $invoiceId,
        public readonly string $invoiceNumber,
        public readonly string $nsfpNumber,
        public readonly int $rangeId,
        public readonly \Carbon\Carbon $allocatedAt
    ) {}

    public static function fromInvoice(Invoice $invoice, string $nsfpNumber): self
    {
        return new self(
            invoiceId: $invoice->id,
            invoiceNumber: $invoice->invoice_number,
            nsfpNumber: $nsfpNumber,
            rangeId: $invoice->nsfp_range_id,
            allocatedAt: now()
        );
    }

    public function toArray(): array
    {
        return [
            'invoice_id' => $this->invoiceId,
            'invoice_number' => $this->invoiceNumber,
            'nsfp_number' => $this->nsfpNumber,
            'range_id' => $this->rangeId,
            'allocated_at' => $this->allocatedAt->toIso8601String(),
        ];
    }
}
