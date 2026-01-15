<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Enums\DocumentStatus;
use App\Models\Sales\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * Service for invoice payment status management.
 *
 * Handles payment status transitions, overdue marking,
 * and early payment discount calculations.
 */
class InvoicePaymentService
{
    /**
     * Update payment status based on paid amount.
     */
    public function updatePaymentStatus(Invoice $invoice): Invoice
    {
        if ($invoice->status === DocumentStatus::Cancelled) {
            return $invoice;
        }

        if ($invoice->paid_amount >= $invoice->total_amount) {
            $invoice->status = DocumentStatus::Paid;
        } elseif ($invoice->paid_amount > 0) {
            $invoice->status = DocumentStatus::Partial;
        } elseif ($invoice->due_date < now() && $invoice->status !== DocumentStatus::Draft) {
            $invoice->status = DocumentStatus::Overdue;
        }

        $invoice->save();

        return $invoice->fresh();
    }

    /**
     * Record a payment and update status.
     */
    public function recordPayment(Invoice $invoice, int $amount): Invoice
    {
        return DB::transaction(function () use ($invoice, $amount) {
            $invoice->paid_amount += $amount;
            $invoice->save();

            return $this->updatePaymentStatus($invoice);
        });
    }

    /**
     * Reverse a payment and update status.
     */
    public function reversePayment(Invoice $invoice, int $amount): Invoice
    {
        return DB::transaction(function () use ($invoice, $amount) {
            $invoice->paid_amount = max(0, $invoice->paid_amount - $amount);
            $invoice->save();

            return $this->updatePaymentStatus($invoice);
        });
    }

    /**
     * Mark invoice as overdue if applicable.
     */
    public function markAsOverdue(Invoice $invoice): bool
    {
        if ($invoice->status === DocumentStatus::Paid || $invoice->status === DocumentStatus::Cancelled) {
            return false;
        }

        if ($invoice->status === DocumentStatus::Draft) {
            return false;
        }

        $invoice->status = DocumentStatus::Overdue;
        $invoice->save();

        return true;
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
     * @return array{total: int, paid: int, outstanding: int, status: string, is_overdue: bool, days_overdue: int, early_discount_available: bool, early_discount_amount: int}
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
