<?php

declare(strict_types=1);

namespace App\Contracts\Shared;

use Illuminate\Support\Collection;

/**
 * Interface for Overdue document processing.
 */
interface OverdueServiceInterface
{
    /**
     * Mark overdue invoices.
     *
     * @return Collection<int, \App\Models\Sales\Invoice>
     */
    public function markOverdueInvoices(): Collection;

    /**
     * Mark overdue bills.
     *
     * @return Collection<int, \App\Models\Purchasing\Bill>
     */
    public function markOverdueBills(): Collection;

    /**
     * Mark all overdue documents.
     *
     * @return array<string, mixed>
     */
    public function markAllOverdue(): array;

    /**
     * Get overdue summary statistics.
     *
     * @return array<string, mixed>
     */
    public function getOverdueSummary(): array;

    /**
     * Get invoices upcoming due within given days.
     *
     * @return Collection<int, \App\Models\Sales\Invoice>
     */
    public function getUpcomingDueInvoices(int $daysAhead = 7): Collection;

    /**
     * Get bills upcoming due within given days.
     *
     * @return Collection<int, \App\Models\Purchasing\Bill>
     */
    public function getUpcomingDueBills(int $daysAhead = 7): Collection;
}
