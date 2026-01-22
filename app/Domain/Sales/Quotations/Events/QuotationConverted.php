<?php

declare(strict_types=1);

namespace App\Domain\Sales\Quotations\Events;

use App\Models\Sales\Quotation;

class QuotationConverted
{
    public function __construct(
        public readonly int $quotationId,
        public readonly string $quotationNumber,
        public readonly int $customerId,
        public readonly int $totalAmount,
        public readonly string $currency,
        public readonly int $invoiceId,
        public readonly ?int $userId,
        public readonly \Carbon\Carbon $convertedAt
    ) {}

    public static function fromQuotation(Quotation $quotation, int $invoiceId, ?int $userId = null): self
    {
        return new self(
            quotationId: $quotation->id,
            quotationNumber: $quotation->quotation_number,
            customerId: $quotation->contact_id,
            totalAmount: $quotation->total_amount,
            currency: $quotation->currency,
            invoiceId: $invoiceId,
            userId: $userId,
            convertedAt: now()
        );
    }

    public function toArray(): array
    {
        return [
            'quotation_id' => $this->quotationId,
            'quotation_number' => $this->quotationNumber,
            'customer_id' => $this->customerId,
            'total_amount' => $this->totalAmount,
            'currency' => $this->currency,
            'invoice_id' => $this->invoiceId,
            'user_id' => $this->userId,
            'converted_at' => $this->convertedAt->toIso8601String(),
        ];
    }
}
