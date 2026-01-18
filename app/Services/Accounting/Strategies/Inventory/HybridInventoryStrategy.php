<?php

declare(strict_types=1);

namespace App\Services\Accounting\Strategies\Inventory;

use App\Contracts\Accounting\Strategies\InventoryAccountingStrategy;
use App\Contracts\Services\Accounting\JournalServiceInterface;
use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\StockOpname;
use App\Models\Purchasing\GoodsReceiptNote;
use App\Models\Sales\DeliveryOrder;

/**
 * Hybrid inventory accounting strategy.
 *
 * - GRN: No journal (wait for bill posting)
 * - DO Ship: No journal (COGS via COGSRecognitionStrategy on invoice post)
 * - Stock Opname: Creates adjustment journal
 *
 * Recommended for EPC/Solar businesses where delivery ≠ revenue recognition.
 */
class HybridInventoryStrategy implements InventoryAccountingStrategy
{
    public function __construct(
        private JournalServiceInterface $journalService
    ) {}

    /**
     * No journal on GRN - inventory is tracked by quantity only.
     * The bill posting will handle the expense/AP entry.
     */
    public function onGoodsReceived(GoodsReceiptNote $grn): ?JournalEntry
    {
        // Hybrid method: No journal entry on goods receipt
        // Inventory quantity is tracked separately via InventoryService
        // The bill posting creates: Dr Expense/Inventory, Cr AP
        return null;
    }

    /**
     * No journal on delivery - COGS is recognized when invoice is posted.
     */
    public function onGoodsShipped(DeliveryOrder $deliveryOrder): ?JournalEntry
    {
        // Hybrid method: No journal entry on delivery
        // Inventory quantity is reduced via InventoryService
        // COGS is recognized via COGSRecognitionStrategy::onInvoicePost()
        return null;
    }

    /**
     * Stock adjustments always create journal entries.
     *
     * Positive adjustment (found more): Dr Inventory, Cr Inventory Adjustment
     * Negative adjustment (shrinkage): Dr Inventory Adjustment, Cr Inventory
     */
    public function onStockAdjustment(StockOpname $stockOpname): ?JournalEntry
    {
        $adjustmentValue = $this->calculateAdjustmentValue($stockOpname);

        if ($adjustmentValue === 0) {
            return null;
        }

        $inventoryAccount = config('accounting.default_accounts.inventory', '1-1400');
        $adjustmentAccount = config('accounting.default_accounts.inventory_adjustment', '5-2900');

        $lines = [];

        if ($adjustmentValue > 0) {
            // Found more inventory than expected
            $lines[] = [
                'account_code' => $inventoryAccount,
                'debit' => $adjustmentValue,
                'credit' => 0,
                'description' => "Penyesuaian stok: {$stockOpname->opname_number}",
            ];
            $lines[] = [
                'account_code' => $adjustmentAccount,
                'debit' => 0,
                'credit' => $adjustmentValue,
                'description' => "Penyesuaian stok: {$stockOpname->opname_number}",
            ];
        } else {
            // Shrinkage - less inventory than expected
            $absValue = abs($adjustmentValue);
            $lines[] = [
                'account_code' => $adjustmentAccount,
                'debit' => $absValue,
                'credit' => 0,
                'description' => "Penyesuaian stok (selisih kurang): {$stockOpname->opname_number}",
            ];
            $lines[] = [
                'account_code' => $inventoryAccount,
                'debit' => 0,
                'credit' => $absValue,
                'description' => "Penyesuaian stok (selisih kurang): {$stockOpname->opname_number}",
            ];
        }

        $entryDate = $stockOpname->opname_date;

        return $this->journalService->createEntry([
            'entry_date' => $entryDate instanceof \DateTimeInterface ? $entryDate->format('Y-m-d') : $entryDate,
            'description' => "Penyesuaian stok opname: {$stockOpname->opname_number}",
            'reference' => $stockOpname->opname_number,
            'source_type' => StockOpname::class,
            'source_id' => $stockOpname->id,
            'lines' => $lines,
        ], autoPost: true);
    }

    public function getIdentifier(): string
    {
        return 'hybrid';
    }

    /**
     * Calculate the total adjustment value from stock opname items.
     *
     * Uses the pre-calculated variance_value from items, which is computed as:
     * (counted_quantity - system_quantity) * system_cost
     */
    private function calculateAdjustmentValue(StockOpname $stockOpname): int
    {
        $stockOpname->loadMissing('items');

        // Sum the pre-calculated variance values
        // Positive = found more inventory, Negative = shrinkage
        return (int) $stockOpname->items->sum('variance_value');
    }
}
