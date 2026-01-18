<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Sales;

use App\Enums\DocumentStatus;
use App\Models\Sales\Invoice;

class UpdateInvoiceStatusOnPayment
{
    public function handle(object $event): void
    {
        if (! $event instanceof \App\Domain\Sales\Events\PaymentReceived) {
            return;
        }

        $invoice = Invoice::find($event->invoiceId);

        if (! $invoice) {
            return;
        }

        $invoice->refresh();

        if ($invoice->paid_amount >= $invoice->total_amount) {
            $invoice->update(['status' => DocumentStatus::Paid]);
        } elseif ($invoice->paid_amount > 0) {
            $invoice->update(['status' => DocumentStatus::Partial]);
        }
    }

    public function shouldHandle(object $event): bool
    {
        return $event instanceof \App\Domain\Sales\Events\PaymentReceived;
    }
}
