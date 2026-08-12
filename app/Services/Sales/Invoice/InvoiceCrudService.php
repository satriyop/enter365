<?php

declare(strict_types=1);

namespace App\Services\Sales\Invoice;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\DocumentLockedException;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Services\Base\Traits\WithDocuments;
use App\Services\Base\Traits\WithEventDispatching;
use App\Services\Base\Traits\WithOperationContext;
use App\Services\Base\Traits\WithTransaction;
use Illuminate\Database\Eloquent\Model;

/**
 * Handles CRUD operations for invoices.
 *
 * Extracted from InvoiceService as part of the Coordinator Pattern refactoring.
 *
 * @see \App\Services\Sales\InvoiceService The coordinator service
 */
class InvoiceCrudService
{
    use WithDocuments;
    use WithEventDispatching;
    use WithOperationContext;
    use WithTransaction;

    protected EventDispatcherInterface $eventDispatcher;

    protected ContextualLoggerInterface $logger;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
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
        /** @var Invoice */
        return $this->createDocument($data);
    }

    /**
     * Update an existing invoice.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Model $document, array $data): Invoice
    {
        /** @var Invoice */
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
        assert($document instanceof Invoice);
        foreach ($items as $index => $item) {
            $invoiceItem = new InvoiceItem([
                'invoice_id' => $document->getKey(),
                'product_id' => $item['product_id'] ?? null,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'] ?? 'unit',
                'unit_price' => $item['unit_price'],
                'discount_percent' => $item['discount_percent'] ?? 0,
                'discount_amount' => $item['discount_amount'] ?? 0,
                'tax_rate' => $item['tax_rate'] ?? 0,
                'tax_amount' => $item['tax_amount'] ?? 0,
                'sort_order' => $item['sort_order'] ?? $index,
                'notes' => $item['notes'] ?? null,
                'revenue_account_id' => $item['revenue_account_id'] ?? null,
            ]);
            $invoiceItem->calculateLineTotal();
            $invoiceItem->save();
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
}
