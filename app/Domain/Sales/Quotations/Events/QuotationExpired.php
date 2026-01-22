<?php

declare(strict_types=1);

namespace App\Domain\Sales\Quotations\Events;

use App\Models\Sales\Quotation;

class QuotationExpired
{
    public function __construct(
        public readonly int $quotationId,
        public readonly string $quotationNumber,
        public readonly int $customerId,
        public readonly int $totalAmount,
        public readonly string $currency,
        public readonly \Carbon\Carbon $validUntil,
        public readonly \Carbon\Carbon $expiredAt
    ) {}

    public static function fromQuotation(Quotation $quotation): self
    {
        return new self(
            quotationId: $quotation->id,
            quotationNumber: $quotation->quotation_number,
            customerId: $quotation->contact_id,
            totalAmount: $quotation->total_amount,
            currency: $quotation->currency,
            validUntil: $quotation->valid_until,
            expiredAt: now()
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
            'valid_until' => $this->validUntil->toIso8601String(),
            'expired_at' => $this->expiredAt->toIso8601String(),
        ];
    }
}
