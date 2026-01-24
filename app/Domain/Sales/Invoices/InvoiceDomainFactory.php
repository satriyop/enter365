<?php

declare(strict_types=1);

namespace App\Domain\Sales\Invoices;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Sales\InvoiceCalculatorInterface;
use App\Models\Sales\Invoice;

/**
 * Factory for creating invoice domain objects with proper dependency injection.
 *
 * Centralizes domain object creation for Invoice, providing a single entry point
 * for state machine access, total calculations, and payment status checks.
 *
 * @example
 * // In a service:
 * public function __construct(private InvoiceDomainFactory $domainFactory) {}
 *
 * public function sendInvoice(Invoice $invoice): Invoice
 * {
 *     $stateMachine = $this->domainFactory->stateMachine($invoice);
 *     if (!$stateMachine->canPost()) {
 *         throw new InvalidArgumentException('Cannot send invoice');
 *     }
 *     $stateMachine->transitionTo(DocumentStatus::Sent);
 * }
 */
class InvoiceDomainFactory
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private InvoiceCalculatorInterface $calculator,
    ) {}

    /**
     * Create a state machine for the given invoice.
     *
     * The state machine is created fresh each time since it holds
     * invoice-specific state.
     */
    public function stateMachine(Invoice $invoice): InvoiceStateMachine
    {
        return InvoiceStateMachine::fromInvoice($invoice, $this->eventDispatcher);
    }

    /**
     * Calculate totals for an invoice.
     *
     * @return InvoiceTotals The calculated totals DTO
     */
    public function calculateTotals(Invoice $invoice): InvoiceTotals
    {
        $lineTotals = $invoice->items->pluck('line_total')->toArray();

        return $this->calculator->calculate(
            $lineTotals,
            (float) ($invoice->tax_rate ?? 0),
            (int) ($invoice->discount_amount ?? 0),
            $invoice->currency ?? 'IDR',
            (float) ($invoice->exchange_rate ?? 1)
        );
    }

    /**
     * Apply calculated totals to an invoice (without saving).
     *
     * Updates the invoice's financial fields based on line items.
     */
    public function applyTotals(Invoice $invoice): Invoice
    {
        $totals = $this->calculateTotals($invoice);

        $invoice->subtotal = $totals->subtotal;
        $invoice->tax_amount = $totals->taxAmount;
        $invoice->total_amount = $totals->totalAmount;

        // Calculate base currency total if multi-currency
        if ($invoice->currency !== 'IDR' && (float) $invoice->exchange_rate > 0) {
            $invoice->base_currency_total = (int) round($invoice->total_amount * (float) $invoice->exchange_rate);
        } else {
            $invoice->base_currency_total = $invoice->total_amount;
        }

        return $invoice;
    }

    /**
     * Calculate outstanding amount for an invoice.
     */
    public function calculateOutstanding(Invoice $invoice): int
    {
        return $invoice->total_amount - $invoice->paid_amount;
    }

    /**
     * Check if invoice is fully paid.
     */
    public function isFullyPaid(Invoice $invoice): bool
    {
        return $this->calculateOutstanding($invoice) <= 0;
    }

    /**
     * Check if early payment discount is still available.
     */
    public function hasEarlyPaymentDiscount(Invoice $invoice): bool
    {
        $deadline = $invoice->early_discount_deadline;

        return $invoice->early_discount_percent > 0
            && $deadline instanceof \Carbon\Carbon
            && $deadline->isFuture();
    }

    /**
     * Calculate early payment discount amount.
     */
    public function calculateEarlyDiscountAmount(Invoice $invoice): int
    {
        if (! $this->hasEarlyPaymentDiscount($invoice)) {
            return 0;
        }

        return (int) round($invoice->total_amount * ($invoice->early_discount_percent / 100));
    }

    /**
     * Get the discounted total if paid early.
     */
    public function getEarlyPaymentTotal(Invoice $invoice): int
    {
        return $invoice->total_amount - $this->calculateEarlyDiscountAmount($invoice);
    }

    /**
     * Check if invoice is overdue.
     */
    public function isOverdue(Invoice $invoice): bool
    {
        $dueDate = $invoice->due_date;
        $status = $invoice->status;
        $statusValue = $status instanceof \App\Enums\DocumentStatus ? $status->value : (string) $status;

        return $dueDate instanceof \Carbon\Carbon
            && $dueDate->isPast()
            && ! $invoice->isFullyPaid()
            && ! in_array($statusValue, ['paid', 'cancelled', 'draft'], true);
    }

    /**
     * Get days overdue (0 if not overdue).
     */
    public function getDaysOverdue(Invoice $invoice): int
    {
        $dueDate = $invoice->due_date;

        if (! $this->isOverdue($invoice) || ! $dueDate instanceof \Carbon\Carbon) {
            return 0;
        }

        return (int) $dueDate->diffInDays(now());
    }

    /**
     * Get days until due (0 if past due).
     */
    public function getDaysUntilDue(Invoice $invoice): int
    {
        $dueDate = $invoice->due_date;

        if (! $dueDate instanceof \Carbon\Carbon || $dueDate->isPast()) {
            return 0;
        }

        return (int) now()->diffInDays($dueDate);
    }
}
