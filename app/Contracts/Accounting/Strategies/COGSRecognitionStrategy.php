<?php

declare(strict_types=1);

namespace App\Contracts\Accounting\Strategies;

use App\Models\Accounting\JournalEntry;
use App\Models\Sales\DeliveryOrder;
use App\Models\Sales\Invoice;

/**
 * Strategy for Cost of Goods Sold (COGS) recognition.
 *
 * Implementations determine when COGS journal entries are created:
 * - On invoice post (matches revenue recognition)
 * - On delivery (when goods leave warehouse)
 * - Manual (no automatic COGS)
 */
interface COGSRecognitionStrategy
{
    /**
     * Handle COGS when invoice is posted.
     *
     * OnInvoice: Dr COGS, Cr Inventory (for each inventoried item)
     * OnDelivery: No action
     * Manual: No action
     */
    public function onInvoicePost(Invoice $invoice): ?JournalEntry;

    /**
     * Handle COGS when goods are shipped/delivered.
     *
     * OnInvoice: No action
     * OnDelivery: Dr COGS, Cr Inventory (for each inventoried item)
     * Manual: No action
     */
    public function onDeliveryShip(DeliveryOrder $deliveryOrder): ?JournalEntry;

    /**
     * Calculate COGS amount for an invoice.
     *
     * Uses average cost from inventory movements.
     */
    public function calculateCOGS(Invoice $invoice): int;

    /**
     * Get the strategy identifier.
     */
    public function getIdentifier(): string;
}
