<?php

declare(strict_types=1);

namespace App\Services\Sales\Invoice;

use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Accounting\Strategies\COGSRecognitionStrategy;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Tax\NsfpServiceInterface;
use App\Domain\Sales\Invoices\InvoiceDomainFactory;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Core\AuditLog;
use App\Models\Sales\Invoice;
use App\Services\Base\Traits\WithEventDispatching;
use App\Services\Base\Traits\WithOperationContext;
use App\Services\Base\Traits\WithTransaction;
use Illuminate\Database\Eloquent\Model;

/**
 * Handles invoice posting (AR/Revenue JE, COGS, NSFP, status transition).
 *
 * Extracted from InvoiceService as part of the Coordinator Pattern refactoring.
 *
 * @see \App\Services\Sales\InvoiceService The coordinator service
 */
class InvoicePostingService
{
    use WithEventDispatching;
    use WithOperationContext;
    use WithTransaction;

    protected EventDispatcherInterface $eventDispatcher;

    protected ContextualLoggerInterface $logger;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        private JournalServiceInterface $journalService,
        private COGSRecognitionStrategy $cogsStrategy,
        private InvoiceDomainFactory $domainFactory,
        private NsfpServiceInterface $nsfpService,
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
    }

    /**
     * Post invoice - create journal entries and transition to Sent.
     *
     * @throws StateTransitionException
     */
    public function post(Invoice $invoice): Invoice
    {
        return $this->executeInTransaction('post', function () use ($invoice) {
            // Pessimistic lock to prevent duplicate posting from concurrent requests
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);

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

            // Allocate NSFP number (Nomor Seri Faktur Pajak) if enabled
            if ($this->nsfpService->isEnabled()) {
                $this->nsfpService->allocate($invoice);
            }

            // Transition status (state machine dispatches InvoiceSent event)
            $invoice->transitionTo(DocumentStatus::Sent, $this->getUserId());

            AuditLog::log(AuditLog::ACTION_POSTED, $invoice, null, [
                'status' => DocumentStatus::Sent->value,
                'total_amount' => $invoice->total_amount,
            ]);

            /** @var Invoice */
            return $this->loadRelations($invoice);
        }, ['invoice_id' => $invoice->id, 'total_amount' => $invoice->total_amount]);
    }

    protected function loadRelations(Model $document): Model
    {
        return $document->fresh(['items', 'contact', 'journalEntry.lines.account']);
    }
}
