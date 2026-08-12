<?php

declare(strict_types=1);

namespace App\Contracts\Tax;

use App\Models\Sales\Invoice;
use App\Models\Tax\NsfpRange;

interface NsfpServiceInterface
{
    /**
     * Check if NSFP feature is enabled.
     */
    public function isEnabled(): bool;

    /**
     * Allocate the next NSFP number to an invoice.
     *
     * Must be called within the invoice post() transaction
     * to ensure gap-free numbering on rollback.
     *
     * @throws \App\Exceptions\Domain\BusinessRuleException
     */
    public function allocate(Invoice $invoice): string;

    /**
     * Create a new NSFP range from e-Nofa import.
     *
     * @param  array{
     *     transaction_code: string,
     *     branch_code: string,
     *     year_code: string,
     *     range_start: int,
     *     range_end: int,
     *     description?: string|null
     * }  $data
     */
    public function createRange(array $data, ?int $createdBy = null): NsfpRange;

    /**
     * Update mutable fields on an NSFP range.
     *
     * @param  array{description?: string|null, is_active?: bool}  $data
     */
    public function updateRange(NsfpRange $range, array $data): NsfpRange;

    /**
     * Deactivate an NSFP range.
     */
    public function deactivateRange(NsfpRange $range): NsfpRange;
}
