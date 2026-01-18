<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Sales;

use App\Domain\Sales\Quotations\Events\QuotationSubmitted;

/**
 * Listener to notify sales team when quotation is submitted.
 *
 * TODO: Implement notification logic when notification infrastructure is ready.
 *
 * This listener should:
 * 1. Load the quotation with customer relationship
 * 2. Notify the sales team (internal notification/email/Slack)
 * 3. Log the notification attempt
 *
 * Example implementation:
 *
 * public function handle(QuotationSubmitted $event): void
 * {
 *     $quotation = Quotation::with('contact')->find($event->quotationId);
 *
 *     // Notify sales team
 *     Notification::route('slack', config('services.slack.sales_channel'))
 *         ->notify(new QuotationSubmittedNotification($quotation));
 *
 *     Log::info('Quotation submitted notification sent to sales team', [
 *         'quotation_id' => $event->quotationId,
 *         'quotation_number' => $event->quotationNumber,
 *         'customer_id' => $event->customerId,
 *     ]);
 * }
 */
class NotifySalesTeamOnQuotationSubmitted
{
    public function handle(QuotationSubmitted $event): void
    {
        // TODO: Implement notification when infrastructure is ready
        //
        // This listener should be triggered when QuotationSubmitted event is dispatched.
        // It will notify the sales team about the new quotation for review.
        //
        // For now, the quotation is processed internally by the API.
    }
}
