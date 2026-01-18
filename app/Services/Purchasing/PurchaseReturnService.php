<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Contracts\Services\Domains\PurchaseReturnServiceInterface;
use App\Domain\Purchasing\PurchaseReturns\Handlers\PurchaseReturnApprovalPipeline;
use App\Enums\DocumentStatus;
use App\Models\Purchasing\Bill;
use App\Models\Purchasing\PurchaseReturn;
use App\Models\Purchasing\PurchaseReturnItem;
use App\Services\Base\AbstractDocumentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PurchaseReturnService extends AbstractDocumentService implements PurchaseReturnServiceInterface
{
    public function __construct(
        private \App\Contracts\Services\Domain\DocumentNumberGeneratorInterface $numberGenerator,
        private PurchaseReturnApprovalPipeline $approvalPipeline
    ) {}

    protected function getModelClass(): string
    {
        return PurchaseReturn::class;
    }

    protected function getItemRelation(): string
    {
        return 'items';
    }

    protected function generateDocumentNumber(?Model $context = null): string
    {
        $prefix = 'PR-'.now()->format('Ym').'-';

        return $this->numberGenerator->generate($prefix, 'purchase_returns', 'return_number');
    }

    protected function getDocumentNumberField(): string
    {
        return 'return_number';
    }

    protected function getInitialStatus(): string
    {
        return DocumentStatus::Draft->value;
    }

    protected function getEagerLoadRelations(): array
    {
        return ['items', 'contact', 'bill', 'warehouse'];
    }

    public function create(array $data): PurchaseReturn
    {
        /** @var PurchaseReturn $result */
        $result = parent::create($data);

        return $result;
    }

    public function update(Model $document, array $data): PurchaseReturn
    {
        /** @var PurchaseReturn $document */
        /** @var PurchaseReturn $result */
        $result = parent::update($document, $data);

        return $result;
    }

    protected function validateEditable(Model $document): void
    {
        /** @var PurchaseReturn $document */
        if (! $document->isEditable()) {
            throw new InvalidArgumentException('Purchase return can only be edited in draft status.');
        }
    }

    protected function validateDeletable(Model $document): void
    {
        /** @var PurchaseReturn $document */
        if (! $document->isEditable()) {
            throw new InvalidArgumentException('Only draft purchase returns can be deleted.');
        }
    }

    protected function createItems(Model $document, array $items): void
    {
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
        return DB::transaction(function () use ($bill, $data) {
            $purchaseReturn = new PurchaseReturn([
                'bill_id' => $bill->id,
                'contact_id' => $bill->contact_id,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'return_date' => $data['return_date'] ?? now()->toDateString(),
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'tax_rate' => $bill->tax_rate,
                'created_by' => $data['created_by'] ?? auth()->id(),
            ]);
            $purchaseReturn->return_number = $this->numberGenerator->generate('PR-'.now()->format('Ym').'-', 'purchase_returns', 'return_number');
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
        });
    }

    public function submit(PurchaseReturn $purchaseReturn, ?int $userId = null): PurchaseReturn
    {
        if (! $purchaseReturn->stateMachine()->canSubmit()) {
            throw new InvalidArgumentException('Purchase return cannot be submitted. Ensure it has items and is in draft status.');
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
            throw new InvalidArgumentException('Only submitted purchase returns can be approved.');
        }

        return DB::transaction(function () use ($purchaseReturn, $userId) {
            $purchaseReturn->transitionTo(DocumentStatus::Approved, $userId);

            // Process approval side effects via pipeline
            $this->approvalPipeline->process($purchaseReturn);

            return $purchaseReturn->fresh(['items', 'journalEntry']);
        });
    }

    public function reject(PurchaseReturn $purchaseReturn, ?string $reason = null, ?int $userId = null): PurchaseReturn
    {
        if (! $purchaseReturn->stateMachine()->canReject()) {
            throw new InvalidArgumentException('Only submitted purchase returns can be rejected.');
        }

        $purchaseReturn->transitionTo(DocumentStatus::Rejected, $userId, ['rejection_reason' => $reason]);

        return $purchaseReturn->fresh(['items', 'contact']);
    }

    public function complete(PurchaseReturn $purchaseReturn, ?int $userId = null): PurchaseReturn
    {
        if (! $purchaseReturn->stateMachine()->canComplete()) {
            throw new InvalidArgumentException('Only approved purchase returns can be completed.');
        }

        $purchaseReturn->transitionTo(DocumentStatus::Completed, $userId);

        return $purchaseReturn->fresh(['items', 'contact']);
    }

    public function cancel(PurchaseReturn $purchaseReturn, ?string $reason = null, ?int $userId = null): PurchaseReturn
    {
        if (! $purchaseReturn->stateMachine()->canCancel()) {
            throw new InvalidArgumentException('Only draft, submitted, or approved purchase returns can be cancelled.');
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
