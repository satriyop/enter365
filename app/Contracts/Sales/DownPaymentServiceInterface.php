<?php

declare(strict_types=1);

namespace App\Contracts\Sales;

use App\Models\Purchasing\Bill;
use App\Models\Sales\DownPayment;
use App\Models\Sales\DownPaymentApplication;
use App\Models\Sales\Invoice;
use App\Models\Shared\Payment;
use Illuminate\Database\Eloquent\Collection;

/**
 * Interface for Down Payment service operations.
 */
interface DownPaymentServiceInterface
{
    /**
     * Create a new down payment.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): DownPayment;

    /**
     * Update a down payment.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(DownPayment $downPayment, array $data): DownPayment;

    /**
     * Delete a down payment.
     */
    public function delete(DownPayment $downPayment): bool;

    /**
     * Apply down payment to an invoice.
     *
     * @param  array<string, mixed>  $data
     */
    public function applyToInvoice(DownPayment $downPayment, Invoice $invoice, array $data): DownPaymentApplication;

    /**
     * Apply down payment to a bill.
     *
     * @param  array<string, mixed>  $data
     */
    public function applyToBill(DownPayment $downPayment, Bill $bill, array $data): DownPaymentApplication;

    /**
     * Unapply (reverse) a down payment application.
     */
    public function unapply(DownPaymentApplication $application): bool;

    /**
     * Refund remaining down payment balance.
     *
     * @param  array<string, mixed>  $data
     */
    public function refund(DownPayment $downPayment, array $data): Payment;

    /**
     * Cancel a down payment.
     */
    public function cancel(DownPayment $downPayment, ?string $reason = null): DownPayment;

    /**
     * Get available down payments for a contact.
     *
     * @return Collection<int, DownPayment>
     */
    public function getAvailableForContact(int $contactId, string $type): Collection;
}
