<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Contracts\Accounting\Strategies\InventoryAccountingStrategy;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Inventory\InventoryServiceInterface;
use App\Contracts\Inventory\StockOpnameServiceInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Enums\DocumentStatus;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\StockOpname;
use App\Models\Inventory\StockOpnameItem;
use App\Models\Inventory\Warehouse;
use App\Services\Base\BaseService;

class StockOpnameService extends BaseService implements StockOpnameServiceInterface
{
    public function __construct(
        private InventoryServiceInterface $inventoryService,
        private InventoryAccountingStrategy $inventoryStrategy,
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Create a new stock opname.
     */
    public function create(array $data): StockOpname
    {
        return $this->executeInTransaction('create', function () use ($data) {
            $opname = StockOpname::create([
                'warehouse_id' => $data['warehouse_id'],
                'opname_date' => $data['opname_date'] ?? now()->toDateString(),
                'status' => DocumentStatus::Draft,
                'name' => $data['name'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'] ?? $this->getUserId(),
            ]);

            return $opname;
        }, ['warehouse_id' => $data['warehouse_id']]);
    }

    /**
     * Update a stock opname.
     */
    public function update(StockOpname $opname, array $data): StockOpname
    {
        if (! $opname->canEdit()) {
            throw \App\Exceptions\Domain\DocumentLockedException::cannotEdit($opname, 'Stock opname tidak dapat diubah pada status ini.');
        }

        $opname->update([
            'name' => $data['name'] ?? $opname->name,
            'notes' => $data['notes'] ?? $opname->notes,
            'opname_date' => $data['opname_date'] ?? $opname->opname_date,
        ]);

        return $opname->fresh();
    }

    /**
     * Delete a stock opname.
     */
    public function delete(StockOpname $opname): void
    {
        if (! $opname->canDelete()) {
            throw \App\Exceptions\Domain\DocumentLockedException::cannotDelete($opname, 'Stock opname tidak dapat dihapus pada status ini.');
        }

        $opname->items()->delete();
        $opname->delete();
    }

    /**
     * Generate items from warehouse stock.
     */
    public function generateItems(StockOpname $opname): StockOpname
    {
        if (! $opname->isDraft()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Stock Opname',
                'generate item',
                $opname->status->value,
                'draft'
            );
        }

        return $this->executeInTransaction('generate_items', function () use ($opname) {
            // Clear existing items
            $opname->items()->delete();

            // Get all products with stock in this warehouse
            $stocks = ProductStock::where('warehouse_id', $opname->warehouse_id)
                ->where('quantity', '>', 0)
                ->with('product')
                ->get();

            foreach ($stocks as $stock) {
                // Only include products that track inventory
                if (! $stock->product || ! $stock->product->track_inventory) {
                    continue;
                }

                StockOpnameItem::create([
                    'stock_opname_id' => $opname->id,
                    'product_id' => $stock->product_id,
                    'system_quantity' => $stock->quantity,
                    'system_cost' => $stock->average_cost,
                    'system_value' => $stock->quantity * $stock->average_cost,
                ]);
            }

            $opname->updateTotals();

            return $opname->fresh(['items']);
        }, ['opname_id' => $opname->id]);
    }

    /**
     * Add an item manually.
     */
    public function addItem(StockOpname $opname, array $data): StockOpnameItem
    {
        if (! $opname->canEdit()) {
            throw \App\Exceptions\Domain\DocumentLockedException::cannotEdit($opname, 'Item tidak dapat ditambahkan pada status ini.');
        }

        $product = Product::findOrFail($data['product_id']);

        if (! $product->track_inventory) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'menambah produk',
                'Produk tidak melacak inventori'
            );
        }

        // Check if product already exists
        $existing = $opname->items()->where('product_id', $product->id)->first();
        if ($existing) {
            throw \App\Exceptions\Domain\BusinessRuleException::duplicateEntry(
                'Item Stock Opname',
                'produk',
                $product->name
            );
        }

        // Get current stock
        $stock = ProductStock::where('warehouse_id', $opname->warehouse_id)
            ->where('product_id', $product->id)
            ->first();

        $systemQty = $stock ? $stock->quantity : 0;
        $systemCost = $stock ? $stock->average_cost : $product->purchase_price;

        $item = StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'product_id' => $product->id,
            'system_quantity' => $systemQty,
            'system_cost' => $systemCost,
            'system_value' => $systemQty * $systemCost,
        ]);

        $opname->updateTotals();

        return $item;
    }

    /**
     * Update an item (record count).
     */
    public function updateItem(StockOpnameItem $item, array $data): StockOpnameItem
    {
        $opname = $item->stockOpname;

        if (! in_array($opname->status, [DocumentStatus::Draft, DocumentStatus::Counting], true)) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Stock Opname',
                'ubah item',
                $opname->status->value,
                'draft atau counting'
            );
        }

        if (isset($data['counted_quantity'])) {
            $item->recordCount(
                (int) $data['counted_quantity'],
                $data['notes'] ?? null
            );
        } else {
            $item->update([
                'notes' => $data['notes'] ?? $item->notes,
            ]);
        }

        return $item->fresh();
    }

    /**
     * Remove an item.
     */
    public function removeItem(StockOpnameItem $item): void
    {
        $opname = $item->stockOpname;

        if (! $opname->canEdit()) {
            throw \App\Exceptions\Domain\DocumentLockedException::cannotEdit($opname, 'Item tidak dapat dihapus pada status ini.');
        }

        $item->delete();
        $opname->updateTotals();
    }

    /**
     * Start counting workflow.
     */
    public function startCounting(StockOpname $opname, int $userId): StockOpname
    {
        $stateMachine = $opname->stateMachine();

        if (! $stateMachine->canStartCounting()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Stock Opname',
                'mulai penghitungan',
                $opname->status->value,
                'draft dengan item'
            );
        }

        // Refresh system quantities before starting
        return $this->executeInTransaction('start_counting', function () use ($opname, $userId) {
            foreach ($opname->items as $item) {
                $stock = ProductStock::where('warehouse_id', $opname->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->first();

                if ($stock) {
                    $item->captureSystemQuantities($stock);
                }
            }

            // Use state machine for status transition
            $opname->transitionTo(DocumentStatus::Counting, $userId);

            $opname->updateTotals();

            return $opname->fresh();
        }, ['opname_id' => $opname->id]);
    }

    /**
     * Submit for review.
     */
    public function submitForReview(StockOpname $opname, int $userId): StockOpname
    {
        $stateMachine = $opname->stateMachine();

        if (! $stateMachine->canSubmitForReview()) {
            $uncounted = $opname->items()->whereNull('counted_quantity')->count();
            if ($uncounted > 0) {
                throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                    'submit stock opname',
                    "Masih ada {$uncounted} item yang belum dihitung"
                );
            }
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Stock Opname',
                'submit untuk review',
                $opname->status->value,
                'counting selesai'
            );
        }

        // Use state machine for status transition
        $opname->transitionTo(DocumentStatus::Reviewed, $userId);

        return $opname->fresh();
    }

    /**
     * Approve and apply adjustments.
     *
     * Creates inventory movements for each variance item and
     * creates journal entry for the total adjustment value via strategy.
     */
    public function approve(StockOpname $opname, int $userId): StockOpname
    {
        $stateMachine = $opname->stateMachine();

        if (! $stateMachine->canApprove()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Stock Opname',
                'approve',
                $opname->status->value,
                'reviewed'
            );
        }

        return $this->executeInTransaction('approve', function () use ($opname, $userId) {
            $warehouse = Warehouse::findOrFail($opname->warehouse_id);

            // Apply adjustments for items with variance
            foreach ($opname->items()->where('variance_quantity', '!=', 0)->get() as $item) {
                $product = $item->product;

                // Create inventory adjustment
                $this->inventoryService->adjust(
                    $product,
                    $warehouse,
                    $item->counted_quantity,
                    null, // newUnitCost - keep existing
                    "Stock Opname: {$opname->opname_number}".($item->notes ? " - {$item->notes}" : ''),
                    StockOpname::class,
                    $opname->id
                );
            }

            // Create adjustment journal entry (if strategy is configured)
            // The journal entry links back to stock_opname via source_id
            $this->inventoryStrategy->onStockAdjustment($opname);

            // Use state machine: REVIEWED -> APPROVED -> COMPLETED
            $opname->transitionTo(DocumentStatus::Approved, $userId);
            $opname->transitionTo(DocumentStatus::Completed, $userId);

            return $opname->fresh();
        }, ['opname_id' => $opname->id]);
    }

    /**
     * Reject and return to counting.
     */
    public function reject(StockOpname $opname, int $userId, ?string $reason = null): StockOpname
    {
        $stateMachine = $opname->stateMachine();

        if (! $stateMachine->canReject()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Stock Opname',
                'reject',
                $opname->status->value,
                'reviewed'
            );
        }

        // Update notes with rejection reason before transition
        if ($reason) {
            $opname->update([
                'notes' => $opname->notes ? $opname->notes."\n\nDitolak: ".$reason : 'Ditolak: '.$reason,
            ]);
        }

        // Use state machine for status transition
        $opname->transitionTo(DocumentStatus::Counting, $userId);

        // Clear review fields since we're going back to counting
        $opname->update([
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        return $opname->fresh();
    }

    /**
     * Cancel stock opname.
     */
    public function cancel(StockOpname $opname, int $userId): StockOpname
    {
        $stateMachine = $opname->stateMachine();

        if (! $stateMachine->canCancel()) {
            throw \App\Exceptions\Domain\StateTransitionException::actionNotAvailable(
                'dibatalkan',
                $opname->status->value
            );
        }

        // Use state machine for status transition
        $opname->transitionTo(DocumentStatus::Cancelled, $userId);

        return $opname->fresh();
    }

    /**
     * Get variance report.
     */
    public function getVarianceReport(StockOpname $opname): array
    {
        $items = $opname->items()->with('product')->get();

        $summary = [
            'total_items' => $items->count(),
            'counted_items' => $items->whereNotNull('counted_quantity')->count(),
            'items_with_variance' => $items->where('variance_quantity', '!=', 0)->count(),
            'items_with_surplus' => $items->where('variance_quantity', '>', 0)->count(),
            'items_with_shortage' => $items->where('variance_quantity', '<', 0)->count(),
            'total_system_value' => $items->sum('system_value'),
            'total_counted_value' => $items->sum(fn ($item) => ($item->counted_quantity ?? 0) * $item->system_cost),
            'total_variance_qty' => $items->sum('variance_quantity'),
            'total_variance_value' => $items->sum('variance_value'),
        ];

        $variances = $items->where('variance_quantity', '!=', 0)
            ->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product_sku' => $item->product->sku,
                'product_name' => $item->product->name,
                'system_quantity' => $item->system_quantity,
                'counted_quantity' => $item->counted_quantity,
                'variance_quantity' => $item->variance_quantity,
                'variance_percentage' => $item->getVariancePercentage(),
                'system_cost' => $item->system_cost,
                'variance_value' => $item->variance_value,
                'notes' => $item->notes,
            ])
            ->values()
            ->toArray();

        /** @var \Carbon\Carbon $opnameDate */
        $opnameDate = $opname->opname_date;

        return [
            'stock_opname' => [
                'id' => $opname->id,
                'opname_number' => $opname->opname_number,
                'warehouse' => $opname->warehouse->name,
                'opname_date' => $opnameDate->toDateString(),
                'status' => $opname->status,
            ],
            'summary' => $summary,
            'variances' => $variances,
        ];
    }
}
