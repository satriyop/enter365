<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Contracts\Accounting\Strategies\COGSRecognitionStrategy;
use App\Contracts\Services\Accounting\JournalServiceInterface;
use App\Contracts\Services\Domain\DocumentNumberGeneratorInterface;
use App\Contracts\Services\Domains\InvoiceServiceInterface;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\DocumentLockedException;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Services\Base\AbstractDocumentService;
use Illuminate\Database\Eloquent\Model;

class InvoiceService extends AbstractDocumentService implements InvoiceServiceInterface
{
    public function __construct(
        private JournalServiceInterface $journalService,
        private COGSRecognitionStrategy $cogsStrategy,
        private DocumentNumberGeneratorInterface $numberGenerator
    ) {}

    protected function getModelClass(): string
    {
        return Invoice::class;
    }

    protected function getItemRelation(): string
    {
        return 'items';
    }

    protected function generateDocumentNumber(?Model $context = null): string
    {
        $prefix = 'INV-'.now()->format('Ym').'-';

        return $this->numberGenerator->generate($prefix, 'invoices', 'invoice_number');
    }

    protected function getDocumentNumberField(): string
    {
        return 'invoice_number';
    }

    protected function getInitialStatus(): string
    {
        return DocumentStatus::Draft->value;
    }

    protected function getDefaultData(): array
    {
        return [
            ...parent::getDefaultData(),
            'paid_amount' => 0,
        ];
    }

    protected function getEagerLoadRelations(): array
    {
        return ['items', 'contact'];
    }

    /**
     * Create items with calculated amount.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function createItems(Model $document, array $items): void
    {
        foreach ($items as $item) {
            $amount = (int) round($item['quantity'] * $item['unit_price']);

            InvoiceItem::create([
                'invoice_id' => $document->id,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'] ?? 'unit',
                'unit_price' => $item['unit_price'],
                'line_total' => $amount,
                'revenue_account_id' => $item['revenue_account_id'] ?? null,
            ]);
        }
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
     * Post an invoice (create journal entry and change status).
     *
     * Creates both the AR/Revenue journal entry via JournalService
     * and COGS journal entry via configured strategy (if applicable).
     *
     * @throws StateTransitionException
     */
    public function post(Invoice $invoice): Invoice
    {
        if (! $invoice->stateMachine()->canPost()) {
            throw StateTransitionException::actionNotAvailable('posting', $invoice->status->label());
        }

        // Create AR/Revenue journal entry
        $this->journalService->postInvoice($invoice);

        // Create COGS journal entry (if strategy is configured for invoice post)
        // The COGS journal links back to invoice via source_id
        $this->cogsStrategy->onInvoicePost($invoice);

        $invoice->transitionTo(DocumentStatus::Sent, auth()->id());

        return $invoice->fresh(['contact', 'items', 'journalEntry.lines.account']);
    }
}
