<?php

declare(strict_types=1);

namespace App\Contracts\Accounting;

use App\Models\Accounting\JournalEntry;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;

interface JournalServiceInterface
{
    /**
     * Post an invoice to create journal entry.
     */
    public function postInvoice(Invoice $invoice): JournalEntry;

    /**
     * Post a bill to create journal entry.
     */
    public function postBill(Bill $bill): JournalEntry;

    /**
     * Create a manual journal entry.
     *
     * @param  array<string, mixed>  $data
     * @param  bool  $autoPost  Whether to automatically post the entry (default: false)
     */
    public function createEntry(array $data, bool $autoPost = false): JournalEntry;

    /**
     * Reverse a journal entry.
     *
     * @param  JournalEntry  $entry  The entry to reverse
     * @param  string|null  $description  Optional description for the reversal
     * @return JournalEntry The new reversal entry
     */
    public function reverseEntry(JournalEntry $entry, ?string $description = null): JournalEntry;
}
