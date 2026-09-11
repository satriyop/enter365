<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Contracts\Accounting\FiscalPositionServiceInterface;
use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Purchasing\BillServiceInterface;
use App\Domain\Purchasing\Bills\BillDomainFactory;
use App\Domain\Purchasing\Bills\Events\BillFullyPaid;
use App\Domain\Purchasing\Bills\Events\BillOverdue;
use App\Domain\Purchasing\Bills\Events\BillPartiallyPaid;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\BusinessRuleException;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Accounting\FiscalPosition;
use App\Models\Accounting\JournalEntry;
use App\Models\Core\AuditLog;
use App\Models\Inventory\Product;
use App\Models\Purchasing\Bill;
use App\Models\Purchasing\BillItem;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderItem;
use App\Models\Tax\TaxRecord;
use App\Services\Base\Traits\WithDocuments;
use App\Services\Base\Traits\WithEventDispatching;
use App\Services\Base\Traits\WithOperationContext;
use App\Services\Base\Traits\WithTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BillService implements BillServiceInterface
{
    use WithDocuments;
    use WithEventDispatching;
    use WithOperationContext;
    use WithTransaction;

    protected EventDispatcherInterface $eventDispatcher;

    protected ContextualLoggerInterface $logger;

    private JournalServiceInterface $journalService;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        JournalServiceInterface $journalService,
        private BillDomainFactory $domainFactory,
        private FiscalPositionServiceInterface $fiscalPositions,
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;

        $this->journalService = $journalService;
    }

    protected function recalculateTotals(Model $document): void
    {
        $document->refresh();
        $document->load($this->getItemRelation());

        /** @var Bill $document */
        $this->domainFactory->applyTotals($document);
        $document->save();
    }

    protected function getModelClass(): string
    {
        return Bill::class;
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
     * Create a new bill.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Bill
    {
        /** @var Bill */
        return $this->createDocument($data);
    }

    /**
     * Update an existing bill.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Model $document, array $data): Bill
    {
        /** @var Bill */
        return $this->updateDocument($document, $data);
    }

    /**
     * Delete a bill.
     */
    public function delete(Model $document): bool
    {
        return $this->deleteDocument($document);
    }

    /**
     * Create items with calculated amount.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function createItems(Model $document, array $items): void
    {
        assert($document instanceof Bill);
        $position = $this->fiscalPositions->forContactId($document->contact_id);
        foreach ($items as $item) {
            $amount = (int) round($item['quantity'] * $item['unit_price']);
            $taxes = $this->resolveLineTaxes($item, $position);
            $taxAmount = (int) round($amount * ($taxes['tax_rate'] / 100));
            $expenseAccountId = $item['expense_account_id'] ?? $item['account_id'] ?? null;

            BillItem::create([
                'bill_id' => $document->getKey(),
                'product_id' => $item['product_id'] ?? null,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'] ?? 'unit',
                'unit_price' => $item['unit_price'],
                'discount_percent' => $item['discount_percent'] ?? 0,
                'tax_rate' => $taxes['tax_rate'],
                'tax_amount' => $taxAmount,
                'line_total' => $amount,
                'expense_account_id' => $this->fiscalPositions->mapAccountId(
                    $position,
                    $expenseAccountId !== null ? (int) $expenseAccountId : null,
                ),
                'analytic_distribution' => $item['analytic_distribution'] ?? null,
                'tax_tag_ids' => $taxes['tax_tag_ids'],
                'tax_record_ids' => $taxes['tax_record_ids'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{tax_rate: float, tax_record_ids: list<int>|null, tax_tag_ids: list<int>|null}
     */
    private function resolveLineTaxes(array $item, ?FiscalPosition $position = null): array
    {
        $explicitTags = is_array($item['tax_tag_ids'] ?? null) ? array_values(array_map('intval', $item['tax_tag_ids'])) : null;
        $recordIds = is_array($item['tax_record_ids'] ?? null)
            ? array_values(array_filter(array_map('intval', $item['tax_record_ids'])))
            : [];

        if (array_key_exists('tax_record_ids', $item) && $recordIds === []) {
            return [
                'tax_rate' => (float) ($item['tax_rate'] ?? 0),
                'tax_record_ids' => [],
                'tax_tag_ids' => $explicitTags,
            ];
        }

        if ($recordIds === [] && ! isset($item['tax_rate']) && ! empty($item['product_id'])) {
            $product = Product::query()->with('purchaseTaxes')->find((int) $item['product_id']);
            if ($product === null) {
                return [
                    'tax_rate' => 0.0,
                    'tax_record_ids' => null,
                    'tax_tag_ids' => $explicitTags,
                ];
            }

            $recordIds = $product->purchaseTaxes
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();

            if ($recordIds === []) {
                return [
                    'tax_rate' => $product->purchaseTaxRate(),
                    'tax_record_ids' => [],
                    'tax_tag_ids' => $explicitTags,
                ];
            }
        }

        if ($recordIds !== []) {
            $recordIds = $this->fiscalPositions->mapTaxRecordIds($position, $recordIds);
            if ($recordIds === []) {
                return [
                    'tax_rate' => 0.0,
                    'tax_record_ids' => [],
                    'tax_tag_ids' => $explicitTags,
                ];
            }

            $records = TaxRecord::query()->whereIn('id', $recordIds)->get();
            $fromRecords = $records
                ->pluck('tax_tag_id')
                ->filter()
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();

            return [
                'tax_rate' => (float) $records->sum(fn (TaxRecord $tax): float => (float) $tax->rate),
                'tax_record_ids' => $recordIds,
                'tax_tag_ids' => ($explicitTags !== null && $explicitTags !== [])
                    ? $explicitTags
                    : ($fromRecords !== [] ? $fromRecords : null),
            ];
        }

        return [
            'tax_rate' => (float) ($item['tax_rate'] ?? 0),
            'tax_record_ids' => null,
            'tax_tag_ids' => $explicitTags,
        ];
    }

    /**
     * Validate that bill can be edited.
     *
     * @throws \App\Exceptions\Domain\DocumentLockedException
     */
    protected function validateEditable(Model $document): void
    {
        /** @var Bill $document */
        if ($document->status !== DocumentStatus::Draft) {
            throw \App\Exceptions\Domain\DocumentLockedException::cannotEdit($document, 'Hanya tagihan draft yang bisa diubah.');
        }
    }

    /**
     * Validate that bill can be deleted.
     *
     * @throws \App\Exceptions\Domain\DocumentLockedException
     * @throws \App\Exceptions\Domain\BusinessRuleException
     */
    protected function validateDeletable(Model $document): void
    {
        /** @var Bill $document */
        if ($document->status !== DocumentStatus::Draft) {
            throw \App\Exceptions\Domain\DocumentLockedException::cannotDelete($document, 'Hanya tagihan draft yang bisa dihapus.');
        }

        if ($document->payments()->exists()) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'menghapus bill',
                'Tidak bisa menghapus tagihan yang sudah memiliki pembayaran'
            );
        }
    }

    /**
     * Post a bill (create journal entry and change status).
     */
    public function post(Bill $bill): Bill
    {
        return $this->executeInTransaction('post', function () use ($bill) {
            // Pessimistic lock to prevent duplicate posting from concurrent requests
            $bill = Bill::lockForUpdate()->findOrFail($bill->id);

            if (! $bill->stateMachine()->canPost()) {
                throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                    'Bill',
                    'diposting',
                    $bill->status->value,
                    'draft'
                );
            }

            $this->journalService->postBill($bill);

            $bill->transitionTo(DocumentStatus::Received, $this->getUserId());

            AuditLog::log(AuditLog::ACTION_POSTED, $bill, null, [
                'status' => DocumentStatus::Received->value,
                'total_amount' => $bill->total_amount,
            ]);

            return $bill->fresh(['contact', 'items', 'journalEntry.lines.account']);
        }, ['bill_id' => $bill->id, 'total_amount' => $bill->total_amount]);
    }

    /**
     * Mark bill as fully paid.
     *
     * @throws StateTransitionException
     */
    public function markAsPaid(Bill $bill): Bill
    {
        if (! $bill->stateMachine()->canMarkAsPaid()) {
            throw StateTransitionException::actionNotAvailable(
                'mark_as_paid',
                $bill->status->label()
            );
        }

        $bill->transitionTo(DocumentStatus::Paid, $this->getUserId());

        event(BillFullyPaid::fromBill($bill, $this->getUserId() ?? 0));

        return $bill->fresh(['contact', 'items']);
    }

    /**
     * Mark bill as partially paid.
     *
     * @throws StateTransitionException
     */
    public function markAsPartial(Bill $bill): Bill
    {
        if (! $bill->stateMachine()->canMarkAsPartial()) {
            throw StateTransitionException::actionNotAvailable(
                'mark_as_partial',
                $bill->status->label()
            );
        }

        $bill->transitionTo(DocumentStatus::Partial, $this->getUserId());

        event(BillPartiallyPaid::fromBill($bill, $this->getUserId() ?? 0));

        return $bill->fresh(['contact', 'items']);
    }

    /**
     * Mark bill as overdue.
     *
     * @throws StateTransitionException
     */
    public function markAsOverdue(Bill $bill): Bill
    {
        if (! $bill->stateMachine()->canMarkAsOverdue()) {
            throw StateTransitionException::actionNotAvailable(
                'mark_as_overdue',
                $bill->status->label()
            );
        }

        $bill->transitionTo(DocumentStatus::Overdue, $this->getUserId());

        event(BillOverdue::fromBill($bill));

        return $bill->fresh(['contact', 'items']);
    }

    /**
     * Update bill payment status based on current paid amount.
     *
     * Automatically determines the correct status:
     * - Paid: if paid_amount >= total_amount
     * - Partial: if paid_amount > 0 and < total_amount
     * - Overdue: if past due date and not fully paid
     */
    public function updatePaymentStatus(Bill $bill): Bill
    {
        $bill->refresh();

        // Skip if cancelled
        if ($bill->status === DocumentStatus::Cancelled) {
            return $bill;
        }

        // Skip if still draft
        if ($bill->status === DocumentStatus::Draft) {
            return $bill;
        }

        // Determine target status based on payment
        if ($bill->paid_amount >= $bill->total_amount) {
            if ($bill->status === DocumentStatus::Paid) {
                return $bill;
            }

            return $this->markAsPaid($bill);
        }

        if ($bill->paid_amount > 0) {
            if ($bill->status === DocumentStatus::Partial) {
                return $bill;
            }

            return $this->markAsPartial($bill);
        }

        // No payment - check if overdue
        if ($bill->due_date->isPast() && ! in_array($bill->status, [DocumentStatus::Overdue, DocumentStatus::Paid])) {
            return $this->markAsOverdue($bill);
        }

        return $bill;
    }

    /**
     * Void/cancel a posted bill.
     *
     * @throws StateTransitionException
     */
    public function void(Bill $bill, string $reason): Bill
    {
        return $this->executeInTransaction('void', function () use ($bill, $reason) {
            if (! $bill->stateMachine()->canCancel()) {
                throw StateTransitionException::actionNotAvailable(
                    'void',
                    $bill->status->label()
                );
            }

            // Reverse journal entry if exists
            if ($bill->journal_entry_id && $bill->journalEntry) {
                $this->journalService->reverseEntry($bill->journalEntry);
            }

            // Transition status
            $bill->transitionTo(DocumentStatus::Cancelled, $this->getUserId());

            // Dispatch event
            $this->dispatch(\App\Domain\Purchasing\Bills\Events\BillVoided::fromBill(
                $bill,
                $this->getUserId(),
                $reason
            ));

            AuditLog::log(AuditLog::ACTION_VOIDED, $bill, null, [
                'status' => DocumentStatus::Cancelled->value,
                'reason' => $reason,
            ]);

            return $bill->fresh(['contact', 'items', 'journalEntry']);
        }, ['bill_id' => $bill->id, 'reason' => $reason]);
    }

    public function createCreditNote(Bill $bill, string $reason): JournalEntry
    {
        return $this->executeInTransaction('create_credit_note', function () use ($bill, $reason) {
            $locked = Bill::query()->lockForUpdate()->findOrFail($bill->id);
            $entry = $locked->journalEntry;

            if ($entry === null) {
                throw BusinessRuleException::operationNotAllowed(
                    'nota kredit vendor',
                    'Tagihan belum memiliki jurnal yang bisa dibalik.'
                );
            }

            $reversal = $this->journalService->reverseEntry($entry, $reason);

            AuditLog::log(AuditLog::ACTION_REVERSED, $locked, null, [
                'credit_note_journal_entry_id' => $reversal->id,
                'reason' => $reason,
            ]);

            return $reversal;
        }, ['bill_id' => $bill->id, 'reason' => $reason]);
    }

    /**
     * @param  list<array{bill_item_id: int, purchase_order_item_id: int}>  $lines
     */
    public function matchPurchaseOrder(Bill $bill, int $purchaseOrderId, array $lines): Bill
    {
        return $this->executeInTransaction('match_purchase_order', function () use ($bill, $purchaseOrderId, $lines) {
            $locked = Bill::query()->lockForUpdate()->findOrFail($bill->id);
            $purchaseOrder = PurchaseOrder::query()->findOrFail($purchaseOrderId);

            if ($purchaseOrder->contact_id !== $locked->contact_id) {
                throw BusinessRuleException::operationNotAllowed(
                    'purchase matching',
                    'Purchase order harus dari vendor yang sama.'
                );
            }

            $billItemIds = $locked->items()->pluck('id')->all();
            $poItemIds = $purchaseOrder->items()->pluck('id')->all();

            foreach ($lines as $line) {
                $billItemId = (int) $line['bill_item_id'];
                $poItemId = (int) $line['purchase_order_item_id'];

                if (! in_array($billItemId, $billItemIds, true)) {
                    throw BusinessRuleException::operationNotAllowed(
                        'purchase matching',
                        'Baris tagihan tidak termasuk dalam tagihan ini.'
                    );
                }

                if (! in_array($poItemId, $poItemIds, true)) {
                    throw BusinessRuleException::operationNotAllowed(
                        'purchase matching',
                        'Baris purchase order tidak termasuk dalam PO ini.'
                    );
                }

                BillItem::query()->whereKey($billItemId)->update([
                    'purchase_order_item_id' => $poItemId,
                ]);
            }

            $locked->update(['purchase_order_id' => $purchaseOrder->id]);

            return $locked->fresh(['contact', 'items.expenseAccount', 'journalEntry.lines.account', 'payments', 'purchaseOrder']);
        }, ['bill_id' => $bill->id, 'purchase_order_id' => $purchaseOrderId]);
    }

    public function purchaseMatching(Bill $bill, ?int $purchaseOrderId = null): array
    {
        $purchaseOrderId ??= $bill->purchase_order_id;
        $purchaseOrder = $purchaseOrderId
            ? PurchaseOrder::query()->with('items')->find($purchaseOrderId)
            : null;

        $bill->loadMissing('items');

        $poItemIds = $purchaseOrder?->items->pluck('id')->all() ?? [];
        $billedByPoItem = $poItemIds === []
            ? collect()
            : DB::table('bill_items')
                ->whereIn('purchase_order_item_id', $poItemIds)
                ->select('purchase_order_item_id')
                ->selectRaw('SUM(quantity) as billed_quantity')
                ->selectRaw('SUM(line_total) as billed_amount')
                ->groupBy('purchase_order_item_id')
                ->get()
                ->keyBy('purchase_order_item_id');

        return [
            'purchase_order_id' => $purchaseOrder?->id,
            'bill_lines' => $bill->items->map(fn (BillItem $item): array => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'description' => (string) $item->description,
                'quantity' => (float) $item->quantity,
                'unit' => (string) $item->unit,
                'unit_price' => (int) $item->unit_price,
                'line_total' => (int) $item->line_total,
                'purchase_order_item_id' => $item->purchase_order_item_id,
            ])->values()->all(),
            'purchase_lines' => ($purchaseOrder?->items ?? collect())->map(function (PurchaseOrderItem $item) use ($billedByPoItem): array {
                $billed = $billedByPoItem->get($item->id);
                $billedQty = (float) ($billed->billed_quantity ?? 0);
                $purchasedQty = (float) $item->quantity;

                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'description' => (string) $item->description,
                    'quantity' => $purchasedQty,
                    'quantity_received' => (float) $item->quantity_received,
                    'unit' => (string) $item->unit,
                    'unit_price' => (int) $item->unit_price,
                    'billed_quantity' => $billedQty,
                    'billed_amount' => (int) ($billed->billed_amount ?? 0),
                    'qty_to_invoice' => max(0, $purchasedQty - $billedQty),
                ];
            })->values()->all(),
        ];
    }
}
