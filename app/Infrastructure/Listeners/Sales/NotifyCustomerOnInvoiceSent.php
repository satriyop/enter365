<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Sales;

use App\Domain\Sales\Invoices\Events\InvoiceSent;

/**
 * Listener to send invoice notifications to customers.
 *
 * TODO: Implement email notification logic when email infrastructure is ready.
 *
 * This listener should:
 * 1. Load the invoice with customer relationship
 * 2. Check if customer has email address
 * 3. Queue an email job to send the invoice
 * 4. Log the notification attempt
 *
 * Example implementation:
 *
 * public function handle(InvoiceSent $event): void
 * {
 *     $invoice = Invoice::with('contact')->find($event->invoiceId);
 *
 *     if (!$invoice->contact->email) {
 *         Log::warning('Cannot send invoice: customer has no email', [
 *             'invoice_id' => $event->invoiceId,
 *             'customer_id' => $event->customerId,
 *         ]);
 *         return;
 *     }
 *
 *     SendInvoiceEmail::dispatch($invoice)
 *         ->onQueue('notifications')
 *         ->delay(now()->addSeconds(5));
 *
 *     Log::info('Invoice notification queued', [
 *         'invoice_id' => $event->invoiceId,
 *         'customer_email' => $invoice->contact->email,
 *     ]);
 * }
 */
class NotifyCustomerOnInvoiceSent
{
    public function handle(InvoiceSent $event): void
    {
        // TODO: Implement email notification when email infrastructure is ready
        //
        // This listener should be triggered when InvoiceSent event is dispatched.
        // It will queue an email job to send the invoice to the customer.
        //
        // For now, the invoice notification is handled by the API controller
        // that returns the invoice data directly to the client.
    }
}
