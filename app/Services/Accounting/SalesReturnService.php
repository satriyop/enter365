<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Product;
use App\Models\Accounting\SalesReturn;
use App\Models\Accounting\SalesReturnItem;
use Illuminate\Support\Facades\DB;

class SalesReturnService
{
    public function __construct(
        private InventoryService $inventoryService,
        private JournalService $journalService
    ) {}

    /**
     * Create a new sales return.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): SalesReturn
    {
        return DB::transaction(function () use ($data) {
            $salesReturn = new SalesReturn($data);
            $salesReturn->return_number = SalesReturn::generateReturnNumber();
            $salesReturn->save();

            // Create items
            if (! empty($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    $this->createItem($salesReturn, $itemData);
                }

                // Recalculate totals
                $salesReturn->calculateTotals();
                $salesReturn->save();
            }

            return $salesReturn->fresh(['items', 'contact', 'invoice', 'warehouse']);
        });
    }

    /**
     * Create sales return from invoice.
     */
    public function createFromInvoice(Invoice $invoice, array $data = []): SalesReturn
    {
        return DB::transaction(function () use ($invoice, $data) {
            $salesReturn = new SalesReturn([
                'invoice_id' => $invoice->id,
                'contact_id' => $invoice->contact_id,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'return_date' => $data['return_date'] ?? now()->toDateString(),
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'tax_rate' => $invoice->tax_rate,
                'created_by' => $data['created_by'] ?? auth()->id(),
            ]);
            $salesReturn->return_number = SalesReturn::generateReturnNumber();
            $salesReturn->save();

            // Create items from invoice items
            foreach ($invoice->items as $invoiceItem) {
                $item = new SalesReturnItem;
                $item->sales_return_id = $salesReturn->id;
                $item->fillFromInvoiceItem($invoiceItem);
                $item->save();
            }

            // Recalculate totals
            $salesReturn->calculateTotals();
            $salesReturn->save();

            return $salesReturn->fresh(['items', 'contact', 'invoice', 'warehouse']);
        });
    }

    /**
     * Update a sales return.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(SalesReturn $salesReturn, array $data): SalesReturn
    {
        if (! $salesReturn->canBeEdited()) {
            throw new \InvalidArgumentException('Sales return can only be edited in draft status.');
        }

        return DB::transaction(function () use ($salesReturn, $data) {
            $salesReturn->fill($data);
            $salesReturn->save();

            // Update items if provided
            if (isset($data['items'])) {
                // Remove existing items and recreate
                $salesReturn->items()->delete();

                foreach ($data['items'] as $itemData) {
                    $this->createItem($salesReturn, $itemData);
                }

                // Recalculate totals
                $salesReturn->calculateTotals();
                $salesReturn->save();
            }

            return $salesReturn->fresh(['items', 'contact', 'invoice', 'warehouse']);
        });
    }

    /**
     * Delete a sales return.
     */
    public function delete(SalesReturn $salesReturn): bool
    {
        if (! $salesReturn->canBeEdited()) {
            throw new \InvalidArgumentException('Only draft sales returns can be deleted.');
        }

        return DB::transaction(function () use ($salesReturn) {
            $salesReturn->items()->delete();

            return $salesReturn->delete();
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

        $salesReturn->status = SalesReturn::STATUS_SUBMITTED;
        $salesReturn->submitted_by = $userId;
        $salesReturn->submitted_at = now();
        $salesReturn->save();

        return $salesReturn->fresh();
    }

    /**
     * Approve a sales return.
     */
    public function approve(SalesReturn $salesReturn, ?int $userId = null): SalesReturn
    {
        if (! $salesReturn->canBeApproved()) {
            throw new \InvalidArgumentException('Only submitted sales returns can be approved.');
        }

        return DB::transaction(function () use ($salesReturn, $userId) {
            $salesReturn->status = SalesReturn::STATUS_APPROVED;
            $salesReturn->approved_by = $userId;
            $salesReturn->approved_at = now();
            $salesReturn->save();

            // Process inventory (stock in - goods returned from customer)
            if ($salesReturn->warehouse_id) {
                $this->processInventoryReturn($salesReturn);
            }

            // Create journal entry for the return
            $this->createReturnJournalEntry($salesReturn);

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

        $salesReturn->status = SalesReturn::STATUS_CANCELLED;
        $salesReturn->rejected_by = $userId;
        $salesReturn->rejected_at = now();
        $salesReturn->rejection_reason = $reason;
        $salesReturn->save();

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

        $salesReturn->status = SalesReturn::STATUS_COMPLETED;
        $salesReturn->completed_by = $userId;
        $salesReturn->completed_at = now();
        $salesReturn->save();

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

        $salesReturn->status = SalesReturn::STATUS_CANCELLED;
        if ($reason) {
            $salesReturn->notes = ($salesReturn->notes ? $salesReturn->notes."\n" : '').'Dibatalkan: '.$reason;
        }
        $salesReturn->save();

        return $salesReturn->fresh();
    }

    /**
     * Get sales returns for an invoice.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, SalesReturn>
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
     *
     * @return array<string, mixed>
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
            'draft_count' => $returns->where('status', SalesReturn::STATUS_DRAFT)->count(),
            'submitted_count' => $returns->where('status', SalesReturn::STATUS_SUBMITTED)->count(),
            'approved_count' => $returns->where('status', SalesReturn::STATUS_APPROVED)->count(),
            'completed_count' => $returns->where('status', SalesReturn::STATUS_COMPLETED)->count(),
            'cancelled_count' => $returns->where('status', SalesReturn::STATUS_CANCELLED)->count(),
            'total_value' => $returns->whereNotIn('status', [SalesReturn::STATUS_CANCELLED])->sum('total_amount'),
            'by_reason' => $returns->whereNotIn('status', [SalesReturn::STATUS_CANCELLED])
                ->groupBy('reason')
                ->map(fn ($group) => [
                    'count' => $group->count(),
                    'total' => $group->sum('total_amount'),
                ]),
        ];
    }

    /**
     * Create a sales return item.
     *
     * @param  array<string, mixed>  $data
     */
    private function createItem(SalesReturn $salesReturn, array $data): SalesReturnItem
    {
        $item = new SalesReturnItem($data);
        $item->sales_return_id = $salesReturn->id;
        $item->calculateLineTotal();
        $item->save();

        return $item;
    }

    /**
     * Process inventory for approved return (stock in).
     */
    private function processInventoryReturn(SalesReturn $salesReturn): void
    {
        $warehouse = $salesReturn->warehouse;

        foreach ($salesReturn->items as $item) {
            if (! $item->product_id) {
                continue;
            }

            $product = Product::find($item->product_id);
            if (! $product || ! $product->track_inventory) {
                continue;
            }

            $this->inventoryService->stockIn(
                $product,
                $warehouse,
                (int) $item->quantity,
                $item->unit_price,
                'Retur penjualan: '.$salesReturn->return_number,
                SalesReturn::class,
                $salesReturn->id
            );
        }
    }

    /**
     * Create journal entry for sales return.
     * Debit: Sales Returns (reduces revenue)
     * Debit: PPN Keluaran (if applicable)
     * Credit: Accounts Receivable (reduces receivable)
     */
    private function createReturnJournalEntry(SalesReturn $salesReturn): void
    {
        $salesReturnsAccount = Account::where('code', '4-2001')->first(); // Retur Penjualan
        $receivableAccount = Account::where('code', '1-1100')->first(); // Piutang Usaha
        $taxPayableAccount = Account::where('code', '2-1200')->first(); // PPN Keluaran

        // If linked to invoice, use invoice's receivable account
        if ($salesReturn->invoice && $salesReturn->invoice->receivable_account_id) {
            $receivableAccount = Account::find($salesReturn->invoice->receivable_account_id) ?? $receivableAccount;
        }

        $lines = [];

        // Debit: Sales Returns (contra revenue)
        if ($salesReturnsAccount) {
            $lines[] = [
                'account_id' => $salesReturnsAccount->id,
                'description' => 'Retur penjualan: '.$salesReturn->return_number,
                'debit' => $salesReturn->subtotal,
                'credit' => 0,
            ];
        }

        // Debit: PPN Keluaran (reverse tax collected)
        if ($salesReturn->tax_amount > 0 && $taxPayableAccount) {
            $lines[] = [
                'account_id' => $taxPayableAccount->id,
                'description' => 'PPN Retur: '.$salesReturn->return_number,
                'debit' => $salesReturn->tax_amount,
                'credit' => 0,
            ];
        }

        // Credit: Accounts Receivable
        $lines[] = [
            'account_id' => $receivableAccount->id,
            'description' => 'Pengurangan piutang: '.$salesReturn->return_number,
            'debit' => 0,
            'credit' => $salesReturn->total_amount,
        ];

        $entry = $this->journalService->createEntry([
            'entry_date' => $salesReturn->return_date->toDateString(),
            'description' => 'Retur penjualan: '.$salesReturn->return_number,
            'reference' => $salesReturn->return_number,
            'source_type' => JournalEntry::SOURCE_MANUAL,
            'source_id' => $salesReturn->id,
            'lines' => $lines,
        ], autoPost: true);

        $salesReturn->journal_entry_id = $entry->id;
        $salesReturn->save();
    }
}
