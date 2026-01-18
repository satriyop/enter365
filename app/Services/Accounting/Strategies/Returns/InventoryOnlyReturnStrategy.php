<?php

declare(strict_types=1);

namespace App\Services\Accounting\Strategies\Returns;

use App\Contracts\Accounting\Strategies\ReturnAccountingStrategy;
use App\Models\Accounting\JournalEntry;
use App\Models\Purchasing\PurchaseReturn;
use App\Models\Sales\SalesReturn;

/**
 * Inventory-only return strategy.
 *
 * Only updates inventory quantities, no journal entries.
 * Use when journal entries are handled manually or through other processes.
 */
class InventoryOnlyReturnStrategy implements ReturnAccountingStrategy
{
    public function onSalesReturnApprove(SalesReturn $salesReturn): ?JournalEntry
    {
        // No journal entry - inventory is updated by the service
        return null;
    }

    public function onPurchaseReturnApprove(PurchaseReturn $purchaseReturn): ?JournalEntry
    {
        // No journal entry - inventory is updated by the service
        return null;
    }

    public function getIdentifier(): string
    {
        return 'inventory_only';
    }
}
