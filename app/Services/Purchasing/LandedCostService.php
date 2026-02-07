<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Contracts\Accounting\AccountLookupServiceInterface;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Purchasing\LandedCostServiceInterface;
use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Inventory\InventoryCostLayer;
use App\Models\Inventory\ProductStock;
use App\Models\Purchasing\GoodsReceiptNote;
use App\Models\Purchasing\LandedCost;
use App\Models\Purchasing\LandedCostAllocation;
use App\Services\Accounting\JournalService;
use App\Services\Base\BaseService;
use Illuminate\Support\Collection;

class LandedCostService extends BaseService implements LandedCostServiceInterface
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        private JournalService $journalService,
        private AccountLookupServiceInterface $accountLookup,
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    public function create(GoodsReceiptNote $grn, array $data): LandedCost
    {
        if ($grn->isCancelled()) {
            throw BusinessRuleException::operationNotAllowed(
                'landed-cost',
                'Tidak dapat menambah landed cost pada GRN yang dibatalkan.'
            );
        }

        return LandedCost::create([
            'goods_receipt_note_id' => $grn->id,
            'description' => $data['description'],
            'cost_type' => $data['cost_type'],
            'total_amount' => $data['total_amount'],
            'allocation_method' => $data['allocation_method'] ?? 'by_value',
            'expense_account_id' => $data['expense_account_id'],
            'status' => 'draft',
            'created_by' => $this->getUserId(),
        ]);
    }

    public function apply(LandedCost $landedCost): LandedCost
    {
        if (! $landedCost->isDraft()) {
            throw BusinessRuleException::operationNotAllowed(
                'landed-cost',
                'Hanya landed cost dengan status draft yang dapat di-apply.'
            );
        }

        $grn = $landedCost->goodsReceiptNote()->with('items')->first();

        if ($grn->isCancelled()) {
            throw BusinessRuleException::operationNotAllowed(
                'landed-cost',
                'Tidak dapat apply landed cost pada GRN yang dibatalkan.'
            );
        }

        $items = $grn->items->filter(fn ($item) => $item->quantity_received > 0);

        if ($items->isEmpty()) {
            throw BusinessRuleException::operationNotAllowed(
                'landed-cost',
                'GRN tidak memiliki item yang diterima.'
            );
        }

        return $this->executeInTransaction('apply_landed_cost', function () use ($landedCost, $grn, $items) {
            // Calculate allocation ratios
            $allocations = $this->calculateAllocations($landedCost, $items);

            // Apply to cost layers and product stocks
            $this->adjustInventoryCosts($allocations, $grn);

            // Create journal entry: Dr Inventory / Cr Expense
            $inventoryAccount = $this->accountLookup->findByCodeOrFail(
                config('accounting.default_accounts.inventory', '1-1400'),
                'Persediaan Barang Dagangan'
            );

            $expenseAccount = $this->accountLookup->findByIdOrFail(
                $landedCost->expense_account_id,
                'Akun biaya landed cost'
            );

            $journalEntry = $this->journalService->createEntry([
                'entry_date' => now()->toDateString(),
                'description' => "Landed cost ({$landedCost->cost_type}): {$landedCost->description} - GRN {$grn->grn_number}",
                'reference' => $landedCost->landed_cost_number,
                'source_type' => 'landed_cost',
                'source_id' => $landedCost->id,
                'lines' => [
                    [
                        'account_id' => $inventoryAccount->id,
                        'debit' => $landedCost->total_amount,
                        'credit' => 0,
                        'description' => "Kapitalisasi {$landedCost->cost_type} ke persediaan - {$grn->grn_number}",
                    ],
                    [
                        'account_id' => $expenseAccount->id,
                        'debit' => 0,
                        'credit' => $landedCost->total_amount,
                        'description' => "Reklasifikasi {$landedCost->cost_type} ke persediaan - {$grn->grn_number}",
                    ],
                ],
            ], autoPost: true);

            $landedCost->update([
                'journal_entry_id' => $journalEntry->id,
                'status' => 'applied',
            ]);

            return $landedCost->fresh(['allocations', 'journalEntry']);
        }, ['landed_cost_id' => $landedCost->id]);
    }

    public function reverse(LandedCost $landedCost): LandedCost
    {
        if (! $landedCost->isApplied()) {
            throw BusinessRuleException::operationNotAllowed(
                'landed-cost',
                'Hanya landed cost yang sudah di-apply yang dapat di-reverse.'
            );
        }

        return $this->executeInTransaction('reverse_landed_cost', function () use ($landedCost) {
            $grn = $landedCost->goodsReceiptNote;
            $allocations = $landedCost->allocations;

            // Restore original costs
            $this->restoreInventoryCosts($allocations, $grn);

            // Reverse the journal entry
            if ($landedCost->journalEntry) {
                $this->journalService->reverseEntry(
                    $landedCost->journalEntry,
                    "Pembatalan landed cost {$landedCost->landed_cost_number}"
                );
            }

            $landedCost->update(['status' => 'reversed']);

            return $landedCost->fresh(['allocations', 'journalEntry']);
        }, ['landed_cost_id' => $landedCost->id]);
    }

    /**
     * Calculate allocation amounts for each GRN item.
     *
     * @param  Collection<int, \App\Models\Purchasing\GoodsReceiptNoteItem>  $items
     * @return Collection<int, LandedCostAllocation>
     */
    private function calculateAllocations(LandedCost $landedCost, Collection $items): Collection
    {
        $totalAmount = $landedCost->total_amount;
        $method = $landedCost->allocation_method;

        if ($method === 'by_quantity') {
            $totalQty = $items->sum('quantity_received');

            return $this->distributeByRatio($landedCost, $items, $totalAmount, function ($item) use ($totalQty) {
                return $totalQty > 0 ? $item->quantity_received / $totalQty : 0;
            });
        }

        // Default: by_value
        $totalValue = $items->sum(fn ($item) => $item->unit_price * $item->quantity_received);

        return $this->distributeByRatio($landedCost, $items, $totalAmount, function ($item) use ($totalValue) {
            $itemValue = $item->unit_price * $item->quantity_received;

            return $totalValue > 0 ? $itemValue / $totalValue : 0;
        });
    }

    /**
     * Distribute total amount by ratio, ensuring last item gets remainder.
     *
     * @param  Collection<int, \App\Models\Purchasing\GoodsReceiptNoteItem>  $items
     * @param  callable(\App\Models\Purchasing\GoodsReceiptNoteItem): float  $ratioFn
     * @return Collection<int, LandedCostAllocation>
     */
    private function distributeByRatio(LandedCost $landedCost, Collection $items, int $totalAmount, callable $ratioFn): Collection
    {
        $allocations = collect();
        $allocatedSoFar = 0;
        $itemCount = $items->count();
        $index = 0;

        foreach ($items as $item) {
            $index++;
            $isLast = ($index === $itemCount);

            if ($isLast) {
                // Last item gets remainder to prevent rounding drift
                $allocated = $totalAmount - $allocatedSoFar;
            } else {
                $ratio = $ratioFn($item);
                $allocated = (int) round($totalAmount * $ratio);
                $allocatedSoFar += $allocated;
            }

            $previousUnitCost = $item->unit_price;
            $allocatedPerUnit = $item->quantity_received > 0
                ? (int) round($allocated / $item->quantity_received)
                : 0;
            $newUnitCost = $previousUnitCost + $allocatedPerUnit;

            $allocation = LandedCostAllocation::create([
                'landed_cost_id' => $landedCost->id,
                'goods_receipt_note_item_id' => $item->id,
                'product_id' => $item->product_id,
                'allocated_amount' => $allocated,
                'previous_unit_cost' => $previousUnitCost,
                'new_unit_cost' => $newUnitCost,
            ]);

            $allocations->push($allocation);
        }

        return $allocations;
    }

    /**
     * Adjust FIFO cost layers and weighted average costs.
     *
     * @param  Collection<int, LandedCostAllocation>  $allocations
     */
    private function adjustInventoryCosts(Collection $allocations, GoodsReceiptNote $grn): void
    {
        foreach ($allocations as $allocation) {
            // FIFO: adjust cost layer for this GRN + product
            $costLayer = InventoryCostLayer::where('reference_type', GoodsReceiptNote::class)
                ->where('reference_id', $grn->id)
                ->where('product_id', $allocation->product_id)
                ->first();

            if ($costLayer) {
                $costLayer->unit_cost += (int) round($allocation->allocated_amount / max(1, $costLayer->quantity));
                $costLayer->save();
            }

            // Weighted average: adjust ProductStock
            $productStock = ProductStock::where('product_id', $allocation->product_id)
                ->where('warehouse_id', $grn->warehouse_id)
                ->first();

            if ($productStock && $productStock->quantity > 0) {
                $currentTotalValue = $productStock->quantity * $productStock->average_cost;
                $newTotalValue = $currentTotalValue + $allocation->allocated_amount;
                $productStock->average_cost = (int) round($newTotalValue / $productStock->quantity);
                $productStock->total_value = $productStock->quantity * $productStock->average_cost;
                $productStock->save();
            }
        }
    }

    /**
     * Restore original costs when reversing a landed cost.
     *
     * @param  Collection<int, LandedCostAllocation>  $allocations
     */
    private function restoreInventoryCosts(Collection $allocations, GoodsReceiptNote $grn): void
    {
        foreach ($allocations as $allocation) {
            // FIFO: restore cost layer
            $costLayer = InventoryCostLayer::where('reference_type', GoodsReceiptNote::class)
                ->where('reference_id', $grn->id)
                ->where('product_id', $allocation->product_id)
                ->first();

            if ($costLayer) {
                $costLayer->unit_cost -= (int) round($allocation->allocated_amount / max(1, $costLayer->quantity));
                $costLayer->save();
            }

            // Weighted average: restore ProductStock
            $productStock = ProductStock::where('product_id', $allocation->product_id)
                ->where('warehouse_id', $grn->warehouse_id)
                ->first();

            if ($productStock && $productStock->quantity > 0) {
                $currentTotalValue = $productStock->quantity * $productStock->average_cost;
                $newTotalValue = $currentTotalValue - $allocation->allocated_amount;
                $productStock->average_cost = (int) round($newTotalValue / $productStock->quantity);
                $productStock->total_value = $productStock->quantity * $productStock->average_cost;
                $productStock->save();
            }
        }
    }
}
