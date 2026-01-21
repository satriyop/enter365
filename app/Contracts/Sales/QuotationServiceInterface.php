<?php

declare(strict_types=1);

namespace App\Contracts\Sales;

use App\Models\Sales\Invoice;
use App\Models\Sales\Quotation;

/**
 * Interface for Quotation service operations.
 */
interface QuotationServiceInterface
{
    /**
     * Create a new quotation with items.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Quotation;

    /**
     * Create a quotation from a BOM.
     *
     * @param  array<string, mixed>  $data
     */
    public function createFromBom(array $data): Quotation;

    /**
     * Update an existing quotation.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Quotation $quotation, array $data): Quotation;

    /**
     * Submit quotation for approval.
     */
    public function submit(Quotation $quotation, ?int $userId = null): Quotation;

    /**
     * Approve a submitted quotation.
     */
    public function approve(Quotation $quotation, ?int $userId = null): Quotation;

    /**
     * Reject a submitted quotation.
     */
    public function reject(Quotation $quotation, string $reason, ?int $userId = null): Quotation;

    /**
     * Cancel a quotation.
     *
     * Cancels a Draft, Submitted, or Approved quotation.
     * Cancelled quotations can be revised to create a new draft.
     */
    public function cancel(Quotation $quotation, ?string $reason = null, ?int $userId = null): Quotation;

    /**
     * Revise a quotation (create a new version).
     */
    public function revise(Quotation $quotation): Quotation;

    /**
     * Convert quotation to invoice.
     */
    public function convertToInvoice(Quotation $quotation): Invoice;

    /**
     * Duplicate a quotation.
     */
    public function duplicate(Quotation $quotation): Quotation;

    /**
     * Mark expired quotations.
     *
     * @return int Number of quotations marked as expired
     */
    public function markExpired(): int;

    /**
     * Mark quotation as sent to customer.
     *
     * Only approved quotations can be marked as sent.
     * Sent quotations will NOT auto-expire when past valid_until date.
     *
     * @param  string|null  $email  Customer email to send to (defaults to contact's email)
     * @param  string|null  $via  How it was sent: 'email', 'print', 'portal'
     */
    public function markAsSent(
        Quotation $quotation,
        ?string $email = null,
        ?string $via = 'email'
    ): Quotation;

    /**
     * Get quotation statistics.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(?string $startDate = null, ?string $endDate = null): array;
}
