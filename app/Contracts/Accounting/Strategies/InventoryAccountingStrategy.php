<?php

declare(strict_types=1);

namespace App\Contracts\Accounting\Strategies;

use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\StockOpname;
use App\Models\Purchasing\GoodsReceiptNote;
use App\Models\Sales\DeliveryOrder;

/**
 * Strategy for inventory accounting journal entries.
 *
 * Implementations determine when and how inventory movements
 * create journal entries (perpetual, periodic, or hybrid).
 */
interface InventoryAccountingStrategy
{
    /**
     * Handle journal entry when goods are received (GRN confirmed).
     *
     * Perpetual: Dr Inventory, Cr Goods Received Not Invoiced / AP
     * Periodic: No journal (quantity tracking only)
     * Hybrid: No journal (wait for bill posting)
     */
    public function onGoodsReceived(GoodsReceiptNote $grn): ?JournalEntry;

    /**
     * Handle journal entry when goods are shipped (DO shipped).
     *
     * Perpetual: Dr COGS, Cr Inventory
     * Periodic: No journal
     * Hybrid: No journal (COGS via COGSRecognitionStrategy)
     */
    public function onGoodsShipped(DeliveryOrder $deliveryOrder): ?JournalEntry;

    /**
     * Handle journal entry for stock adjustments (Stock Opname).
     *
     * All methods: Dr/Cr Inventory, Dr/Cr Inventory Adjustment
     */
    public function onStockAdjustment(StockOpname $stockOpname): ?JournalEntry;

    /**
     * Get the strategy identifier.
     */
    public function getIdentifier(): string;
}
