<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Bills\Events;

use App\Models\Purchasing\Bill;

readonly class BillPartiallyPaid
{
    public function __construct(
        public readonly int $billId,
        public readonly int $vendorId,
        public readonly string $billNumber,
        public readonly int $paidAmount,
        public readonly int $totalAmount,
        public readonly int $outstandingAmount,
        public readonly string $currency,
        public readonly int $userId
    ) {}

    public static function fromBill(Bill $bill, int $userId): self
    {
        return new self(
            billId: $bill->id,
            vendorId: $bill->contact_id,
            billNumber: $bill->bill_number,
            paidAmount: $bill->paid_amount,
            totalAmount: $bill->total_amount,
            outstandingAmount: $bill->total_amount - $bill->paid_amount,
            currency: $bill->currency,
            userId: $userId
        );
    }

    public function toArray(): array
    {
        return [
            'bill_id' => $this->billId,
            'vendor_id' => $this->vendorId,
            'bill_number' => $this->billNumber,
            'paid_amount' => $this->paidAmount,
            'total_amount' => $this->totalAmount,
            'outstanding_amount' => $this->outstandingAmount,
            'currency' => $this->currency,
            'user_id' => $this->userId,
        ];
    }
}
