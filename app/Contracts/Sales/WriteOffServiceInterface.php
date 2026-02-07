<?php

declare(strict_types=1);

namespace App\Contracts\Sales;

use App\Models\Sales\Invoice;
use App\Models\Sales\WriteOff;

/**
 * Write-Off Service Interface.
 *
 * Handles writing off uncollectible receivables:
 * - Creates write-off record with JE (Dr Bad Debt Expense / Cr AR)
 * - Updates invoice paid_amount so status transitions naturally
 * - Supports reversal to restore original state
 */
interface WriteOffServiceInterface
{
    /**
     * Write off an invoice (fully or partially).
     *
     * @param  array{
     *     amount: int,
     *     reason: string,
     *     write_off_date?: string,
     * } $data
     */
    public function writeOff(Invoice $invoice, array $data): WriteOff;

    /**
     * Reverse a write-off, restoring the invoice's paid_amount.
     */
    public function reverse(WriteOff $writeOff, ?string $reason = null): WriteOff;
}
