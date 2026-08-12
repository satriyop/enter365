<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Sales;

use App\Domain\Sales\Quotations\Events\QuotationWon;
use App\Models\Sales\Quotation;
use App\Notifications\QuotationWonTeamNotification;
use App\Support\TeamNotifier;
use Illuminate\Support\Facades\Log;

/**
 * Notify sales team when a quotation is marked won.
 */
class NotifySalesTeamOnQuotationWon
{
    public function handle(QuotationWon $event): void
    {
        if (! config('accounting.notifications.quotation_won.enabled', true)) {
            return;
        }

        $quotation = Quotation::with('contact')->find($event->quotationId);

        if (! $quotation) {
            return;
        }

        TeamNotifier::mail(
            new QuotationWonTeamNotification($quotation),
            'quotation_won'
        );

        Log::info('Quotation won team notification queued', [
            'quotation_id' => $quotation->id,
            'quotation_number' => $quotation->quotation_number,
        ]);
    }
}
