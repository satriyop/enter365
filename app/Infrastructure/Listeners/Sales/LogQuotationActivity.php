<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Sales;

use App\Domain\Sales\Quotations\Events\QuotationApproved;
use App\Domain\Sales\Quotations\Events\QuotationConverted;
use App\Domain\Sales\Quotations\Events\QuotationExpired;
use App\Domain\Sales\Quotations\Events\QuotationLost;
use App\Domain\Sales\Quotations\Events\QuotationRejected;
use App\Domain\Sales\Quotations\Events\QuotationStatusChanged;
use App\Domain\Sales\Quotations\Events\QuotationSubmitted;
use App\Domain\Sales\Quotations\Events\QuotationWon;
use Illuminate\Support\Facades\Log;

class LogQuotationActivity
{
    public function handle(
        QuotationSubmitted|QuotationApproved|QuotationRejected|
        QuotationConverted|QuotationExpired|QuotationWon|QuotationLost|QuotationStatusChanged $event
    ): void {
        if ($event instanceof QuotationStatusChanged) {
            Log::info('Quotation status changed', [
                'quotation_id' => $event->quotationId,
                'from_status' => $event->fromStatus->value,
                'to_status' => $event->toStatus->value,
                'user_id' => $event->userId,
            ]);

            return;
        }

        if ($event instanceof QuotationSubmitted) {
            Log::info('Quotation submitted', [
                'quotation_id' => $event->quotationId,
                'quotation_number' => $event->quotationNumber,
                'customer_id' => $event->customerId,
                'total_amount' => $event->totalAmount,
                'user_id' => $event->userId,
            ]);
        }

        if ($event instanceof QuotationApproved) {
            Log::info('Quotation approved', [
                'quotation_id' => $event->quotationId,
                'quotation_number' => $event->quotationNumber,
                'customer_id' => $event->customerId,
                'total_amount' => $event->totalAmount,
                'user_id' => $event->userId,
            ]);
        }

        if ($event instanceof QuotationRejected) {
            Log::info('Quotation rejected', [
                'quotation_id' => $event->quotationId,
                'quotation_number' => $event->quotationNumber,
                'customer_id' => $event->customerId,
                'total_amount' => $event->totalAmount,
                'user_id' => $event->userId,
                'reason' => $event->reason,
            ]);
        }

        if ($event instanceof QuotationConverted) {
            Log::info('Quotation converted to invoice', [
                'quotation_id' => $event->quotationId,
                'quotation_number' => $event->quotationNumber,
                'invoice_id' => $event->invoiceId,
                'customer_id' => $event->customerId,
                'total_amount' => $event->totalAmount,
                'user_id' => $event->userId,
            ]);
        }

        if ($event instanceof QuotationExpired) {
            Log::info('Quotation expired', [
                'quotation_id' => $event->quotationId,
                'quotation_number' => $event->quotationNumber,
                'customer_id' => $event->customerId,
                'total_amount' => $event->totalAmount,
                'valid_until' => $event->validUntil->toIso8601String(),
            ]);
        }

        if ($event instanceof QuotationWon) {
            Log::info('Quotation marked as won', [
                'quotation_id' => $event->quotationId,
                'quotation_number' => $event->quotationNumber,
                'customer_id' => $event->customerId,
                'total_amount' => $event->totalAmount,
                'user_id' => $event->userId,
            ]);
        }

        if ($event instanceof QuotationLost) {
            Log::info('Quotation marked as lost', [
                'quotation_id' => $event->quotationId,
                'quotation_number' => $event->quotationNumber,
                'customer_id' => $event->customerId,
                'total_amount' => $event->totalAmount,
                'user_id' => $event->userId,
                'reason' => $event->reason,
            ]);
        }
    }
}
