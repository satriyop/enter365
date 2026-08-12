<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Models\Accounting\JournalEntry;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use App\Models\Shared\Payment;
use App\Services\Accounting\Journal\DocumentJournalService;
use App\Services\Accounting\Journal\JournalEntryService;
use App\Services\Base\BaseService;
use App\Support\OperationContext;

/**
 * Journal service coordinator.
 *
 * Thin coordinator that delegates to focused services:
 * - JournalEntryService: createEntry, postEntry, reverseEntry
 * - DocumentJournalService: postInvoice, postBill, postPayment
 *
 * Maintains backward compatibility for consumers of JournalService /
 * JournalServiceInterface while keeping responsibilities focused.
 *
 * @see \App\Services\Accounting\Journal\JournalEntryService
 * @see \App\Services\Accounting\Journal\DocumentJournalService
 */
class JournalService extends BaseService implements JournalServiceInterface
{
    public function __construct(
        private JournalEntryService $entryService,
        private DocumentJournalService $documentService,
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Propagate operation context to all underlying services.
     */
    public function withContext(OperationContext $context): static
    {
        $clone = parent::withContext($context);
        $clone->entryService = $this->entryService->withContext($context);
        $clone->documentService = $this->documentService->withContext($context);

        return $clone;
    }

    /**
     * Create a journal entry with lines.
     *
     * @param array{
     *     entry_date: string,
     *     description: string,
     *     reference?: string,
     *     source_type?: string,
     *     source_id?: int,
     *     lines: array<array{account_id: int, debit?: int, credit?: int, description?: string, currency_code?: string|null, amount_currency?: int|null, exchange_rate?: float|null}>
     * } $data
     */
    public function createEntry(array $data, bool $autoPost = false): JournalEntry
    {
        return $this->entryService->createEntry($data, $autoPost);
    }

    /**
     * Validate and post a journal entry.
     *
     * Public for backward compatibility (not on JournalServiceInterface).
     */
    public function postEntry(JournalEntry $entry): JournalEntry
    {
        return $this->entryService->postEntry($entry);
    }

    /**
     * Reverse a posted journal entry.
     */
    public function reverseEntry(JournalEntry $entry, ?string $description = null): JournalEntry
    {
        return $this->entryService->reverseEntry($entry, $description);
    }

    /**
     * Create journal entry for an invoice when posted.
     */
    public function postInvoice(Invoice $invoice): JournalEntry
    {
        return $this->documentService->postInvoice($invoice);
    }

    /**
     * Create journal entry for a bill when posted.
     */
    public function postBill(Bill $bill): JournalEntry
    {
        return $this->documentService->postBill($bill);
    }

    /**
     * Create journal entry for a payment.
     */
    public function postPayment(Payment $payment): JournalEntry
    {
        return $this->documentService->postPayment($payment);
    }
}
