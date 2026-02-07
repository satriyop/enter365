<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Purchasing\PurchaseReturnServiceInterface;
use App\Domain\Purchasing\PurchaseReturns\Handlers\PurchaseReturnApprovalPipeline;
use App\Enums\DocumentStatus;
use App\Models\Purchasing\Bill;
use App\Models\Purchasing\PurchaseReturn;
use App\Models\Purchasing\PurchaseReturnItem;
use App\Services\Base\Traits\WithDocuments;
use App\Services\Base\Traits\WithEventDispatching;
use App\Services\Base\Traits\WithOperationContext;
use App\Services\Base\Traits\WithTransaction;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturnService implements PurchaseReturnServiceInterface
{
    use WithDocuments;
    use WithEventDispatching;
    use WithOperationContext;
    use WithTransaction;

    protected EventDispatcherInterface $eventDispatcher;

    protected ContextualLoggerInterface $logger;

    private PurchaseReturnApprovalPipeline $approvalPipeline;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        PurchaseReturnApprovalPipeline $approvalPipeline
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;

        $this->approvalPipeline = $approvalPipeline;
    }

    protected function getModelClass(): string
    {
        return PurchaseReturn::class;
    }

    protected function getItemRelation(): string
    {
        return 'items';
    }

    protected function getInitialStatus(): DocumentStatus
    {
        return DocumentStatus::Draft;
    }

    protected function getEagerLoadRelations(): array
    {
        return ['items', 'contact', 'bill', 'warehouse'];
    }

    /**
     * Create a new purchase return.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PurchaseReturn
    {
        return $this->createDocument($data);
    }

    /**
     * Update an existing purchase return.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(PurchaseReturn $purchaseReturn, array $data): PurchaseReturn
    {
        return $this->updateDocument($purchaseReturn, $data);
    }

    /**
     * Delete a purchase return.
     */
    public function delete(PurchaseReturn $purchaseReturn): bool
    {
        return $this->deleteDocument($purchaseReturn);
    }

    protected function validateEditable(Model $document): void
    {
        /** @var PurchaseReturn $document */
        if (! $document->isEditable()) {
            throw \App\Exceptions\Domain\DocumentLockedException::cannotEdit($document, 'Purchase return can only be edited in draft status.');
        }
    }

    protected function validateDeletable(Model $document): void
    {
        /** @var PurchaseReturn $document */
        if (! $document->isEditable()) {
            throw \App\Exceptions\Domain\DocumentLockedException::cannotDelete($document, 'Only draft purchase returns can be deleted.');
        }
    }

    protected function createItems(Model $document, array $items): void
    {
        assert($document instanceof PurchaseReturn);
        foreach ($items as $itemData) {
            $item = new PurchaseReturnItem($itemData);
            $item->purchase_return_id = $document->id;
            $item->calculateLineTotal();
            $item->save();
        }
    }

    /**
     * Create a new purchase return.
     */
    public function createFromBill(Bill $bill, array $data = []): PurchaseReturn
    {
        return $this->executeInTransaction('create_from_bill', function () use ($bill, $data) {
            $purchaseReturn = new PurchaseReturn([
                'bill_id' => $bill->id,
                'contact_id' => $bill->contact_id,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'return_date' => $data['return_date'] ?? now()->toDateString(),
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'tax_rate' => $bill->tax_rate,
                'created_by' => $data['created_by'] ?? $this->getUserId(),
            ]);
            $purchaseReturn->save();

            foreach ($bill->items as $billItem) {
                $item = new PurchaseReturnItem;
                $item->purchase_return_id = $purchaseReturn->id;
                $item->fillFromBillItem($billItem);
                $item->save();
            }

            $purchaseReturn->calculateTotals();
            $purchaseReturn->save();

            return $purchaseReturn->fresh(['items', 'contact', 'bill', 'warehouse']);
        }, ['bill_id' => $bill->id]);
    }

    public function submit(PurchaseReturn $purchaseReturn, ?int $userId = null): PurchaseReturn
    {
        if (! $purchaseReturn->stateMachine()->canSubmit()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Purchase Return',
                'diajukan',
                $purchaseReturn->status->value,
                'draft dengan item'
            );
        }

        $purchaseReturn->transitionTo(DocumentStatus::Submitted, $userId);

        return $purchaseReturn->fresh(['items', 'contact', 'bill']);
    }

    /**
     * Approve a purchase return.
     *
     * Delegates to the approval pipeline which runs handlers in priority order:
     * 1. InventoryReturnHandler - processes stock-out (if warehouse specified)
     * 2. JournalEntryHandler - creates accounting entries
     */
    public function approve(PurchaseReturn $purchaseReturn, ?int $userId = null): PurchaseReturn
    {
        if (! $purchaseReturn->stateMachine()->canApprove()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Purchase Return',
                'disetujui',
                $purchaseReturn->status->value,
                'submitted'
            );
        }

        return $this->executeInTransaction('approve', function () use ($purchaseReturn, $userId) {
            $purchaseReturn->transitionTo(DocumentStatus::Approved, $userId);

            // Process approval side effects via pipeline
            $this->approvalPipeline->process($purchaseReturn);

            return $purchaseReturn->fresh(['items', 'journalEntry']);
        }, ['purchase_return_id' => $purchaseReturn->id]);
    }

    public function reject(PurchaseReturn $purchaseReturn, ?string $reason = null, ?int $userId = null): PurchaseReturn
    {
        if (! $purchaseReturn->stateMachine()->canReject()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Purchase Return',
                'ditolak',
                $purchaseReturn->status->value,
                'submitted'
            );
        }

        $purchaseReturn->transitionTo(DocumentStatus::Rejected, $userId, ['rejection_reason' => $reason]);

        return $purchaseReturn->fresh(['items', 'contact']);
    }

    public function complete(PurchaseReturn $purchaseReturn, ?int $userId = null): PurchaseReturn
    {
        if (! $purchaseReturn->stateMachine()->canComplete()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Purchase Return',
                'diselesaikan',
                $purchaseReturn->status->value,
                'approved'
            );
        }

        $purchaseReturn->transitionTo(DocumentStatus::Completed, $userId);

        return $purchaseReturn->fresh(['items', 'contact']);
    }

    public function cancel(PurchaseReturn $purchaseReturn, ?string $reason = null, ?int $userId = null): PurchaseReturn
    {
        if (! $purchaseReturn->stateMachine()->canCancel()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Purchase Return',
                'dibatalkan',
                $purchaseReturn->status->value,
                'draft, submitted, atau approved'
            );
        }

        $purchaseReturn->transitionTo(DocumentStatus::Cancelled, $userId, ['cancellation_reason' => $reason]);

        return $purchaseReturn->fresh(['items', 'contact']);
    }

    /**
     * Get purchase returns for a bill.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, PurchaseReturn>
     */
    public function getForBill(Bill $bill): \Illuminate\Database\Eloquent\Collection
    {
        return PurchaseReturn::query()
            ->where('bill_id', $bill->id)
            ->with(['items', 'creator'])
            ->orderBy('return_date', 'desc')
            ->get();
    }

    /**
     * Get statistics for purchase returns.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(?string $startDate = null, ?string $endDate = null): array
    {
        $query = PurchaseReturn::query();

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
