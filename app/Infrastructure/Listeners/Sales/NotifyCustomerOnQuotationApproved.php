<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Sales;

use App\Domain\Sales\Quotations\Events\QuotationApproved;

/**
 * Listener to notify customer when quotation is approved.
 *
 * TODO: Implement notification logic when notification infrastructure is ready.
 *
 * This listener should:
 * 1. Load the quotation with customer relationship
 * 2. Check if customer has email address
 * 3. Queue an email job to send the approved quotation
 * 4. Log the notification attempt
 *
 * Example implementation:
 *
 * public function handle(QuotationApproved $event): void
 * {
 *     $quotation = Quotation::with('contact')->find($event->quotationId);
 *
 *     if (!$quotation->contact->email) {
 *         Log::warning('Cannot send approved quotation: customer has no email', [
 *             'quotation_id' => $event->quotationId,
 *             'customer_id' => $event->customerId,
 *         ]);
 *         return;
 *     }
 *
 *     SendApprovedQuotationEmail::dispatch($quotation)
 *         ->onQueue('notifications')
 *         ->delay(now()->addSeconds(5));
 *
 *     Log::info('Approved quotation notification queued', [
 *         'quotation_id' => $event->quotationId,
 *         'customer_email' => $quotation->contact->email,
 *     ]);
 * }
 */
class NotifyCustomerOnQuotationApproved
{
    public function handle(QuotationApproved $event): void
    {
        // TODO: Implement email notification when email infrastructure is ready
        //
        // This listener should be triggered when QuotationApproved event is dispatched.
        // It will queue an email job to send the approved quotation to the customer.
        //
        // For now, the approved quotation is available via API.
    }
}
