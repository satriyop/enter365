<?php

declare(strict_types=1);

namespace App\Listeners\Sales;

use App\Contracts\Accounting\JournalServiceInterface;
use App\Domain\Sales\Events\InvoicePosted;

/**
 * Post invoice to journal when InvoicePosted event is dispatched.
 */
class PostInvoiceToJournal
{
    public function __construct(
        private JournalServiceInterface $journalService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(InvoicePosted $event): void
    {
        $journalEntry = $this->journalService->postInvoice($event->invoice);
        $event->invoice->update([
            'journal_entry_id' => $journalEntry->id,
        ]);
    }
}
