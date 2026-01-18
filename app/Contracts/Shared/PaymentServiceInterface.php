<?php

declare(strict_types=1);

namespace App\Contracts\Shared;

use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use App\Models\Shared\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Payment Service Interface.
 *
 * Handles all payment-related business logic including:
 * - Creating payments for invoices and bills
 * - Updating payable amounts and statuses
 * - Voiding payments
 * - Querying payment data
 */
interface PaymentServiceInterface
{
    /**
     * Create a payment for an invoice or bill.
     *
     * @param  array{
     *     type: string,
     *     contact_id: int,
     *     cash_account_id: int,
     *     amount: int,
     *     payment_date: string,
     *     reference?: string,
     *     notes?: string,
     *     invoice_id?: int,
     *     bill_id?: int
     * }  $data
     *
     * @throws \InvalidArgumentException When validation fails
     */
    public function create(array $data): Payment;

    /**
     * Create a payment specifically for an invoice.
     *
     * @throws \InvalidArgumentException When amount exceeds outstanding
     */
    public function createForInvoice(Invoice $invoice, array $data): Payment;

    /**
     * Create a payment specifically for a bill.
     *
     * @throws \InvalidArgumentException When amount exceeds outstanding
     */
    public function createForBill(Bill $bill, array $data): Payment;

    /**
     * Void a payment and reverse its effects.
     *
     * @throws \InvalidArgumentException When payment cannot be voided
     */
    public function void(Payment $payment, ?string $reason = null): Payment;

    /**
     * Get all payments for an invoice.
     *
     * @return Collection<int, Payment>
     */
    public function getForInvoice(Invoice $invoice): Collection;

    /**
     * Get all payments for a bill.
     *
     * @return Collection<int, Payment>
     */
    public function getForBill(Bill $bill): Collection;

    /**
     * Get the outstanding amount for a payable (invoice or bill).
     */
    public function getOutstandingAmount(Model $payable): int;

    /**
     * Check if a payable can receive a payment.
     */
    public function canReceivePayment(Model $payable): bool;
}
