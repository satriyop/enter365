<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Sales\InvoiceServiceInterface;
use App\Enums\DocumentStatus;
use App\Models\Sales\Invoice;
use App\Services\Base\AbstractApplicationService;

/**
 * Service for invoice payment status management.
 *
 * Handles payment recording and delegates status transitions
 * to InvoiceService for proper state machine handling.
 */
class InvoicePaymentService extends AbstractApplicationService
{
    public function __construct(
        private InvoiceServiceInterface $invoiceService,
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Update payment status based on paid amount.
     *
     * Delegates to InvoiceService which uses the state machine.
     */
    public function updatePaymentStatus(Invoice $invoice): Invoice
    {
        $result = $this->invoiceService->updatePaymentStatus($invoice);

        return $result->getData() ?? $invoice->fresh();
    }

    /**
     * Record a payment and update status.
     */
    public function recordPayment(Invoice $invoice, int $amount): Invoice
    {
        return $this->executeInTransaction('record_payment', function () use ($invoice, $amount) {
            $invoice->paid_amount += $amount;
            $invoice->save();

            return $this->updatePaymentStatus($invoice);
        }, ['invoice_id' => $invoice->id, 'amount' => $amount]);
    }

    /**
     * Reverse a payment and update status.
     */
    public function reversePayment(Invoice $invoice, int $amount): Invoice
    {
        return $this->executeInTransaction('reverse_payment', function () use ($invoice, $amount) {
            $invoice->paid_amount = max(0, $invoice->paid_amount - $amount);
            $invoice->save();

            return $this->updatePaymentStatus($invoice);
        }, ['invoice_id' => $invoice->id, 'amount' => $amount]);
    }

    /**
     * Mark invoice as overdue if applicable.
     *
     * Delegates to InvoiceService which uses the state machine.
     */
    public function markAsOverdue(Invoice $invoice): bool
    {
        if ($invoice->status === DocumentStatus::Paid || $invoice->status === DocumentStatus::Cancelled) {
            return false;
        }

        if ($invoice->status === DocumentStatus::Draft) {
            return false;
        }

        $result = $this->invoiceService->markAsOverdue($invoice);

        return $result->isSuccess();
    }

    /**
     * Calculate early payment discount amount.
     */
    public function calculateEarlyDiscount(Invoice $invoice): int
    {
        if (! $invoice->hasEarlyPaymentDiscount()) {
            return 0;
        }

        return (int) round($invoice->total_amount * ($invoice->early_discount_percent / 100));
    }

    /**
     * Get the discounted total if paid early.
     */
    public function getEarlyPaymentTotal(Invoice $invoice): int
    {
        return $invoice->total_amount - $this->calculateEarlyDiscount($invoice);
    }

    /**
     * Check if invoice qualifies for early payment discount.
     */
    public function qualifiesForEarlyDiscount(Invoice $invoice): bool
    {
        return $invoice->hasEarlyPaymentDiscount();
    }

    /**
     * Get payment summary for an invoice.
     *
     * @return array{total: int, paid: int, outstanding: int, status: DocumentStatus, is_overdue: bool, days_overdue: int, early_discount_available: bool, early_discount_amount: int}
     */
    public function getPaymentSummary(Invoice $invoice): array
    {
        return [
            'total' => $invoice->total_amount,
            'paid' => $invoice->paid_amount,
            'outstanding' => $invoice->getOutstandingAmount(),
            'status' => $invoice->status,
            'is_overdue' => $invoice->isOverdue(),
            'days_overdue' => $invoice->getDaysOverdue(),
            'early_discount_available' => $invoice->hasEarlyPaymentDiscount(),
            'early_discount_amount' => $this->calculateEarlyDiscount($invoice),
        ];
    }
}
