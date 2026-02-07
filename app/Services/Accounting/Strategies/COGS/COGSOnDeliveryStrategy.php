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
 * COGS recognition on delivery strategy.
 *
 * Recognizes COGS when goods are shipped/delivered.
 * Used when delivery timing matches cost recognition.
 */
class COGSOnDeliveryStrategy implements COGSRecognitionStrategy
{
    public function __construct(
        private COGSOnInvoiceStrategy $invoiceStrategy,
        private JournalServiceInterface $journalService
    ) {}

    public function onInvoicePost(Invoice $invoice): ?JournalEntry
    {
        // No action on invoice - COGS recognized on delivery
        return null;
    }

    public function onDeliveryShip(DeliveryOrder $deliveryOrder): ?JournalEntry
    {
        $deliveryOrder->loadMissing(['items.product']);

        $cogsAmount = 0;

        foreach ($deliveryOrder->items as $item) {
            if ($item->product_id && $item->product?->track_inventory) {
                $unitCost = ProductStock::where('product_id', $item->product_id)
                    ->where('warehouse_id', $deliveryOrder->warehouse_id)
                    ->where('average_cost', '>', 0)
                    ->value('average_cost') ?? $item->product->purchase_price ?? 0;
                $cogsAmount += (int) round($item->quantity * $unitCost);
            }
        }

        if ($cogsAmount <= 0) {
            return null;
        }

        $cogsAccount = config('accounting.default_accounts.cogs', '5-1001');
        $inventoryAccount = config('accounting.default_accounts.inventory', '1-1400');
        $reference = $deliveryOrder->do_number;
        $entryDate = $deliveryOrder->shipping_date ?? $deliveryOrder->do_date;

        // Handle DateTimeInterface for entry_date
        if ($entryDate instanceof \DateTimeInterface) {
            $entryDate = $entryDate->format('Y-m-d');
        }

        return $this->journalService->createEntry([
            'entry_date' => $entryDate,
            'reference' => $reference,
            'description' => "HPP Pengiriman: {$reference}",
            'source_type' => DeliveryOrder::class,
            'source_id' => $deliveryOrder->id,
            'lines' => [
                [
                    'account_code' => $cogsAccount,
                    'description' => "HPP Pengiriman: {$reference}",
                    'debit' => $cogsAmount,
                    'credit' => 0,
                ],
                [
                    'account_code' => $inventoryAccount,
                    'description' => "Pengurangan persediaan: {$reference}",
                    'debit' => 0,
                    'credit' => $cogsAmount,
                ],
            ],
        ], autoPost: true);
    }

    public function calculateCOGS(Invoice $invoice): int
    {
        return $this->invoiceStrategy->calculateCOGS($invoice);
    }

    public function getIdentifier(): string
    {
        return 'on_delivery';
    }
}
