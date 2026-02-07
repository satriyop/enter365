<?php

declare(strict_types=1);

namespace App\Services\Accounting\Strategies\COGS;

use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Accounting\Strategies\COGSRecognitionStrategy;
use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\ProductStock;
use App\Models\Sales\DeliveryOrder;
use App\Models\Sales\Invoice;

/**
 * COGS recognition on invoice post strategy.
 *
 * Recognizes COGS when invoice is posted, matching revenue recognition.
 * Recommended for businesses where delivery timing differs from billing.
 */
class COGSOnInvoiceStrategy implements COGSRecognitionStrategy
{
    public function __construct(
        private JournalServiceInterface $journalService
    ) {}

    /**
     * Create COGS journal entry when invoice is posted.
     *
     * Dr COGS (5-1001)
     * Cr Inventory (1-1400)
     */
    public function onInvoicePost(Invoice $invoice): ?JournalEntry
    {
        $invoice->loadMissing(['items.product']);

        $cogsAmount = $this->calculateCOGS($invoice);

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
                'description' => "HPP Invoice: {$invoice->invoice_number}",
            ],
            [
                'account_code' => $inventoryAccount,
                'debit' => 0,
                'credit' => $cogsAmount,
                'description' => "Pengurangan persediaan: {$invoice->invoice_number}",
            ],
        ];

        return $this->journalService->createEntry([
            'entry_date' => $invoice->invoice_date->toDateString(),
            'description' => "Harga Pokok Penjualan: {$invoice->invoice_number}",
            'reference' => $invoice->invoice_number,
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
            'lines' => $lines,
        ], autoPost: true);
    }

    /**
     * No action on delivery - COGS is recognized on invoice post.
     */
    public function onDeliveryShip(DeliveryOrder $deliveryOrder): ?JournalEntry
    {
        return null;
    }

    /**
     * Calculate COGS for invoice based on average cost.
     */
    public function calculateCOGS(Invoice $invoice): int
    {
        $totalCOGS = 0;

        foreach ($invoice->items as $item) {
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
                ->where('average_cost', '>', 0)
                ->value('average_cost') ?? $product->purchase_price ?? 0;
            $totalCOGS += (int) round($item->quantity * $unitCost);
        }

        return (int) $totalCOGS;
    }

    public function getIdentifier(): string
    {
        return 'on_invoice';
    }
}
