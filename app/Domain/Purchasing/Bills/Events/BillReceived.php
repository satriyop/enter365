<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Bills\Events;

use App\Models\Purchasing\Bill;

class BillReceived
{
    public function __construct(
        public readonly int $billId,
        public readonly string $billNumber,
        public readonly int $vendorId,
        public readonly int $totalAmount,
        public readonly string $currency,
        public readonly ?int $userId,
        public readonly \Carbon\Carbon $receivedAt
    ) {}

    public static function fromBill(Bill $bill, ?int $userId = null): self
    {
        return new self(
            billId: $bill->id,
            billNumber: $bill->bill_number,
            vendorId: $bill->contact_id,
            totalAmount: $bill->total_amount,
            currency: $bill->currency,
            userId: $userId,
            receivedAt: now()
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
            'received_at' => $this->receivedAt->toIso8601String(),
        ];
    }
}
