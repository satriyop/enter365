<?php

declare(strict_types=1);

namespace App\Services\Sales\Invoice;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Sales\Invoices\Events\InvoiceFullyPaid;
use App\Domain\Sales\Invoices\Events\InvoiceOverdue;
use App\Domain\Sales\Invoices\Events\InvoicePartiallyPaid;
use App\Domain\Sales\Invoices\InvoiceDomainFactory;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Sales\Invoice;
use App\Services\Base\Traits\WithEventDispatching;
use App\Services\Base\Traits\WithOperationContext;
use App\Services\Base\Traits\WithTransaction;

/**
 * Handles invoice payment status transitions.
 *
 * Extracted from InvoiceService as part of the Coordinator Pattern refactoring.
 *
 * @see \App\Services\Sales\InvoiceService The coordinator service
 */
class InvoicePaymentStatusService
{
    use WithEventDispatching;
    use WithOperationContext;
    use WithTransaction;

    protected EventDispatcherInterface $eventDispatcher;

    protected ContextualLoggerInterface $logger;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        private InvoiceDomainFactory $domainFactory,
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
    }

    /**
     * Mark invoice as fully paid.
     *
     * @throws StateTransitionException
     */
    public function markAsPaid(Invoice $invoice): Invoice
    {
        return $this->executeInTransaction('mark_paid', function () use ($invoice) {
            if (! $this->domainFactory->stateMachine($invoice)->canMarkAsPaid()) {
                throw StateTransitionException::actionNotAvailable(
                    'mark_as_paid',
                    $invoice->status->label()
                );
            }

            $invoice->transitionTo(DocumentStatus::Paid, $this->getUserId());

            $this->dispatch(InvoiceFullyPaid::fromInvoice($invoice, $this->getUserId() ?? 0));

            return $invoice;
        }, ['invoice_id' => $invoice->id]);
    }

    /**
     * Mark invoice as partially paid.
     *
     * @throws StateTransitionException
     */
    public function markAsPartial(Invoice $invoice): Invoice
    {
        return $this->executeInTransaction('mark_partial', function () use ($invoice) {
            if (! $this->domainFactory->stateMachine($invoice)->canMarkAsPartial()) {
                throw StateTransitionException::actionNotAvailable(
                    'mark_as_partial',
                    $invoice->status->label()
                );
            }

            $invoice->transitionTo(DocumentStatus::Partial, $this->getUserId());

            $this->dispatch(InvoicePartiallyPaid::fromInvoice($invoice, $this->getUserId() ?? 0));

            return $invoice;
        }, ['invoice_id' => $invoice->id]);
    }

    /**
     * Mark invoice as overdue.
     *
     * @throws StateTransitionException
     */
    public function markAsOverdue(Invoice $invoice): Invoice
    {
        return $this->executeInTransaction('mark_overdue', function () use ($invoice) {
            if (! $this->domainFactory->stateMachine($invoice)->canMarkAsOverdue()) {
                throw StateTransitionException::actionNotAvailable(
                    'mark_as_overdue',
                    $invoice->status->label()
                );
            }

            $invoice->transitionTo(DocumentStatus::Overdue, $this->getUserId());

            $this->dispatch(InvoiceOverdue::fromInvoice($invoice));

            return $invoice;
        }, ['invoice_id' => $invoice->id]);
    }

    /**
     * Update invoice payment status based on current paid amount.
     *
     * Automatically determines the correct status:
     * - Paid: if paid_amount >= total_amount
     * - Partial: if paid_amount > 0 and < total_amount
     * - Overdue: if past due date and not fully paid
     */
    public function updatePaymentStatus(Invoice $invoice): Invoice
    {
        $invoice->refresh();

        // Skip if cancelled
        if ($invoice->status === DocumentStatus::Cancelled) {
            return $invoice;
        }

        // Skip if still draft
        if ($invoice->status === DocumentStatus::Draft) {
            return $invoice;
        }

        // Determine target status from cash + credit-note relief
        if ($invoice->getOutstandingAmount() === 0) {
            // Already paid? No change needed
            if ($invoice->status === DocumentStatus::Paid) {
                return $invoice;
            }

            return $this->markAsPaid($invoice);
        }

        if ($invoice->getSettledAmount() > 0) {
            // Already partial? No change needed
            if ($invoice->status === DocumentStatus::Partial) {
                return $invoice;
            }

            return $this->markAsPartial($invoice);
        }

        // No payment remaining - revert to Sent or Overdue
        if ($invoice->getSettledAmount() === 0) {
            // If was paid or partial, revert back to appropriate status
            if (in_array($invoice->status, [DocumentStatus::Paid, DocumentStatus::Partial])) {
                // Check if overdue
                if ($invoice->due_date->isPast()) {
                    return $this->markAsOverdue($invoice);
                }

                // Revert to Sent
                if ($invoice->stateMachine()->canTransitionTo(DocumentStatus::Sent)) {
                    $invoice->transitionTo(DocumentStatus::Sent, $this->getUserId());

                    return $invoice;
                }
            }
        }

        // No payment - check if overdue
        if ($invoice->due_date->isPast() && ! in_array($invoice->status, [DocumentStatus::Overdue, DocumentStatus::Paid])) {
            return $this->markAsOverdue($invoice);
        }

        return $invoice;
    }

    /**
     * Apply a credit note against AR. Does not treat the amount as cash.
     */
    public function applyCreditNote(Invoice $invoice, int $amount): Invoice
    {
        return $this->executeInTransaction('apply_credit_note', function () use ($invoice, $amount) {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $credit = min(max(0, $amount), $locked->getOutstandingAmount());
            $locked->credited_amount = (int) $locked->credited_amount + $credit;
            $locked->save();

            return $this->updatePaymentStatus($locked);
        }, ['invoice_id' => $invoice->id]);
    }

    /**
     * Reverse a previously applied credit note.
     */
    public function reverseCreditNote(Invoice $invoice, int $amount): Invoice
    {
        return $this->executeInTransaction('reverse_credit_note', function () use ($invoice, $amount) {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $locked->credited_amount = max(0, (int) $locked->credited_amount - max(0, $amount));
            $locked->save();

            return $this->updatePaymentStatus($locked);
        }, ['invoice_id' => $invoice->id]);
    }
}
