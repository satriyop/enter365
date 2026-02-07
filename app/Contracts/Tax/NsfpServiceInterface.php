<?php

declare(strict_types=1);

namespace App\Contracts\Tax;

use App\Models\Sales\Invoice;

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
}
