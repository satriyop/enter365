<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Accounting\Strategies\COGSRecognitionStrategy;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Sales\InvoiceServiceInterface;
use App\Domain\Sales\Invoices\Events\InvoiceFullyPaid;
use App\Domain\Sales\Invoices\Events\InvoiceOverdue;
use App\Domain\Sales\Invoices\Events\InvoicePartiallyPaid;
use App\Domain\Sales\Invoices\Events\InvoiceSent;
use App\Domain\Sales\Invoices\Events\InvoiceVoided;
use App\Domain\Sales\Invoices\InvoiceDomainFactory;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\DocumentLockedException;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Services\Base\Traits\WithDocuments;
use App\Services\Base\Traits\WithEventDispatching;
use App\Services\Base\Traits\WithOperationContext;
use App\Services\Base\Traits\WithTransaction;
use Illuminate\Database\Eloquent\Model;

/**
 * Service for invoice operations.
 *
 * Handles invoice CRUD, posting, and voiding with:
 * - Journal entry creation for AR/Revenue
 * - COGS recognition via configurable strategy
 * - Event dispatching for audit trails
 */
class InvoiceService implements InvoiceServiceInterface
{
    use WithDocuments;
    use WithEventDispatching;
    use WithOperationContext;
    use WithTransaction;

    protected EventDispatcherInterface $eventDispatcher;

    protected ContextualLoggerInterface $logger;

    private JournalServiceInterface $journalService;

    private COGSRecognitionStrategy $cogsStrategy;

    private InvoiceDomainFactory $domainFactory;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        JournalServiceInterface $journalService,
        COGSRecognitionStrategy $cogsStrategy,
        InvoiceDomainFactory $domainFactory
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;

        $this->journalService = $journalService;
        $this->cogsStrategy = $cogsStrategy;
        $this->domainFactory = $domainFactory;
    }

    protected function getModelClass(): string
    {
        return Invoice::class;
    }

    protected function getItemRelation(): string
    {
        return 'items';
    }

    protected function getDefaultData(): array
    {
        return [
            'currency' => 'IDR',
            'exchange_rate' => 1,
            'tax_rate' => config('accounting.tax.default_rate', 11.00),
            'subtotal' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'base_currency_total' => 0,
            'paid_amount' => 0,
        ];
    }

    protected function getEagerLoadRelations(): array
    {
        return ['items', 'contact'];
    }

    /**
     * Create a new invoice.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Invoice
    {
        return $this->createDocument($data);
    }

    /**
     * Update an existing invoice.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Model $document, array $data): Invoice
    {
        return $this->updateDocument($document, $data);
    }

    /**
     * Delete an invoice.
     */
    public function delete(Model $document): bool
    {
        return $this->deleteDocument($document);
    }

    /**
     * Create items with calculated line total.
     */
    protected function createItems(Model $document, array $items): void
    {
        foreach ($items as $item) {
            $lineTotal = (int) round($item['quantity'] * $item['unit_price']);

            InvoiceItem::create([
                'invoice_id' => $document->id,
                'product_id' => $item['product_id'] ?? null,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'] ?? 'unit',
                'unit_price' => $item['unit_price'],
                'line_total' => $lineTotal,
                'revenue_account_id' => $item['revenue_account_id'] ?? null,
            ]);
        }
    }

    protected function loadRelations(Model $document): Model
    {
        return $document->fresh(['items', 'contact', 'journalEntry.lines.account']);
    }

    /**
     * Validate that invoice can be edited.
     *
     * @throws DocumentLockedException
     */
    protected function validateEditable(Model $document): void
    {
        /** @var Invoice $document */
        if ($document->status !== DocumentStatus::Draft) {
            throw DocumentLockedException::cannotEdit($document, 'Hanya faktur draft yang bisa diubah.');
        }
    }

    /**
     * Validate that invoice can be deleted.
     *
     * @throws DocumentLockedException
     */
    protected function validateDeletable(Model $document): void
    {
        /** @var Invoice $document */
        if ($document->status !== DocumentStatus::Draft) {
            throw DocumentLockedException::cannotDelete($document, 'Hanya faktur draft yang bisa dihapus.');
        }

        if ($document->payments()->exists()) {
            throw DocumentLockedException::hasDependencies($document, 'pembayaran');
        }
    }

    /**
     * Post invoice - create journal entries and transition to Sent.
     *
     * @throws StateTransitionException
     */
    public function post(Invoice $invoice): Invoice
    {
        return $this->executeInTransaction('post', function () use ($invoice) {
            if (! $this->domainFactory->stateMachine($invoice)->canPost()) {
                throw StateTransitionException::actionNotAvailable(
                    'posting',
                    $invoice->status->label()
                );
            }

            // Create AR/Revenue journal entry
            $this->journalService->postInvoice($invoice);

            // Create COGS journal entry (if configured)
            $this->cogsStrategy->onInvoicePost($invoice);

            // Transition status
            $invoice->transitionTo(DocumentStatus::Sent, $this->getUserId());

            // Dispatch event
            $this->dispatch(InvoiceSent::fromInvoice($invoice, $this->getUserId()));

            return $this->loadRelations($invoice);
        }, ['invoice_id' => $invoice->id, 'total_amount' => $invoice->total_amount]);
    }

    /**
     * Void/cancel posted invoice.
     *
     * @throws StateTransitionException
     */
    public function void(Invoice $invoice, string $reason): Invoice
    {
        return $this->executeInTransaction('void', function () use ($invoice, $reason) {
            if (! $this->domainFactory->stateMachine($invoice)->canCancel()) {
                throw StateTransitionException::actionNotAvailable(
                    'void',
                    $invoice->status->label()
                );
            }

            // Reverse journal entry if exists
            if ($invoice->journal_entry_id && $invoice->journalEntry) {
                $this->journalService->reverseEntry($invoice->journalEntry);
            }

            // Transition status
            $invoice->transitionTo(DocumentStatus::Cancelled, $this->getUserId());

            // Dispatch event
            $this->dispatch(InvoiceVoided::fromInvoice($invoice, $this->getUserId(), $reason));

            return $this->loadRelations($invoice);
        }, ['invoice_id' => $invoice->id, 'reason' => $reason]);
    }

    /**
     * Mark invoice as fully paid.
     *
     * @throws StateTransitionException
     */
    public function markAsPaid(Invoice $invoice): Invoice
    {
        return $this->executeInTransaction('mark_paid', function () use ($invoice) {
            if (! $this->domainFactory->stateMachine($invoice)->canMarkAsPaid()) {
                throw StateTransitionException::actionNotAvailable(
                    'mark_as_paid',
                    $invoice->status->label()
                );
            }

            $invoice->transitionTo(DocumentStatus::Paid, $this->getUserId());

            $this->dispatch(InvoiceFullyPaid::fromInvoice($invoice, $this->getUserId() ?? 0));

            return $invoice;
        }, ['invoice_id' => $invoice->id]);
    }

    /**
     * Mark invoice as partially paid.
     *
     * @throws StateTransitionException
     */
    public function markAsPartial(Invoice $invoice): Invoice
    {
        return $this->executeInTransaction('mark_partial', function () use ($invoice) {
            if (! $this->domainFactory->stateMachine($invoice)->canMarkAsPartial()) {
                throw StateTransitionException::actionNotAvailable(
                    'mark_as_partial',
                    $invoice->status->label()
                );
            }

            $invoice->transitionTo(DocumentStatus::Partial, $this->getUserId());

            $this->dispatch(InvoicePartiallyPaid::fromInvoice($invoice, $this->getUserId() ?? 0));

            return $invoice;
        }, ['invoice_id' => $invoice->id]);
    }

    /**
     * Mark invoice as overdue.
     *
     * @throws StateTransitionException
     */
    public function markAsOverdue(Invoice $invoice): Invoice
    {
        return $this->executeInTransaction('mark_overdue', function () use ($invoice) {
            if (! $this->domainFactory->stateMachine($invoice)->canMarkAsOverdue()) {
                throw StateTransitionException::actionNotAvailable(
                    'mark_as_overdue',
                    $invoice->status->label()
                );
            }

            $invoice->transitionTo(DocumentStatus::Overdue, $this->getUserId());

            $this->dispatch(InvoiceOverdue::fromInvoice($invoice));

            return $invoice;
        }, ['invoice_id' => $invoice->id]);
    }

    /**
     * Update invoice payment status based on current paid amount.
     *
     * Automatically determines the correct status:
     * - Paid: if paid_amount >= total_amount
     * - Partial: if paid_amount > 0 and < total_amount
     * - Overdue: if past due date and not fully paid
     */
    public function updatePaymentStatus(Invoice $invoice): Invoice
    {
        $invoice->refresh();

        // Skip if cancelled
        if ($invoice->status === DocumentStatus::Cancelled) {
            return $invoice;
        }

        // Skip if still draft
        if ($invoice->status === DocumentStatus::Draft) {
            return $invoice;
        }

        // Determine target status based on payment
        if ($invoice->paid_amount >= $invoice->total_amount) {
            // Already paid? No change needed
            if ($invoice->status === DocumentStatus::Paid) {
                return $invoice;
            }

            return $this->markAsPaid($invoice);
        }

        if ($invoice->paid_amount > 0) {
            // Already partial? No change needed
            if ($invoice->status === DocumentStatus::Partial) {
                return $invoice;
            }

            return $this->markAsPartial($invoice);
        }

        // No payment - check if overdue
        if ($invoice->due_date->isPast() && ! in_array($invoice->status, [DocumentStatus::Overdue, DocumentStatus::Paid])) {
            return $this->markAsOverdue($invoice);
        }

        return $invoice;
    }
}
