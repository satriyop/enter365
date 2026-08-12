<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Sales;

use App\Domain\Sales\Quotations\Events\QuotationApproved;
use App\Models\Sales\Quotation;
use App\Notifications\QuotationApprovedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Notify customer by email when a quotation is approved.
 */
class NotifyCustomerOnQuotationApproved
{
    public function handle(QuotationApproved $event): void
    {
        if (! config('accounting.notifications.quotation_approved.enabled', true)) {
            return;
        }

        $quotation = Quotation::with('contact')->find($event->quotationId);

        if (! $quotation) {
            return;
        }

        $email = $quotation->contact?->email;

        if (! $email) {
            Log::info('Quotation approved notification skipped: contact has no email', [
                'quotation_id' => $event->quotationId,
                'customer_id' => $event->customerId,
            ]);

            return;
        }

        Notification::route('mail', $email)
            ->notify(new QuotationApprovedNotification($quotation));

        Log::info('Quotation approved notification queued', [
            'quotation_id' => $quotation->id,
            'customer_email' => $email,
        ]);
    }
}
