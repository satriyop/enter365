<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Sales;

use App\Domain\Sales\Quotations\Events\QuotationWon;

/**
 * Listener to notify sales team when quotation is won.
 *
 * TODO: Implement notification logic when notification infrastructure is ready.
 *
 * This listener should:
 * 1. Load the quotation with customer relationship
 * 2. Notify the sales team about the won quotation
 * 3. Trigger any downstream processes (e.g., create invoice workflow)
 * 4. Log the notification attempt
 *
 * Example implementation:
 *
 * public function handle(QuotationWon $event): void
 * {
 *     $quotation = Quotation::with('contact')->find($event->quotationId);
 *
 *     // Notify sales team
 *     Notification::route('slack', config('services.slack.sales_channel'))
 *         ->notify(new QuotationWonNotification($quotation));
 *
 *     // Trigger downstream processes
 *     dispatch(new CreateInvoiceFromQuotation($quotation));
 *
 *     Log::info('Quotation won notification sent', [
 *         'quotation_id' => $event->quotationId,
 *         'quotation_number' => $event->quotationNumber,
 *         'customer_id' => $event->customerId,
 *     ]);
 * }
 */
class NotifySalesTeamOnQuotationWon
{
    public function handle(QuotationWon $event): void
    {
        // TODO: Implement notification when infrastructure is ready
        //
        // This listener should be triggered when QuotationWon event is dispatched.
        // It will notify the sales team and trigger downstream processes.
        //
        // For now, the won quotation is processed internally by the API.
    }
}
