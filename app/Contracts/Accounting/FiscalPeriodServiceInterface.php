<?php

declare(strict_types=1);

namespace App\Contracts\Accounting;

use App\Models\Accounting\FiscalPeriod;

/**
 * Interface for Fiscal Period management operations.
 */
interface FiscalPeriodServiceInterface
{
    /**
     * Create a new fiscal period.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): FiscalPeriod;

    /**
     * Close a fiscal period.
     *
     * @return array<string, mixed>
     */
    public function closePeriod(FiscalPeriod $period, ?string $notes = null): array;

    /**
     * Reopen a closed fiscal period.
     */
    public function reopenPeriod(FiscalPeriod $period): bool;

    /**
     * Get the closing checklist as an array.
     *
     * @return array<string, mixed>
     */
    public function getClosingChecklistArray(FiscalPeriod $period): array;
}
