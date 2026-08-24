<?php

declare(strict_types=1);

namespace App\Services\Accounting\Strategies\Inventory;

use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Accounting\Strategies\InventoryAccountingStrategy;
use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\ProductStock;
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
        private JournalServiceInterface $journalService,
        private HybridInventoryStrategy $hybridStrategy
    ) {}

    /**
     * Create inventory journal entry on goods receipt.
     *
     * Dr Inventory (1-1400)
     * Cr Goods Received Not Invoiced (2-1300)
     */
    public function onGoodsReceived(GoodsReceiptNote $grn): ?JournalEntry
    {
        $grn->loadMissing(['items.product']);

        $totalValue = 0;

        foreach ($grn->items as $item) {
            if (! $item->product_id) {
                continue;
            }

            $product = $item->product;

            // Only track inventory items
            if (! $product || ! $product->track_inventory) {
                continue;
            }

            $totalValue += $item->inventoryTotalCost();
        }

        if ($totalValue <= 0) {
            return null;
        }

        $inventoryAccount = config('accounting.default_accounts.inventory', '1-1400');
        $grniAccount = config('accounting.default_accounts.goods_received_not_invoiced', '2-1300');

        $lines = [
            [
                'account_code' => $inventoryAccount,
                'debit' => $totalValue,
                'credit' => 0,
                'description' => "Penerimaan barang: {$grn->grn_number}",
            ],
            [
                'account_code' => $grniAccount,
                'debit' => 0,
                'credit' => $totalValue,
                'description' => "GRNI: {$grn->grn_number}",
            ],
        ];

        $entryDate = $grn->receipt_date;

        return $this->journalService->createEntry([
            'entry_date' => $entryDate instanceof \DateTimeInterface ? $entryDate->format('Y-m-d') : $entryDate,
            'description' => "Penerimaan persediaan: {$grn->grn_number}",
            'reference' => $grn->grn_number,
            'source_type' => GoodsReceiptNote::class,
            'source_id' => $grn->id,
            'lines' => $lines,
        ], autoPost: true);
    }

    /**
     * Create COGS journal entry on goods shipment.
     *
     * Dr COGS (5-1001)
     * Cr Inventory (1-1400)
     */
    public function onGoodsShipped(DeliveryOrder $deliveryOrder): ?JournalEntry
    {
        $deliveryOrder->loadMissing(['items.product']);

        $cogsAmount = 0;

        foreach ($deliveryOrder->items as $item) {
            if (! $item->product_id) {
                continue;
            }

            $product = $item->product;

            // Skip services and non-inventory items
            if (! $product || ! $product->track_inventory) {
                continue;
            }

            // Use weighted average cost from inventory
            $unitCost = ProductStock::where('product_id', $product->id)
                ->where('warehouse_id', $deliveryOrder->warehouse_id)
                ->where('average_cost', '>', 0)
                ->value('average_cost') ?? $product->purchase_price ?? 0;
            $cogsAmount += (int) round($item->quantity * $unitCost);
        }

        if ($cogsAmount <= 0) {
            return null;
        }

        $cogsAccount = config('accounting.default_accounts.cogs', '5-1001');
        $inventoryAccount = config('accounting.default_accounts.inventory', '1-1400');

        $lines = [
            [
                'account_code' => $cogsAccount,
                'debit' => $cogsAmount,
                'credit' => 0,
                'description' => "HPP Pengiriman: {$deliveryOrder->do_number}",
            ],
            [
                'account_code' => $inventoryAccount,
                'debit' => 0,
                'credit' => $cogsAmount,
                'description' => "Pengurangan persediaan: {$deliveryOrder->do_number}",
            ],
        ];

        $entryDate = $deliveryOrder->shipping_date ?? $deliveryOrder->do_date;

        return $this->journalService->createEntry([
            'entry_date' => $entryDate instanceof \DateTimeInterface ? $entryDate->format('Y-m-d') : $entryDate,
            'description' => "Harga Pokok Pengiriman: {$deliveryOrder->do_number}",
            'reference' => $deliveryOrder->do_number,
            'source_type' => DeliveryOrder::class,
            'source_id' => $deliveryOrder->id,
            'lines' => $lines,
        ], autoPost: true);
    }

    public function onStockAdjustment(StockOpname $stockOpname): ?JournalEntry
    {
        // Stock adjustments always create journals - delegate to hybrid strategy
        return $this->hybridStrategy->onStockAdjustment($stockOpname);
    }

    public function getIdentifier(): string
    {
        return 'perpetual';
    }
}
