<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Sales;

class LogSalesReturnActivity
{
    public function handle(
        \App\Domain\Sales\SalesReturns\Events\SalesReturnStatusChanged|
        \App\Domain\Sales\SalesReturns\Events\SalesReturnSubmitted|
        \App\Domain\Sales\SalesReturns\Events\SalesReturnApproved|
        \App\Domain\Sales\SalesReturns\Events\SalesReturnRejected|
        \App\Domain\Sales\SalesReturns\Events\SalesReturnCompleted|
        \App\Domain\Sales\SalesReturns\Events\SalesReturnCancelled $event
    ): void {
        if ($event instanceof \App\Domain\Sales\SalesReturns\Events\SalesReturnStatusChanged) {
            \Log::info('Sales Return status changed', [
                'sales_return_id' => $event->salesReturnId,
                'from_status' => $event->fromStatus->value,
                'to_status' => $event->toStatus->value,
                'user_id' => $event->userId,
            ]);
        }

        if ($event instanceof \App\Domain\Sales\SalesReturns\Events\SalesReturnSubmitted) {
            \Log::info('Sales Return submitted', [
                'sales_return_id' => $event->salesReturnId,
                'return_number' => $event->returnNumber,
                'contact_id' => $event->contactId,
                'invoice_id' => $event->invoiceId,
                'user_id' => $event->userId,
            ]);
        }

        if ($event instanceof \App\Domain\Sales\SalesReturns\Events\SalesReturnApproved) {
            \Log::info('Sales Return approved', [
                'sales_return_id' => $event->salesReturnId,
                'return_number' => $event->returnNumber,
                'contact_id' => $event->contactId,
                'invoice_id' => $event->invoiceId,
                'user_id' => $event->userId,
            ]);
        }

        if ($event instanceof \App\Domain\Sales\SalesReturns\Events\SalesReturnRejected) {
            \Log::info('Sales Return rejected', [
                'sales_return_id' => $event->salesReturnId,
                'return_number' => $event->returnNumber,
                'contact_id' => $event->contactId,
                'invoice_id' => $event->invoiceId,
                'user_id' => $event->userId,
                'reason' => $event->reason,
            ]);
        }

        if ($event instanceof \App\Domain\Sales\SalesReturns\Events\SalesReturnCompleted) {
            \Log::info('Sales Return completed', [
                'sales_return_id' => $event->salesReturnId,
                'return_number' => $event->returnNumber,
                'contact_id' => $event->contactId,
                'invoice_id' => $event->invoiceId,
                'user_id' => $event->userId,
            ]);
        }

        if ($event instanceof \App\Domain\Sales\SalesReturns\Events\SalesReturnCancelled) {
            \Log::info('Sales Return cancelled', [
                'sales_return_id' => $event->salesReturnId,
                'return_number' => $event->returnNumber,
                'contact_id' => $event->contactId,
                'invoice_id' => $event->invoiceId,
                'user_id' => $event->userId,
                'reason' => $event->reason,
            ]);
        }
    }
}
