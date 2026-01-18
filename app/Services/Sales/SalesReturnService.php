<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Contracts\Services\Domains\SalesReturnServiceInterface;
use App\Domain\Sales\SalesReturns\Handlers\SalesReturnApprovalPipeline;
use App\Enums\DocumentStatus;
use App\Models\Sales\Invoice;
use App\Models\Sales\SalesReturn;
use App\Models\Sales\SalesReturnItem;
use App\Services\Base\AbstractDocumentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SalesReturnService extends AbstractDocumentService implements SalesReturnServiceInterface
{
    public function __construct(
        private \App\Contracts\Services\Domain\DocumentNumberGeneratorInterface $numberGenerator,
        private SalesReturnApprovalPipeline $approvalPipeline
    ) {}

    protected function getModelClass(): string
    {
        return SalesReturn::class;
    }

    protected function getItemRelation(): string
    {
        return 'items';
    }

    protected function generateDocumentNumber(?Model $context = null): string
    {
        $prefix = 'SR-'.now()->format('Ym').'-';

        return $this->numberGenerator->generate($prefix, 'sales_returns', 'return_number');
    }

    protected function getDocumentNumberField(): string
    {
        return 'return_number';
    }

    protected function getInitialStatus(): string
    {
        return DocumentStatus::Draft->value;
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

    public function create(array $data): SalesReturn
    {
        /** @var SalesReturn $result */
        $result = parent::create($data);

        return $result;
    }

    public function update(Model $document, array $data): SalesReturn
    {
        /** @var SalesReturn $document */
        /** @var SalesReturn $result */
        $result = parent::update($document, $data);

        return $result;
    }

    protected function validateEditable(Model $document): void
    {
        /** @var SalesReturn $document */
        if (! $document->canBeEdited()) {
            throw new \InvalidArgumentException('Sales return can only be edited in draft status.');
        }
    }

    protected function validateDeletable(Model $document): void
    {
        /** @var SalesReturn $document */
        if (! $document->canBeEdited()) {
            throw new \InvalidArgumentException('Only draft sales returns can be deleted.');
        }
    }

    protected function createItems(Model $document, array $items): void
    {
        foreach ($items as $itemData) {
            $item = new SalesReturnItem($itemData);
            $item->sales_return_id = $document->id;
            $item->calculateLineTotal();
            $item->save();
        }
    }

    /**
     * Create sales return from invoice.
     */
    public function createFromInvoice(Invoice $invoice, array $data = []): SalesReturn
    {
        return DB::transaction(function () use ($invoice, $data) {
            $defaults = [
                'invoice_id' => $invoice->id,
                'contact_id' => $invoice->contact_id,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'return_date' => $data['return_date'] ?? now()->toDateString(),
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'tax_rate' => $invoice->tax_rate,
                'created_by' => $data['created_by'] ?? auth()->id(),
            ];

            $defaults['return_number'] = $this->generateDocumentNumber();

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
            $salesReturn->calculateTotals();
            $salesReturn->save();

            return $salesReturn->fresh(['items', 'contact', 'invoice', 'warehouse']);
        });
    }

    /**
     * Submit a sales return for approval.
     */
    public function submit(SalesReturn $salesReturn, ?int $userId = null): SalesReturn
    {
        if (! $salesReturn->canBeSubmitted()) {
            throw new \InvalidArgumentException('Sales return cannot be submitted. Ensure it has items and is in draft status.');
        }

        $salesReturn->transitionTo(DocumentStatus::Submitted, $userId);

        return $salesReturn->fresh();
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
            throw new \InvalidArgumentException('Only submitted sales returns can be approved.');
        }

        return DB::transaction(function () use ($salesReturn, $userId) {
            $salesReturn->transitionTo(DocumentStatus::Approved, $userId);

            // Process approval side effects via pipeline
            $this->approvalPipeline->process($salesReturn);

            return $salesReturn->fresh(['items', 'journalEntry']);
        });
    }

    /**
     * Reject a sales return.
     */
    public function reject(SalesReturn $salesReturn, ?string $reason = null, ?int $userId = null): SalesReturn
    {
        if (! $salesReturn->canBeRejected()) {
            throw new \InvalidArgumentException('Only submitted sales returns can be rejected.');
        }

        $salesReturn->transitionTo(DocumentStatus::Rejected, $userId, [
            'rejection_reason' => $reason,
        ]);

        return $salesReturn->fresh();
    }

    /**
     * Complete a sales return (after approved and inventory processed).
     */
    public function complete(SalesReturn $salesReturn, ?int $userId = null): SalesReturn
    {
        if (! $salesReturn->canBeCompleted()) {
            throw new \InvalidArgumentException('Only approved sales returns can be completed.');
        }

        $salesReturn->transitionTo(DocumentStatus::Completed, $userId);

        return $salesReturn->fresh();
    }

    /**
     * Cancel a sales return.
     */
    public function cancel(SalesReturn $salesReturn, ?string $reason = null): SalesReturn
    {
        if (! $salesReturn->canBeCancelled()) {
            throw new \InvalidArgumentException('Only draft or submitted sales returns can be cancelled.');
        }

        $salesReturn->transitionTo(DocumentStatus::Cancelled, $userId ?? null, [
            'cancellation_reason' => $reason,
        ]);

        return $salesReturn->fresh();
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
