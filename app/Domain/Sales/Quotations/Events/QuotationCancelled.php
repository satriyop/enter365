<?php

declare(strict_types=1);

namespace App\Domain\Sales\Quotations\Events;

use App\Enums\DocumentStatus;
use App\Models\Sales\Quotation;

class QuotationCancelled
{
    public function __construct(
        public readonly int $quotationId,
        public readonly string $quotationNumber,
        public readonly int $customerId,
        public readonly int $totalAmount,
        public readonly string $currency,
        public readonly DocumentStatus $previousStatus,
        public readonly ?int $userId,
        public readonly \Carbon\Carbon $cancelledAt,
        public readonly ?string $reason = null
    ) {}

    public static function fromQuotation(
        Quotation $quotation,
        DocumentStatus $previousStatus,
        ?int $userId = null,
        ?string $reason = null
    ): self {
        return new self(
            quotationId: $quotation->id,
            quotationNumber: $quotation->quotation_number,
            customerId: $quotation->contact_id,
            totalAmount: $quotation->total_amount ?? 0,
            currency: $quotation->currency ?? 'IDR',
            previousStatus: $previousStatus,
            userId: $userId,
            cancelledAt: now(),
            reason: $reason
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
            'previous_status' => $this->previousStatus->value,
            'user_id' => $this->userId,
            'cancelled_at' => $this->cancelledAt->toIso8601String(),
            'reason' => $this->reason,
        ];
    }
}
