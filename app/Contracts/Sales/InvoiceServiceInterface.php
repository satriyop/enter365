<?php

declare(strict_types=1);

namespace App\Contracts\Sales;

use App\Models\Sales\Invoice;
use App\Support\Results\ServiceResult;
use Illuminate\Database\Eloquent\Model;

/**
 * Interface for Invoice service operations.
 *
 * Handles invoice CRUD operations and invoice-specific
 * operations like posting to journal.
 */
interface InvoiceServiceInterface
{
    /**
     * Create a new invoice.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Invoice;

    /**
     * Update an existing invoice.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Model $document, array $data): Invoice;

    /**
     * Delete an invoice.
     */
    public function delete(Model $document): bool;

    /**
     * Post an invoice to the journal (create accounting entry).
     *
     * @return ServiceResult<Invoice>
     *
     * @throws \App\Exceptions\Domain\StateTransitionException If invoice cannot be posted
     */
    public function post(Invoice $invoice): ServiceResult;

    /**
     * Void/cancel a posted invoice.
     *
     * @return ServiceResult<Invoice>
     *
     * @throws \App\Exceptions\Domain\StateTransitionException If invoice cannot be voided
     */
    public function void(Invoice $invoice, string $reason): ServiceResult;

    /**
     * Mark invoice as fully paid.
     *
     * @return ServiceResult<Invoice>
     *
     * @throws \App\Exceptions\Domain\StateTransitionException If invoice cannot be marked as paid
     */
    public function markAsPaid(Invoice $invoice): ServiceResult;

    /**
     * Mark invoice as partially paid.
     *
     * @return ServiceResult<Invoice>
     *
     * @throws \App\Exceptions\Domain\StateTransitionException If invoice cannot be marked as partial
     */
    public function markAsPartial(Invoice $invoice): ServiceResult;

    /**
     * Mark invoice as overdue.
     *
     * @return ServiceResult<Invoice>
     *
     * @throws \App\Exceptions\Domain\StateTransitionException If invoice cannot be marked as overdue
     */
    public function markAsOverdue(Invoice $invoice): ServiceResult;

    /**
     * Update invoice payment status based on current paid amount.
     *
     * Automatically determines the correct status based on payment state.
     *
     * @return ServiceResult<Invoice>
     */
    public function updatePaymentStatus(Invoice $invoice): ServiceResult;
}
