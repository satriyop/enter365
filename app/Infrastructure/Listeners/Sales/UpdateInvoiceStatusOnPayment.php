<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Sales;

use App\Contracts\Sales\InvoiceServiceInterface;
use App\Domain\Sales\Events\PaymentReceived;
use App\Models\Sales\Invoice;

/**
 * Listener that updates invoice payment status after a payment is received.
 *
 * Delegates to InvoiceService which uses the state machine for proper
 * status transitions, event dispatching, and history recording.
 */
class UpdateInvoiceStatusOnPayment
{
    public function __construct(
        private InvoiceServiceInterface $invoiceService
    ) {}

    public function handle(object $event): void
    {
        if (! $event instanceof PaymentReceived) {
            return;
        }

        $invoice = Invoice::find($event->invoiceId);

        if (! $invoice) {
            return;
        }

        // Delegate to InvoiceService which uses state machine
        $this->invoiceService->updatePaymentStatus($invoice);
    }

    public function shouldHandle(object $event): bool
    {
        return $event instanceof PaymentReceived;
    }
}
