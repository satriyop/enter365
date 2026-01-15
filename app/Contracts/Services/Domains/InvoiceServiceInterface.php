<?php

declare(strict_types=1);

namespace App\Contracts\Services\Domains;

use App\Contracts\Services\DocumentLifecycleInterface;
use App\Models\Sales\Invoice;

/**
 * Interface for Invoice service operations.
 *
 * Extends DocumentLifecycleInterface for CRUD operations and adds
 * invoice-specific operations like posting to journal.
 */
interface InvoiceServiceInterface extends DocumentLifecycleInterface
{
    /**
     * Post an invoice to the journal (create accounting entry).
     *
     * @throws \InvalidArgumentException If invoice is not in draft status
     */
    public function post(Invoice $invoice): Invoice;
}
