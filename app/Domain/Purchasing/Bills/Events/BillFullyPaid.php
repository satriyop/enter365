<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Bills\Events;

use App\Models\Purchasing\Bill;

class BillFullyPaid
{
    public function __construct(
        public readonly int $billId,
        public readonly int $vendorId,
        public readonly string $billNumber,
        public readonly int $totalAmount,
        public readonly string $currency,
        public readonly int $userId,
        public readonly \Carbon\Carbon $paidAt
    ) {}

    public static function fromBill(Bill $bill, int $userId): self
    {
        return new self(
            billId: $bill->id,
            vendorId: $bill->contact_id,
            billNumber: $bill->bill_number,
            totalAmount: $bill->total_amount,
            currency: $bill->currency,
            userId: $userId,
            paidAt: now()
        );
    }

    public function toArray(): array
    {
        return [
            'bill_id' => $this->billId,
            'vendor_id' => $this->vendorId,
            'bill_number' => $this->billNumber,
            'total_amount' => $this->totalAmount,
            'currency' => $this->currency,
            'user_id' => $this->userId,
            'paid_at' => $this->paidAt->toIso8601String(),
        ];
    }
}
