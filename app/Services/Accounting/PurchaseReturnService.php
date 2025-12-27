<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\Bill;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Product;
use App\Models\Accounting\PurchaseReturn;
use App\Models\Accounting\PurchaseReturnItem;
use Illuminate\Support\Facades\DB;

class PurchaseReturnService
{
    public function __construct(
        private InventoryService $inventoryService,
        private JournalService $journalService
    ) {}

    /**
     * Create a new purchase return.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PurchaseReturn
    {
        return DB::transaction(function () use ($data) {
            $purchaseReturn = new PurchaseReturn($data);
            $purchaseReturn->return_number = PurchaseReturn::generateReturnNumber();
            $purchaseReturn->save();

            // Create items
            if (! empty($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    $this->createItem($purchaseReturn, $itemData);
                }

                // Recalculate totals
                $purchaseReturn->calculateTotals();
                $purchaseReturn->save();
            }

            return $purchaseReturn->fresh(['items', 'contact', 'bill', 'warehouse']);
        });
    }

    /**
     * Create purchase return from bill.
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
            $purchaseReturn->return_number = PurchaseReturn::generateReturnNumber();
            $purchaseReturn->save();

            // Create items from bill items
            foreach ($bill->items as $billItem) {
                $item = new PurchaseReturnItem;
                $item->purchase_return_id = $purchaseReturn->id;
                $item->fillFromBillItem($billItem);
                $item->save();
            }

            // Recalculate totals
            $purchaseReturn->calculateTotals();
            $purchaseReturn->save();

            return $purchaseReturn->fresh(['items', 'contact', 'bill', 'warehouse']);
        });
    }

    /**
     * Update a purchase return.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(PurchaseReturn $purchaseReturn, array $data): PurchaseReturn
    {
        if (! $purchaseReturn->canBeEdited()) {
            throw new \InvalidArgumentException('Purchase return can only be edited in draft status.');
        }

        return DB::transaction(function () use ($purchaseReturn, $data) {
            $purchaseReturn->fill($data);
            $purchaseReturn->save();

            // Update items if provided
            if (isset($data['items'])) {
                // Remove existing items and recreate
                $purchaseReturn->items()->delete();

                foreach ($data['items'] as $itemData) {
                    $this->createItem($purchaseReturn, $itemData);
                }

                // Recalculate totals
                $purchaseReturn->calculateTotals();
                $purchaseReturn->save();
            }

            return $purchaseReturn->fresh(['items', 'contact', 'bill', 'warehouse']);
        });
    }

    /**
     * Delete a purchase return.
     */
    public function delete(PurchaseReturn $purchaseReturn): bool
    {
        if (! $purchaseReturn->canBeEdited()) {
            throw new \InvalidArgumentException('Only draft purchase returns can be deleted.');
        }

        return DB::transaction(function () use ($purchaseReturn) {
            $purchaseReturn->items()->delete();

            return $purchaseReturn->delete();
        });
    }

    /**
     * Submit a purchase return for approval.
     */
    public function submit(PurchaseReturn $purchaseReturn, ?int $userId = null): PurchaseReturn
    {
        if (! $purchaseReturn->canBeSubmitted()) {
            throw new \InvalidArgumentException('Purchase return cannot be submitted. Ensure it has items and is in draft status.');
        }

        $purchaseReturn->status = PurchaseReturn::STATUS_SUBMITTED;
        $purchaseReturn->submitted_by = $userId;
        $purchaseReturn->submitted_at = now();
        $purchaseReturn->save();

        return $purchaseReturn->fresh();
    }

    /**
     * Approve a purchase return.
     */
    public function approve(PurchaseReturn $purchaseReturn, ?int $userId = null): PurchaseReturn
    {
        if (! $purchaseReturn->canBeApproved()) {
            throw new \InvalidArgumentException('Only submitted purchase returns can be approved.');
        }

        return DB::transaction(function () use ($purchaseReturn, $userId) {
            $purchaseReturn->status = PurchaseReturn::STATUS_APPROVED;
            $purchaseReturn->approved_by = $userId;
            $purchaseReturn->approved_at = now();
            $purchaseReturn->save();

            // Process inventory (stock out - goods returned to supplier)
            if ($purchaseReturn->warehouse_id) {
                $this->processInventoryReturn($purchaseReturn);
            }

            // Create journal entry for the return
            $this->createReturnJournalEntry($purchaseReturn);

            return $purchaseReturn->fresh(['items', 'journalEntry']);
        });
    }

    /**
     * Reject a purchase return.
     */
    public function reject(PurchaseReturn $purchaseReturn, ?string $reason = null, ?int $userId = null): PurchaseReturn
    {
        if (! $purchaseReturn->canBeRejected()) {
            throw new \InvalidArgumentException('Only submitted purchase returns can be rejected.');
        }

        $purchaseReturn->status = PurchaseReturn::STATUS_CANCELLED;
        $purchaseReturn->rejected_by = $userId;
        $purchaseReturn->rejected_at = now();
        $purchaseReturn->rejection_reason = $reason;
        $purchaseReturn->save();

        return $purchaseReturn->fresh();
    }

    /**
     * Complete a purchase return (after approved and inventory processed).
     */
    public function complete(PurchaseReturn $purchaseReturn, ?int $userId = null): PurchaseReturn
    {
        if (! $purchaseReturn->canBeCompleted()) {
            throw new \InvalidArgumentException('Only approved purchase returns can be completed.');
        }

        $purchaseReturn->status = PurchaseReturn::STATUS_COMPLETED;
        $purchaseReturn->completed_by = $userId;
        $purchaseReturn->completed_at = now();
        $purchaseReturn->save();

        return $purchaseReturn->fresh();
    }

    /**
     * Cancel a purchase return.
     */
    public function cancel(PurchaseReturn $purchaseReturn, ?string $reason = null): PurchaseReturn
    {
        if (! $purchaseReturn->canBeCancelled()) {
            throw new \InvalidArgumentException('Only draft or submitted purchase returns can be cancelled.');
        }

        $purchaseReturn->status = PurchaseReturn::STATUS_CANCELLED;
        if ($reason) {
            $purchaseReturn->notes = ($purchaseReturn->notes ? $purchaseReturn->notes."\n" : '').'Dibatalkan: '.$reason;
        }
        $purchaseReturn->save();

        return $purchaseReturn->fresh();
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
            'draft_count' => $returns->where('status', PurchaseReturn::STATUS_DRAFT)->count(),
            'submitted_count' => $returns->where('status', PurchaseReturn::STATUS_SUBMITTED)->count(),
            'approved_count' => $returns->where('status', PurchaseReturn::STATUS_APPROVED)->count(),
            'completed_count' => $returns->where('status', PurchaseReturn::STATUS_COMPLETED)->count(),
            'cancelled_count' => $returns->where('status', PurchaseReturn::STATUS_CANCELLED)->count(),
            'total_value' => $returns->whereNotIn('status', [PurchaseReturn::STATUS_CANCELLED])->sum('total_amount'),
            'by_reason' => $returns->whereNotIn('status', [PurchaseReturn::STATUS_CANCELLED])
                ->groupBy('reason')
                ->map(fn ($group) => [
                    'count' => $group->count(),
                    'total' => $group->sum('total_amount'),
                ]),
        ];
    }

    /**
     * Create a purchase return item.
     *
     * @param  array<string, mixed>  $data
     */
    private function createItem(PurchaseReturn $purchaseReturn, array $data): PurchaseReturnItem
    {
        $item = new PurchaseReturnItem($data);
        $item->purchase_return_id = $purchaseReturn->id;
        $item->calculateLineTotal();
        $item->save();

        return $item;
    }

    /**
     * Process inventory for approved return (stock out).
     */
    private function processInventoryReturn(PurchaseReturn $purchaseReturn): void
    {
        $warehouse = $purchaseReturn->warehouse;

        foreach ($purchaseReturn->items as $item) {
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
                (int) $item->quantity,
                'Retur pembelian: '.$purchaseReturn->return_number,
                PurchaseReturn::class,
                $purchaseReturn->id
            );
        }
    }

    /**
     * Create journal entry for purchase return.
     * Debit: Accounts Payable (reduces payable)
     * Credit: Purchase Returns (reduces expense/COGS)
     * Credit: PPN Masukan (if applicable)
     */
    private function createReturnJournalEntry(PurchaseReturn $purchaseReturn): void
    {
        $purchaseReturnsAccount = Account::where('code', '5-2001')->first(); // Retur Pembelian
        $payableAccount = Account::where('code', '2-1100')->first(); // Utang Usaha
        $taxReceivableAccount = Account::where('code', '1-1300')->first(); // PPN Masukan

        // If linked to bill, use bill's payable account
        if ($purchaseReturn->bill && $purchaseReturn->bill->payable_account_id) {
            $payableAccount = Account::find($purchaseReturn->bill->payable_account_id) ?? $payableAccount;
        }

        $lines = [];

        // Debit: Accounts Payable (reduce what we owe)
        $lines[] = [
            'account_id' => $payableAccount->id,
            'description' => 'Pengurangan utang: '.$purchaseReturn->return_number,
            'debit' => $purchaseReturn->total_amount,
            'credit' => 0,
        ];

        // Credit: Purchase Returns (contra expense)
        if ($purchaseReturnsAccount) {
            $lines[] = [
                'account_id' => $purchaseReturnsAccount->id,
                'description' => 'Retur pembelian: '.$purchaseReturn->return_number,
                'debit' => 0,
                'credit' => $purchaseReturn->subtotal,
            ];
        }

        // Credit: PPN Masukan (reverse tax claimed)
        if ($purchaseReturn->tax_amount > 0 && $taxReceivableAccount) {
            $lines[] = [
                'account_id' => $taxReceivableAccount->id,
                'description' => 'PPN Retur: '.$purchaseReturn->return_number,
                'debit' => 0,
                'credit' => $purchaseReturn->tax_amount,
            ];
        }

        $entry = $this->journalService->createEntry([
            'entry_date' => $purchaseReturn->return_date->toDateString(),
            'description' => 'Retur pembelian: '.$purchaseReturn->return_number,
            'reference' => $purchaseReturn->return_number,
            'source_type' => JournalEntry::SOURCE_MANUAL,
            'source_id' => $purchaseReturn->id,
            'lines' => $lines,
        ], autoPost: true);

        $purchaseReturn->journal_entry_id = $entry->id;
        $purchaseReturn->save();
    }
}
