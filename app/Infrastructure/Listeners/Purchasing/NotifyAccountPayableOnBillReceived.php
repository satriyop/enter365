<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Purchasing;

use App\Domain\Purchasing\Bills\Events\BillReceived;
use App\Models\Purchasing\Bill;
use App\Notifications\BillReceivedTeamNotification;
use App\Support\TeamNotifier;
use Illuminate\Support\Facades\Log;

/**
 * Notify accounts payable team when a bill is received.
 */
class NotifyAccountPayableOnBillReceived
{
    public function handle(BillReceived $event): void
    {
        if (! config('accounting.notifications.bill_received.enabled', true)) {
            return;
        }

        $bill = Bill::with('contact')->find($event->billId);

        if (! $bill) {
            return;
        }

        TeamNotifier::mail(
            new BillReceivedTeamNotification($bill),
            'bill_received'
        );

        Log::info('Bill received team notification queued', [
            'bill_id' => $bill->id,
            'bill_number' => $bill->bill_number,
        ]);
    }
}
