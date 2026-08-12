<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Contracts\Sales\DownPaymentServiceInterface;
use App\Models\Purchasing\Bill;
use App\Models\Sales\DownPayment;
use App\Models\Sales\DownPaymentApplication;
use App\Models\Sales\Invoice;
use App\Models\Shared\Payment;
use App\Services\Sales\DownPayment\DownPaymentApplicationService;
use App\Services\Sales\DownPayment\DownPaymentCrudService;
use App\Services\Sales\DownPayment\DownPaymentLifecycleService;
use App\Support\OperationContext;
use Illuminate\Database\Eloquent\Collection;

/**
 * Down payment service coordinator.
 *
 * Thin coordinator that delegates to focused services:
 * - DownPaymentCrudService: create, update, delete
 * - DownPaymentApplicationService: applyToInvoice, applyToBill, unapply
 * - DownPaymentLifecycleService: refund, cancel, getAvailableForContact
 *
 * @see \App\Services\Sales\DownPayment\DownPaymentCrudService
 * @see \App\Services\Sales\DownPayment\DownPaymentApplicationService
 * @see \App\Services\Sales\DownPayment\DownPaymentLifecycleService
 */
class DownPaymentService implements DownPaymentServiceInterface
{
    public function __construct(
        private DownPaymentCrudService $crud,
        private DownPaymentApplicationService $application,
        private DownPaymentLifecycleService $lifecycle,
    ) {}

    /**
     * Set operation context for all underlying services.
     *
     * Returns a clone with context-aware services for fluent chaining.
     */
    public function withContext(OperationContext $context): static
    {
        $clone = clone $this;
        $clone->crud = $this->crud->withContext($context);
        $clone->application = $this->application->withContext($context);
        $clone->lifecycle = $this->lifecycle->withContext($context);

        return $clone;
    }

    // ─────────────────────────────────────────────────────────────
    // CRUD Operations (delegated to DownPaymentCrudService)
    // ─────────────────────────────────────────────────────────────

    /**
     * Create a new down payment.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): DownPayment
    {
        return $this->crud->create($data);
    }

    /**
     * Update a down payment (only active with no applications).
     *
     * @param  array<string, mixed>  $data
     */
    public function update(DownPayment $downPayment, array $data): DownPayment
    {
        return $this->crud->update($downPayment, $data);
    }

    /**
     * Delete a down payment (only if no applications).
     */
    public function delete(DownPayment $downPayment): bool
    {
        return $this->crud->delete($downPayment);
    }

    // ─────────────────────────────────────────────────────────────
    // Application Operations (delegated to DownPaymentApplicationService)
    // ─────────────────────────────────────────────────────────────

    /**
     * Apply down payment to an invoice.
     *
     * @param  array<string, mixed>  $data
     */
    public function applyToInvoice(DownPayment $downPayment, Invoice $invoice, array $data): DownPaymentApplication
    {
        return $this->application->applyToInvoice($downPayment, $invoice, $data);
    }

    /**
     * Apply down payment to a bill.
     *
     * @param  array<string, mixed>  $data
     */
    public function applyToBill(DownPayment $downPayment, Bill $bill, array $data): DownPaymentApplication
    {
        return $this->application->applyToBill($downPayment, $bill, $data);
    }

    /**
     * Unapply (reverse) a down payment application.
     */
    public function unapply(DownPaymentApplication $application): bool
    {
        return $this->application->unapply($application);
    }

    // ─────────────────────────────────────────────────────────────
    // Lifecycle Operations (delegated to DownPaymentLifecycleService)
    // ─────────────────────────────────────────────────────────────

    /**
     * Refund remaining down payment balance.
     *
     * @param  array<string, mixed>  $data
     */
    public function refund(DownPayment $downPayment, array $data): Payment
    {
        return $this->lifecycle->refund($downPayment, $data);
    }

    /**
     * Cancel a down payment (only if no applications).
     */
    public function cancel(DownPayment $downPayment, ?string $reason = null): DownPayment
    {
        return $this->lifecycle->cancel($downPayment, $reason);
    }

    /**
     * Get available down payments for a contact.
     *
     * @return Collection<int, DownPayment>
     */
    public function getAvailableForContact(int $contactId, string $type): Collection
    {
        return $this->lifecycle->getAvailableForContact($contactId, $type);
    }
}
