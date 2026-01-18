<?php

declare(strict_types=1);

namespace App\Services\Accounting\Strategies\COGS;

use App\Contracts\Accounting\Strategies\COGSRecognitionStrategy;
use App\Models\Accounting\JournalEntry;
use App\Models\Sales\DeliveryOrder;
use App\Models\Sales\Invoice;

/**
 * Manual COGS strategy.
 *
 * No automatic COGS journal entries.
 * COGS must be recorded manually via journal entries.
 */
class ManualCOGSStrategy implements COGSRecognitionStrategy
{
    public function onInvoicePost(Invoice $invoice): ?JournalEntry
    {
        return null;
    }

    public function onDeliveryShip(DeliveryOrder $deliveryOrder): ?JournalEntry
    {
        return null;
    }

    public function calculateCOGS(Invoice $invoice): int
    {
        // Return 0 as COGS is handled manually
        return 0;
    }

    public function getIdentifier(): string
    {
        return 'manual';
    }
}
