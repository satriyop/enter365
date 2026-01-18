<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Purchasing;

use App\Domain\Purchasing\Bills\Events\BillReceived;

/**
 * Listener to notify accounts payable team when bill is received.
 *
 * TODO: Implement notification logic when notification infrastructure is ready.
 *
 * This listener should:
 * 1. Load the bill with vendor relationship
 * 2. Notify the accounts payable team (internal notification/email)
 * 3. Log the notification attempt
 *
 * Example implementation:
 *
 * public function handle(BillReceived $event): void
 * {
 *     $bill = Bill::with('contact')->find($event->billId);
 *
 *     // Notify AP team
 *     Notification::route('slack', config('services.slack.ap_channel'))
 *         ->notify(new BillReceivedNotification($bill));
 *
 *     Log::info('Bill received notification sent to AP team', [
 *         'bill_id' => $event->billId,
 *         'bill_number' => $event->billNumber,
 *         'vendor_id' => $event->vendorId,
 *     ]);
 * }
 */
class NotifyAccountPayableOnBillReceived
{
    public function handle(BillReceived $event): void
    {
        // TODO: Implement notification when infrastructure is ready
        //
        // This listener should be triggered when BillReceived event is dispatched.
        // It will notify the accounts payable team about the new bill.
        //
        // For now, the bill is processed internally by the API.
    }
}
