<?php

declare(strict_types=1);

namespace App\Contracts\Purchasing;

use App\Models\Purchasing\Bill;
use App\Support\Results\ServiceResult;
use Illuminate\Database\Eloquent\Model;

/**
 * Interface for Bill service operations.
 *
 * Handles bill CRUD operations and bill-specific
 * operations like posting to journal.
 */
interface BillServiceInterface
{
    /**
     * Create a new bill.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Bill;

    /**
     * Update an existing bill.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Model $document, array $data): Bill;

    /**
     * Delete a bill.
     */
    public function delete(Model $document): bool;

    /**
     * Post a bill to the journal (create accounting entry).
     *
     * @throws \InvalidArgumentException If bill is not in draft status
     */
    public function post(Bill $bill): Bill;

    /**
     * Mark bill as fully paid.
     *
     * @return ServiceResult<Bill>
     *
     * @throws \App\Exceptions\Domain\StateTransitionException If bill cannot be marked as paid
     */
    public function markAsPaid(Bill $bill): ServiceResult;

    /**
     * Post a bill to journal.
     *
     *
     * @throws \App\Exceptions\Domain\StateTransitionException If bill cannot be posted
     */
    public function post(Bill $bill): Bill;

    /**
     * Void/cancel a posted bill.
     *
     *
     * @throws \App\Exceptions\Domain\StateTransitionException If bill cannot be voided
     */
    public function void(Bill $bill, string $reason): Bill;
     *
     *
     * @throws \App\Exceptions\Domain\StateTransitionException If bill cannot be marked as paid
     */
    public function markAsPaid(Bill $bill): Bill;

    /**
     * Mark bill as partially paid.
     *
     * * @return Bill
     *
     * ✅ Phase 2.2: Updated to return Bill instead of ServiceResult<Bill>
     *
     * @throws \App\Exceptions\Domain\StateTransitionException If bill cannot be marked as partial
     */
    public function markAsPartial(Bill $bill): Bill;

    /**
     * Mark bill as overdue.
     *
     * @return ServiceResult<Bill>
     *
     * @throws \App\Exceptions\Domain\StateTransitionException If bill cannot be marked as overdue
     */
    public function markAsOverdue(Bill $bill): ServiceResult;

    /**
     * Update bill payment status based on current paid amount.
     *
     * Automatically determines the correct status based on payment state.
     *
     * @return ServiceResult<Bill>
     */
    public function updatePaymentStatus(Bill $bill): ServiceResult;
}
