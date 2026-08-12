<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Sales;

use App\Domain\Sales\Quotations\Events\QuotationSubmitted;
use App\Models\Sales\Quotation;
use App\Notifications\QuotationSubmittedTeamNotification;
use App\Support\TeamNotifier;
use Illuminate\Support\Facades\Log;

/**
 * Notify sales team when a quotation is submitted.
 */
class NotifySalesTeamOnQuotationSubmitted
{
    public function handle(QuotationSubmitted $event): void
    {
        if (! config('accounting.notifications.quotation_submitted.enabled', true)) {
            return;
        }

        $quotation = Quotation::with('contact')->find($event->quotationId);

        if (! $quotation) {
            return;
        }

        TeamNotifier::mail(
            new QuotationSubmittedTeamNotification($quotation),
            'quotation_submitted'
        );

        Log::info('Quotation submitted team notification queued', [
            'quotation_id' => $quotation->id,
            'quotation_number' => $quotation->quotation_number,
        ]);
    }
}
