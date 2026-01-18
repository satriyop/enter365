<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Sales;

use Illuminate\Support\Facades\Log;

class LogPaymentActivity
{
    public function handle(object $event): void
    {
        $logData = $this->buildLogData($event);

        if ($logData === null) {
            return;
        }

        Log::channel('payment')->info($logData['message'], $logData['context']);
    }

    private function buildLogData(object $event): ?array
    {
        $context = match (true) {
            $event instanceof \App\Domain\Sales\Events\PaymentReceived => [
                'message' => 'Payment received',
                'context' => [
                    'type' => 'payment_received',
                    'invoice_id' => $event->invoiceId,
                    'payment_id' => $event->paymentId,
                    'amount' => $event->amount,
                    'currency' => $event->currency,
                    'customer_id' => $event->customerId,
                    'user_id' => $event->userId,
                    'paid_at' => $event->paidAt->toIso8601String(),
                ],
            ],
            $event instanceof \App\Domain\Sales\Events\PaymentVoided => [
                'message' => 'Payment voided',
                'context' => [
                    'type' => 'payment_voided',
                    'invoice_id' => $event->invoiceId,
                    'payment_id' => $event->paymentId,
                    'amount' => $event->amount,
                    'currency' => $event->currency,
                    'customer_id' => $event->customerId,
                    'user_id' => $event->userId,
                    'voided_at' => $event->voidedAt->toIso8601String(),
                    'reason' => $event->reason,
                ],
            ],
            $event instanceof \App\Domain\Sales\Invoices\Events\InvoiceFullyPaid => [
                'message' => 'Invoice fully paid',
                'context' => [
                    'type' => 'invoice_fully_paid',
                    'invoice_id' => $event->invoiceId,
                    'invoice_number' => $event->invoiceNumber,
                    'total_amount' => $event->totalAmount,
                    'currency' => $event->currency,
                    'customer_id' => $event->customerId,
                    'user_id' => $event->userId,
                    'paid_at' => $event->paidAt->toIso8601String(),
                ],
            ],
            default => null,
        };

        return $context;
    }

    public function shouldHandle(object $event): bool
    {
        return $event instanceof \App\Domain\Sales\Events\PaymentReceived
            || $event instanceof \App\Domain\Sales\Events\PaymentVoided
            || $event instanceof \App\Domain\Sales\Invoices\Events\InvoiceFullyPaid;
    }
}
