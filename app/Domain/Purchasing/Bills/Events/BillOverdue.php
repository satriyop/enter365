<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Bills\Events;

use App\Models\Purchasing\Bill;

readonly class BillOverdue
{
    public function __construct(
        public readonly int $billId,
        public readonly int $vendorId,
        public readonly string $billNumber,
        public readonly int $outstandingAmount,
        public readonly string $currency,
        public readonly \Carbon\Carbon $dueDate,
        public readonly int $daysOverdue
    ) {}

    public static function fromBill(Bill $bill): self
    {
        $daysOverdue = (int) now()->diffInDays($bill->due_date);

        return new self(
            billId: $bill->id,
            vendorId: $bill->contact_id,
            billNumber: $bill->bill_number,
            outstandingAmount: $bill->total_amount - $bill->paid_amount,
            currency: $bill->currency,
            dueDate: $bill->due_date,
            daysOverdue: $daysOverdue
        );
    }

    public function toArray(): array
    {
        return [
            'bill_id' => $this->billId,
            'vendor_id' => $this->vendorId,
            'bill_number' => $this->billNumber,
            'outstanding_amount' => $this->outstandingAmount,
            'currency' => $this->currency,
            'due_date' => $this->dueDate->toIso8601String(),
            'days_overdue' => $this->daysOverdue,
        ];
    }
}
