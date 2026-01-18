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
 * Perpetual inventory accounting strategy.
 *
 * Creates journal entries on every inventory movement:
 * - GRN: Dr Inventory, Cr Goods Received Not Invoiced
 * - DO Ship: Dr COGS, Cr Inventory
 */
class PerpetualInventoryStrategy implements InventoryAccountingStrategy
{
    public function __construct(
        private JournalServiceInterface $journalService
    ) {}

    public function onGoodsReceived(GoodsReceiptNote $grn): ?JournalEntry
    {
        // TODO: Implement perpetual inventory on GRN
        // Dr Inventory (1-1400)
        // Cr Goods Received Not Invoiced (2-1300)
        return null;
    }

    public function onGoodsShipped(DeliveryOrder $deliveryOrder): ?JournalEntry
    {
        // TODO: Implement perpetual COGS on delivery
        // Dr COGS (5-1001)
        // Cr Inventory (1-1400)
        return null;
    }

    public function onStockAdjustment(StockOpname $stockOpname): ?JournalEntry
    {
        // Same as hybrid - stock adjustments always create journals
        return (new HybridInventoryStrategy($this->journalService))
            ->onStockAdjustment($stockOpname);
    }

    public function getIdentifier(): string
    {
        return 'perpetual';
    }
}
