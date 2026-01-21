<?php

declare(strict_types=1);

namespace App\Listeners\Sales;

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Sales\Invoices\Events\InvoiceFullyPaid;
use App\Domain\Sales\Invoices\Events\InvoiceOverdue;
use App\Domain\Sales\Invoices\Events\InvoiceSent;
use App\Domain\Sales\Invoices\Events\InvoiceStatusChanged;
use App\Domain\Sales\Invoices\Events\InvoiceVoided;
use Illuminate\Events\Dispatcher;

/**
 * Handles all invoice-related events.
 *
 * Consolidates logging and audit trail creation for invoice events.
 */
class InvoiceEventSubscriber
{
    public function __construct(
        private ContextualLoggerInterface $logger
    ) {}

    public function handleStatusChanged(InvoiceStatusChanged $event): void
    {
        $this->logger->logOperation('invoice.status_changed', [
            'invoice_id' => $event->invoiceId,
            'from' => $event->fromStatus->value,
            'to' => $event->toStatus->value,
            'user_id' => $event->userId,
        ]);
    }

    public function handleSent(InvoiceSent $event): void
    {
        $this->logger->logOperation('invoice.sent', [
            'invoice_id' => $event->invoiceId,
            'invoice_number' => $event->invoiceNumber,
            'customer_id' => $event->customerId,
            'total_amount' => $event->totalAmount,
            'sent_at' => $event->sentAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleFullyPaid(InvoiceFullyPaid $event): void
    {
        $this->logger->logOperation('invoice.fully_paid', [
            'invoice_id' => $event->invoiceId,
            'invoice_number' => $event->invoiceNumber,
            'total_amount' => $event->totalAmount,
            'paid_at' => $event->paidAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleOverdue(InvoiceOverdue $event): void
    {
        $this->logger->logOperation('invoice.overdue', [
            'invoice_id' => $event->invoiceId,
            'invoice_number' => $event->invoiceNumber,
            'outstanding_amount' => $event->outstandingAmount,
            'due_date' => $event->dueDate->toDateString(),
            'days_overdue' => $event->daysOverdue,
        ]);
    }

    public function handleVoided(InvoiceVoided $event): void
    {
        $this->logger->logOperation('invoice.voided', [
            'invoice_id' => $event->invoiceId,
            'invoice_number' => $event->invoiceNumber,
            'reason' => $event->reason,
            'voided_at' => $event->voidedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            InvoiceStatusChanged::class => 'handleStatusChanged',
            InvoiceSent::class => 'handleSent',
            InvoiceFullyPaid::class => 'handleFullyPaid',
            InvoiceOverdue::class => 'handleOverdue',
            InvoiceVoided::class => 'handleVoided',
        ];
    }
}
