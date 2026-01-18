<?php

declare(strict_types=1);

namespace App\Services\Accounting\Strategies\Inventory;

use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Accounting\Strategies\InventoryAccountingStrategy;
use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\StockOpname;
use App\Models\Purchasing\GoodsReceiptNote;
use App\Models\Sales\DeliveryOrder;

/**
 * Periodic inventory accounting strategy.
 *
 * No automatic journal entries on inventory movements.
 * Inventory is adjusted at period end only.
 */
class PeriodicInventoryStrategy implements InventoryAccountingStrategy
{
    public function __construct(
        private JournalServiceInterface $journalService
    ) {}

    public function onGoodsReceived(GoodsReceiptNote $grn): ?JournalEntry
    {
        // Periodic method: No journal on goods receipt
        return null;
    }

    public function onGoodsShipped(DeliveryOrder $deliveryOrder): ?JournalEntry
    {
        // Periodic method: No journal on delivery
        return null;
    }

    public function onStockAdjustment(StockOpname $stockOpname): ?JournalEntry
    {
        // Stock adjustments always create journals
        return (new HybridInventoryStrategy($this->journalService))
            ->onStockAdjustment($stockOpname);
    }

    public function getIdentifier(): string
    {
        return 'periodic';
    }
}
