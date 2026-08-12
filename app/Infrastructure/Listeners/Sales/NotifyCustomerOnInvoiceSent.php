<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Sales;

use App\Domain\Sales\Invoices\Events\InvoiceSent;
use App\Models\Sales\Invoice;
use App\Notifications\InvoiceSentNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Notify customer by email when an invoice is sent.
 */
class NotifyCustomerOnInvoiceSent
{
    public function handle(InvoiceSent $event): void
    {
        if (! config('accounting.notifications.invoice_sent.enabled', true)) {
            return;
        }

        $invoice = Invoice::with('contact')->find($event->invoiceId);

        if (! $invoice) {
            return;
        }

        $email = $invoice->contact?->email;

        if (! $email) {
            Log::info('Invoice sent notification skipped: contact has no email', [
                'invoice_id' => $event->invoiceId,
                'customer_id' => $event->customerId,
            ]);

            return;
        }

        Notification::route('mail', $email)
            ->notify(new InvoiceSentNotification($invoice));

        Log::info('Invoice sent notification queued', [
            'invoice_id' => $invoice->id,
            'customer_email' => $email,
        ]);
    }
}
