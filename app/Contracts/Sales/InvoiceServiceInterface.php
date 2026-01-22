<?php

declare(strict_types=1);

namespace App\Contracts\Sales;

use App\Models\Sales\Invoice;
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
     *
     * @throws \App\Exceptions\Domain\StateTransitionException If invoice cannot be posted
     */
    public function post(Invoice $invoice): Invoice;

    /**
     * Void/cancel a posted invoice.
     *
     *
     * @throws \App\Exceptions\Domain\StateTransitionException If invoice cannot be voided
     */
    public function void(Invoice $invoice, string $reason): Invoice;

    /**
     * Mark invoice as fully paid.
     *
     *
     * @throws \App\Exceptions\Domain\StateTransitionException If invoice cannot be marked as paid
     */
    public function markAsPaid(Invoice $invoice): Invoice;

    /**
     * Mark invoice as partially paid.
     *
     *
     * @throws \App\Exceptions\Domain\StateTransitionException If invoice cannot be marked as partial
     */
    public function markAsPartial(Invoice $invoice): Invoice;

    /**
     * Mark invoice as overdue.
     *
     *
     * @throws \App\Exceptions\Domain\StateTransitionException If invoice cannot be marked as overdue
     */
    public function markAsOverdue(Invoice $invoice): Invoice;

    /**
     * Update invoice payment status based on current paid amount.
     *
     * Automatically determines the correct status based on payment state.
     */
    public function updatePaymentStatus(Invoice $invoice): Invoice;
}
