<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Inventory\InventoryServiceInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Sales\SalesReturnServiceInterface;
use App\Domain\Sales\SalesReturns\Handlers\SalesReturnApprovalPipeline;
use App\Domain\Sales\SalesReturns\SalesReturnDomainFactory;
use App\Enums\DocumentStatus;
use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\Product;
use App\Models\Sales\Invoice;
use App\Models\Sales\SalesReturn;
use App\Models\Sales\SalesReturnItem;
use App\Services\Base\Traits\WithDocuments;
use App\Services\Base\Traits\WithEventDispatching;
use App\Services\Base\Traits\WithOperationContext;
use App\Services\Base\Traits\WithTransaction;
use App\Services\Sales\Invoice\InvoicePaymentStatusService;
use Illuminate\Database\Eloquent\Model;

class SalesReturnService implements SalesReturnServiceInterface
{
    use WithDocuments;
    use WithEventDispatching;
    use WithOperationContext;
    use WithTransaction;

    protected EventDispatcherInterface $eventDispatcher;

    protected ContextualLoggerInterface $logger;

    private SalesReturnApprovalPipeline $approvalPipeline;

    private JournalServiceInterface $journalService;

    private InventoryServiceInterface $inventoryService;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        SalesReturnApprovalPipeline $approvalPipeline,
        JournalServiceInterface $journalService,
        InventoryServiceInterface $inventoryService,
        private SalesReturnDomainFactory $domainFactory,
        private InvoicePaymentStatusService $invoicePaymentStatus,
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
        $this->approvalPipeline = $approvalPipeline;
        $this->journalService = $journalService;
        $this->inventoryService = $inventoryService;
    }

    protected function getModelClass(): string
    {
        return SalesReturn::class;
    }

    protected function getItemRelation(): string
    {
        return 'items';
    }

    protected function getInitialStatus(): DocumentStatus
    {
        return DocumentStatus::Draft;
    }

    protected function getDefaultData(): array
    {
        return [
            'currency' => 'IDR',
            'exchange_rate' => 1,
            'tax_rate' => config('accounting.tax.default_rate', 11.00),
            'subtotal' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'base_currency_total' => 0,
        ];
    }

    protected function getEagerLoadRelations(): array
    {
        return ['items', 'contact', 'invoice', 'warehouse'];
    }

    /**
     * Create a new sales return.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): SalesReturn
    {
        /** @var SalesReturn */
        $result = $this->createDocument($data);

        return $result;
    }

    /**
     * Update an existing sales return.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(SalesReturn $salesReturn, array $data): SalesReturn
    {
        /** @var SalesReturn */
        $result = $this->updateDocument($salesReturn, $data);

        return $result;
    }

    /**
     * Delete a sales return.
     */
    public function delete(SalesReturn $salesReturn): bool
    {
        return $this->deleteDocument($salesReturn);
    }

    protected function validateEditable(Model $document): void
    {
        /** @var SalesReturn $document */
        if (! $document->canBeEdited()) {
            throw \App\Exceptions\Domain\DocumentLockedException::cannotEdit($document, 'Sales return can only be edited in draft status.');
        }
    }

    protected function validateDeletable(Model $document): void
    {
        /** @var SalesReturn $document */
        if (! $document->canBeEdited()) {
            throw \App\Exceptions\Domain\DocumentLockedException::cannotDelete($document, 'Only draft sales returns can be deleted.');
        }
    }

    protected function createItems(Model $document, array $items): void
    {
        assert($document instanceof SalesReturn);
        foreach ($items as $itemData) {
            $item = new SalesReturnItem($itemData);
            $item->sales_return_id = $document->getKey();
            $item->calculateLineTotal();
            $item->save();
        }
    }

    /**
     * Create sales return from invoice.
     */
    public function createFromInvoice(Invoice $invoice, array $data = []): SalesReturn
    {
        if (in_array($invoice->status, [DocumentStatus::Draft, DocumentStatus::Cancelled], true)) {
            throw new \InvalidArgumentException(
                'Retur penjualan tidak dapat dibuat dari invoice dengan status '.$invoice->status->label().'.'
            );
        }

        return $this->executeInTransaction('create_from_invoice', function () use ($invoice, $data) {
            $defaults = [
                'invoice_id' => $invoice->id,
                'contact_id' => $invoice->contact_id,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'return_date' => $data['return_date'] ?? now()->toDateString(),
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'tax_rate' => $invoice->tax_rate,
                'created_by' => $data['created_by'] ?? $this->getUserId(),
            ];

            /** @var SalesReturn $salesReturn */
            $salesReturn = SalesReturn::create($defaults);

            // Create items from invoice items
            foreach ($invoice->items as $invoiceItem) {
                $item = new SalesReturnItem;
                $item->sales_return_id = $salesReturn->id;
                $item->fillFromInvoiceItem($invoiceItem);
                $item->save();
            }

            $salesReturn->refresh();
            $salesReturn->load('items');
            $this->domainFactory->applyTotals($salesReturn);
            $salesReturn->save();

            return $salesReturn->fresh(['items', 'contact', 'invoice', 'warehouse']);
        }, ['invoice_id' => $invoice->id]);
    }

    protected function recalculateTotals(Model $document): void
    {
        $document->refresh();
        $document->load($this->getItemRelation());

        /** @var SalesReturn $document */
        $this->domainFactory->applyTotals($document);
        $document->save();
    }

    /**
     * Submit a sales return for approval.
     */
    public function submit(SalesReturn $salesReturn, ?int $userId = null): SalesReturn
    {
        if (! $salesReturn->canBeSubmitted()) {
            /** @var DocumentStatus $status */
            $status = $salesReturn->status;
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Sales Return',
                'diajukan',
                $status->value,
                'draft dengan item'
            );
        }

        return $this->executeInTransaction('submit', function () use ($salesReturn, $userId) {
            $salesReturn->transitionTo(DocumentStatus::Submitted, $userId);

            return $salesReturn->fresh();
        }, ['sales_return_id' => $salesReturn->id]);
    }

    /**
     * Approve a sales return.
     *
     * Delegates to the approval pipeline which runs handlers in priority order:
     * 1. InventoryReturnHandler - processes stock-in (if warehouse specified)
     * 2. JournalEntryHandler - creates accounting entries
     */
    public function approve(SalesReturn $salesReturn, ?int $userId = null): SalesReturn
    {
        if (! $salesReturn->canBeApproved()) {
            /** @var DocumentStatus $status */
            $status = $salesReturn->status;
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Sales Return',
                'disetujui',
                $status->value,
                'submitted'
            );
        }

        return $this->executeInTransaction('approve', function () use ($salesReturn, $userId) {
            $salesReturn->transitionTo(DocumentStatus::Approved, $userId);

            // Process approval side effects via pipeline
            $this->approvalPipeline->process($salesReturn);

            return $salesReturn->fresh(['items', 'journalEntry']);
        }, ['sales_return_id' => $salesReturn->id]);
    }

    /**
     * Reject a sales return.
     */
    public function reject(SalesReturn $salesReturn, ?string $reason = null, ?int $userId = null): SalesReturn
    {
        if (! $salesReturn->canBeRejected()) {
            /** @var DocumentStatus $status */
            $status = $salesReturn->status;
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Sales Return',
                'ditolak',
                $status->value,
                'submitted'
            );
        }

        return $this->executeInTransaction('reject', function () use ($salesReturn, $reason, $userId) {
            $salesReturn->transitionTo(DocumentStatus::Rejected, $userId, [
                'rejection_reason' => $reason,
            ]);

            return $salesReturn->fresh();
        }, ['sales_return_id' => $salesReturn->id]);
    }

    /**
     * Complete a sales return (after approved and inventory processed).
     */
    public function complete(SalesReturn $salesReturn, ?int $userId = null): SalesReturn
    {
        if (! $salesReturn->canBeCompleted()) {
            /** @var DocumentStatus $status */
            $status = $salesReturn->status;
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Sales Return',
                'diselesaikan',
                $status->value,
                'approved'
            );
        }

        return $this->executeInTransaction('complete', function () use ($salesReturn, $userId) {
            $salesReturn->transitionTo(DocumentStatus::Completed, $userId);

            return $salesReturn->fresh();
        }, ['sales_return_id' => $salesReturn->id]);
    }

    /**
     * Cancel a sales return.
     *
     * When cancelling an approved return, reverses:
     * - Journal entry (if created)
     * - Inventory stock-in (if warehouse was specified)
     */
    public function cancel(SalesReturn $salesReturn, ?string $reason = null, ?int $userId = null): SalesReturn
    {
        if (! $salesReturn->canBeCancelled()) {
            /** @var DocumentStatus $status */
            $status = $salesReturn->status;
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Sales Return',
                'dibatalkan',
                $status->value,
                'draft, submitted, atau approved'
            );
        }

        $wasApproved = $salesReturn->status === DocumentStatus::Approved;

        return $this->executeInTransaction('cancel', function () use ($salesReturn, $reason, $userId, $wasApproved) {
            // Reverse side effects if cancelling from approved status
            if ($wasApproved) {
                $this->reverseApprovalSideEffects($salesReturn);
            }

            $salesReturn->transitionTo(DocumentStatus::Cancelled, $userId, [
                'cancellation_reason' => $reason,
            ]);

            return $salesReturn->fresh();
        }, ['sales_return_id' => $salesReturn->id]);
    }

    /**
     * Reverse side effects that were applied during approval.
     */
    private function reverseApprovalSideEffects(SalesReturn $salesReturn): void
    {
        // Reverse journal entry
        if ($salesReturn->journal_entry_id) {
            $journalEntry = JournalEntry::find($salesReturn->journal_entry_id);
            if ($journalEntry) {
                $this->journalService->reverseEntry(
                    $journalEntry,
                    'Pembatalan retur penjualan: '.$salesReturn->return_number
                );
            }
            $salesReturn->journal_entry_id = null;
        }

        if ($salesReturn->invoice_id && $salesReturn->invoice) {
            $this->invoicePaymentStatus->reverseCreditNote(
                $salesReturn->invoice,
                $salesReturn->total_amount
            );
        }

        // Reverse inventory stock-in
        if ($salesReturn->warehouse_id) {
            $warehouse = $salesReturn->warehouse;

            foreach ($salesReturn->items as $item) {
                if (! $item->product_id) {
                    continue;
                }

                $product = Product::find($item->product_id);
                if (! $product || ! $product->track_inventory) {
                    continue;
                }

                $this->inventoryService->stockOut(
                    $product,
                    $warehouse,
                    (int) round((float) $item->quantity),
                    'Pembatalan retur penjualan: '.$salesReturn->return_number,
                    SalesReturn::class,
                    $salesReturn->id
                );
            }
        }

        $salesReturn->save();
    }

    /**
     * Get sales returns for an invoice.
     */
    public function getForInvoice(Invoice $invoice): \Illuminate\Database\Eloquent\Collection
    {
        return SalesReturn::query()
            ->where('invoice_id', $invoice->id)
            ->with(['items', 'creator'])
            ->orderBy('return_date', 'desc')
            ->get();
    }

    /**
     * Get statistics for sales returns.
     */
    public function getStatistics(?string $startDate = null, ?string $endDate = null): array
    {
        $query = SalesReturn::query();

        if ($startDate) {
            $query->where('return_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('return_date', '<=', $endDate);
        }

        $returns = $query->get();

        return [
            'total_count' => $returns->count(),
            'draft_count' => $returns->where('status', DocumentStatus::Draft)->count(),
            'submitted_count' => $returns->where('status', DocumentStatus::Submitted)->count(),
            'approved_count' => $returns->where('status', DocumentStatus::Approved)->count(),
            'completed_count' => $returns->where('status', DocumentStatus::Completed)->count(),
            'cancelled_count' => $returns->where('status', DocumentStatus::Cancelled)->count(),
            'total_value' => $returns->whereNotIn('status', [DocumentStatus::Cancelled])->sum('total_amount'),
            'by_reason' => $returns->whereNotIn('status', [DocumentStatus::Cancelled])
                ->groupBy('reason')
                ->map(fn ($group) => [
                    'count' => $group->count(),
                    'total' => $group->sum('total_amount'),
                ]),
        ];
    }
}
