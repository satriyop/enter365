<?php

declare(strict_types=1);

namespace App\Contracts\Accounting\Strategies;

use App\Models\Accounting\JournalEntry;
use App\Models\Purchasing\PurchaseReturn;
use App\Models\Sales\SalesReturn;

/**
 * Strategy for return (sales/purchase) accounting.
 *
 * Implementations determine whether returns create journal entries:
 * - Full journal: Create reversal entries for AR/AP + Tax
 * - Inventory only: Only update inventory, no journals
 */
interface ReturnAccountingStrategy
{
    /**
     * Handle journal entry when sales return is approved.
     *
     * Full journal:
     *   Dr Sales Returns (contra revenue)
     *   Dr PPN Keluaran (reverse tax)
     *   Cr Accounts Receivable
     *
     * Inventory only: No journal
     */
    public function onSalesReturnApprove(SalesReturn $salesReturn): ?JournalEntry;

    /**
     * Handle journal entry when purchase return is approved.
     *
     * Full journal:
     *   Dr Accounts Payable
     *   Cr Purchase Returns (contra expense)
     *   Cr PPN Masukan (reverse tax)
     *
     * Inventory only: No journal
     */
    public function onPurchaseReturnApprove(PurchaseReturn $purchaseReturn): ?JournalEntry;

    /**
     * Get the strategy identifier.
     */
    public function getIdentifier(): string;
}
