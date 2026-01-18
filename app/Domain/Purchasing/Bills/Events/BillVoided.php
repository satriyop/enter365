<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Bills\Events;

use App\Models\Purchasing\Bill;

class BillVoided
{
    public function __construct(
        public readonly int $billId,
        public readonly string $billNumber,
        public readonly int $vendorId,
        public readonly int $totalAmount,
        public readonly string $currency,
        public readonly ?int $userId,
        public readonly \Carbon\Carbon $voidedAt,
        public readonly ?string $reason = null
    ) {}

    public static function fromBill(Bill $bill, ?int $userId = null, ?string $reason = null): self
    {
        return new self(
            billId: $bill->id,
            billNumber: $bill->bill_number,
            vendorId: $bill->contact_id,
            totalAmount: $bill->total_amount,
            currency: $bill->currency,
            userId: $userId,
            voidedAt: now(),
            reason: $reason
        );
    }

    public function toArray(): array
    {
        return [
            'bill_id' => $this->billId,
            'bill_number' => $this->billNumber,
            'vendor_id' => $this->vendorId,
            'total_amount' => $this->totalAmount,
            'currency' => $this->currency,
            'user_id' => $this->userId,
            'voided_at' => $this->voidedAt->toIso8601String(),
            'reason' => $this->reason,
        ];
    }
}
