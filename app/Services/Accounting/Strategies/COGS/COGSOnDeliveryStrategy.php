<?php

declare(strict_types=1);

namespace App\Services\Accounting\Strategies\COGS;

use App\Contracts\Accounting\Strategies\COGSRecognitionStrategy;
use App\Models\Accounting\JournalEntry;
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
        private COGSOnInvoiceStrategy $invoiceStrategy
    ) {}

    public function onInvoicePost(Invoice $invoice): ?JournalEntry
    {
        // No action on invoice - COGS recognized on delivery
        return null;
    }

    public function onDeliveryShip(DeliveryOrder $deliveryOrder): ?JournalEntry
    {
        // TODO: Implement COGS on delivery
        // Dr COGS (5-1001)
        // Cr Inventory (1-1400)
        return null;
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
