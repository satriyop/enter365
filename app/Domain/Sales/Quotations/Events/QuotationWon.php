<?php

declare(strict_types=1);

namespace App\Domain\Sales\Quotations\Events;

use App\Models\Sales\Quotation;

class QuotationWon
{
    public function __construct(
        public readonly int $quotationId,
        public readonly string $quotationNumber,
        public readonly int $customerId,
        public readonly int $totalAmount,
        public readonly string $currency,
        public readonly ?int $userId,
        public readonly \Carbon\Carbon $wonAt
    ) {}

    public static function fromQuotation(Quotation $quotation, ?int $userId = null): self
    {
        return new self(
            quotationId: $quotation->id,
            quotationNumber: $quotation->quotation_number,
            customerId: $quotation->contact_id,
            totalAmount: $quotation->total_amount,
            currency: $quotation->currency,
            userId: $userId,
            wonAt: now()
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
            'user_id' => $this->userId,
            'won_at' => $this->wonAt->toIso8601String(),
        ];
    }
}
