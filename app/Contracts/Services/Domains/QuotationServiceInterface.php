<?php

declare(strict_types=1);

namespace App\Contracts\Services\Domains;

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
     */
    public function markExpired(): int;

    /**
     * Get quotation statistics.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(?string $startDate = null, ?string $endDate = null): array;
}
