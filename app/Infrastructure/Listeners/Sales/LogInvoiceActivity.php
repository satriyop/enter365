<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Sales;

use App\Domain\Sales\Invoices\Events\InvoiceSent;
use App\Domain\Sales\Invoices\Events\InvoiceStatusChanged;
use App\Domain\Sales\Invoices\Events\InvoiceVoided;
use Illuminate\Support\Facades\Log;

class LogInvoiceActivity
{
    public function handle(InvoiceStatusChanged|InvoiceSent|InvoiceVoided $event): void
    {
        $context = [
            'invoice_id' => $event->invoiceId ?? $event->invoice_id ?? null,
            'timestamp' => now()->toIso8601String(),
        ];

        if ($event instanceof InvoiceStatusChanged) {
            Log::info('Invoice status changed', [
                'invoice_id' => $event->invoiceId,
                'from_status' => $event->fromStatus->value,
                'to_status' => $event->toStatus->value,
                'user_id' => $event->userId,
            ]);
        }

        if ($event instanceof InvoiceSent) {
            Log::info('Invoice sent to customer', [
                'invoice_id' => $event->invoiceId,
                'invoice_number' => $event->invoiceNumber,
                'customer_id' => $event->customerId,
                'total_amount' => $event->totalAmount,
                'user_id' => $event->userId,
            ]);
        }

        if ($event instanceof InvoiceVoided) {
            Log::info('Invoice voided', [
                'invoice_id' => $event->invoiceId,
                'invoice_number' => $event->invoiceNumber,
                'customer_id' => $event->customerId,
                'total_amount' => $event->totalAmount,
                'user_id' => $event->userId,
                'reason' => $event->reason,
            ]);
        }
    }
}
