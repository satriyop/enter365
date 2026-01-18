<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Purchasing;

use App\Domain\Purchasing\Bills\Events\BillReceived;
use App\Domain\Purchasing\Bills\Events\BillStatusChanged;
use App\Domain\Purchasing\Bills\Events\BillVoided;
use Illuminate\Support\Facades\Log;

class LogBillActivity
{
    public function handle(BillStatusChanged|BillReceived|BillVoided $event): void
    {
        if ($event instanceof BillStatusChanged) {
            Log::info('Bill status changed', [
                'bill_id' => $event->billId,
                'from_status' => $event->fromStatus->value,
                'to_status' => $event->toStatus->value,
                'user_id' => $event->userId,
            ]);
        }

        if ($event instanceof BillReceived) {
            Log::info('Bill received from vendor', [
                'bill_id' => $event->billId,
                'bill_number' => $event->billNumber,
                'vendor_id' => $event->vendorId,
                'total_amount' => $event->totalAmount,
                'user_id' => $event->userId,
            ]);
        }

        if ($event instanceof BillVoided) {
            Log::info('Bill voided', [
                'bill_id' => $event->billId,
                'bill_number' => $event->billNumber,
                'vendor_id' => $event->vendorId,
                'total_amount' => $event->totalAmount,
                'user_id' => $event->userId,
                'reason' => $event->reason,
            ]);
        }
    }
}
