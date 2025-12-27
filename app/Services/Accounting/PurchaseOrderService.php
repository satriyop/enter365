<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Bill;
use App\Models\Accounting\BillItem;
use App\Models\Accounting\PurchaseOrder;
use App\Models\Accounting\PurchaseOrderItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PurchaseOrderService
{
    /**
     * Create a new purchase order with items.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            // Set defaults
            $data['po_number'] = PurchaseOrder::generatePoNumber();
            $data['status'] = PurchaseOrder::STATUS_DRAFT;
            $data['currency'] = $data['currency'] ?? 'IDR';
            $data['exchange_rate'] = $data['exchange_rate'] ?? 1;
            $data['tax_rate'] = $data['tax_rate'] ?? config('accounting.tax.default_rate', 11.00);

            // Create PO with zero totals first
            $data['subtotal'] = 0;
            $data['discount_amount'] = 0;
            $data['tax_amount'] = 0;
            $data['total'] = 0;
            $data['base_currency_total'] = 0;
            $data['created_by'] = auth()->id();

            $purchaseOrder = PurchaseOrder::create($data);

            // Create items
            $this->createItems($purchaseOrder, $items);

            // Calculate totals
            $purchaseOrder->refresh();
            $purchaseOrder->calculateTotals();
            $purchaseOrder->save();

            return $purchaseOrder->load('items', 'contact');
        });
    }

    /**
     * Update a purchase order.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(PurchaseOrder $purchaseOrder, array $data): PurchaseOrder
    {
        if (! $purchaseOrder->isEditable()) {
            throw new InvalidArgumentException('Hanya PO draft yang dapat diubah.');
        }

        return DB::transaction(function () use ($purchaseOrder, $data) {
            $items = $data['items'] ?? null;
            unset($data['items']);

            $purchaseOrder->update($data);

            if ($items !== null) {
                // Delete existing items and recreate
                $purchaseOrder->items()->delete();
                $this->createItems($purchaseOrder, $items);
            }

            // Recalculate totals
            $purchaseOrder->refresh();
            $purchaseOrder->calculateTotals();
            $purchaseOrder->save();

            return $purchaseOrder->load('items', 'contact');
        });
    }

    /**
     * Submit PO for approval.
     */
    public function submit(PurchaseOrder $purchaseOrder, ?int $userId = null): PurchaseOrder
    {
        if (! $purchaseOrder->canSubmit()) {
            throw new InvalidArgumentException('PO tidak dapat diajukan. Pastikan status draft dan memiliki item.');
        }

        $purchaseOrder->update([
            'status' => PurchaseOrder::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'submitted_by' => $userId ?? auth()->id(),
        ]);

        return $purchaseOrder->fresh(['items', 'contact']);
    }

    /**
     * Approve a PO.
     */
    public function approve(PurchaseOrder $purchaseOrder, ?int $userId = null): PurchaseOrder
    {
        if (! $purchaseOrder->canApprove()) {
            throw new InvalidArgumentException('PO tidak dapat disetujui. Pastikan sudah diajukan.');
        }

        $purchaseOrder->update([
            'status' => PurchaseOrder::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $userId ?? auth()->id(),
        ]);

        return $purchaseOrder->fresh(['items', 'contact']);
    }

    /**
     * Reject a PO.
     */
    public function reject(PurchaseOrder $purchaseOrder, string $reason, ?int $userId = null): PurchaseOrder
    {
        if (! $purchaseOrder->canReject()) {
            throw new InvalidArgumentException('PO tidak dapat ditolak. Pastikan sudah diajukan.');
        }

        if (empty($reason)) {
            throw new InvalidArgumentException('Alasan penolakan harus diisi.');
        }

        $purchaseOrder->update([
            'status' => PurchaseOrder::STATUS_REJECTED,
            'rejected_at' => now(),
            'rejected_by' => $userId ?? auth()->id(),
            'rejection_reason' => $reason,
        ]);

        return $purchaseOrder->fresh(['items', 'contact']);
    }

    /**
     * Cancel a PO.
     */
    public function cancel(PurchaseOrder $purchaseOrder, string $reason, ?int $userId = null): PurchaseOrder
    {
        if (! $purchaseOrder->canCancel()) {
            throw new InvalidArgumentException('PO tidak dapat dibatalkan.');
        }

        if (empty($reason)) {
            throw new InvalidArgumentException('Alasan pembatalan harus diisi.');
        }

        $purchaseOrder->update([
            'status' => PurchaseOrder::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $userId ?? auth()->id(),
            'cancellation_reason' => $reason,
        ]);

        return $purchaseOrder->fresh(['items', 'contact']);
    }

    /**
     * Receive items for a PO.
     *
     * @param  array<int, array{item_id: int, quantity: float}>  $receivedItems
     */
    public function receive(PurchaseOrder $purchaseOrder, array $receivedItems): PurchaseOrder
    {
        if (! $purchaseOrder->canReceive()) {
            throw new InvalidArgumentException('PO tidak dapat menerima barang. Pastikan sudah disetujui.');
        }

        return DB::transaction(function () use ($purchaseOrder, $receivedItems) {
            foreach ($receivedItems as $received) {
                $item = $purchaseOrder->items()->find($received['item_id']);

                if (! $item) {
                    throw new InvalidArgumentException("Item dengan ID {$received['item_id']} tidak ditemukan.");
                }

                $newQty = $received['quantity'];
                $remaining = $item->getQuantityRemaining();

                if ($newQty > $remaining) {
                    throw new InvalidArgumentException("Jumlah terima ({$newQty}) melebihi sisa yang harus diterima ({$remaining}) untuk item: {$item->description}");
                }

                $item->receive($newQty);
                $item->save();
            }

            // Update PO status
            $purchaseOrder->refresh();
            $purchaseOrder->updateReceivingStatus();
            $purchaseOrder->save();

            return $purchaseOrder->fresh(['items', 'contact']);
        });
    }

    /**
     * Convert a PO to bill.
     */
    public function convertToBill(PurchaseOrder $purchaseOrder): Bill
    {
        if (! $purchaseOrder->canConvert()) {
            throw new InvalidArgumentException('PO tidak dapat dikonversi. Pastikan sudah menerima barang dan belum dikonversi.');
        }

        return DB::transaction(function () use ($purchaseOrder) {
            // Create bill
            $bill = Bill::create([
                'bill_number' => Bill::generateBillNumber(),
                'contact_id' => $purchaseOrder->contact_id,
                'bill_date' => now(),
                'due_date' => now()->addDays(config('accounting.payment.default_term_days', 30)),
                'description' => $purchaseOrder->subject,
                'reference' => $purchaseOrder->getFullNumber(),
                'subtotal' => $purchaseOrder->subtotal,
                'tax_amount' => $purchaseOrder->tax_amount,
                'tax_rate' => $purchaseOrder->tax_rate,
                'discount_amount' => $purchaseOrder->discount_amount,
                'total_amount' => $purchaseOrder->total,
                'currency' => $purchaseOrder->currency,
                'exchange_rate' => $purchaseOrder->exchange_rate,
                'base_currency_total' => $purchaseOrder->base_currency_total,
                'paid_amount' => 0,
                'status' => Bill::STATUS_DRAFT,
                'created_by' => auth()->id(),
            ]);

            // Copy items (only received quantities)
            foreach ($purchaseOrder->items as $item) {
                if ($item->quantity_received > 0) {
                    // Recalculate based on received quantity
                    $receivedRatio = $item->quantity_received / $item->quantity;
                    $lineTotal = (int) round($item->line_total * $receivedRatio);

                    BillItem::create([
                        'bill_id' => $bill->id,
                        'product_id' => $item->product_id,
                        'description' => $item->description,
                        'quantity' => $item->quantity_received,
                        'unit' => $item->unit,
                        'unit_price' => $item->unit_price,
                        'amount' => $lineTotal,
                    ]);
                }
            }

            // Update PO
            $purchaseOrder->update([
                'converted_to_bill_id' => $bill->id,
                'converted_at' => now(),
            ]);

            return $bill->load('items', 'contact');
        });
    }

    /**
     * Duplicate a PO as a new draft.
     */
    public function duplicate(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder) {
            $newPo = PurchaseOrder::create([
                'po_number' => PurchaseOrder::generatePoNumber(),
                'revision' => 0,
                'contact_id' => $purchaseOrder->contact_id,
                'po_date' => now(),
                'expected_date' => now()->addDays(14),
                'reference' => null,
                'subject' => $purchaseOrder->subject,
                'status' => PurchaseOrder::STATUS_DRAFT,
                'currency' => $purchaseOrder->currency,
                'exchange_rate' => $purchaseOrder->exchange_rate,
                'subtotal' => $purchaseOrder->subtotal,
                'discount_type' => $purchaseOrder->discount_type,
                'discount_value' => $purchaseOrder->discount_value,
                'discount_amount' => $purchaseOrder->discount_amount,
                'tax_rate' => $purchaseOrder->tax_rate,
                'tax_amount' => $purchaseOrder->tax_amount,
                'total' => $purchaseOrder->total,
                'base_currency_total' => $purchaseOrder->base_currency_total,
                'notes' => $purchaseOrder->notes,
                'terms_conditions' => $purchaseOrder->terms_conditions,
                'shipping_address' => $purchaseOrder->shipping_address,
                'created_by' => auth()->id(),
            ]);

            // Copy items
            foreach ($purchaseOrder->items as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $newPo->id,
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'quantity_received' => 0,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price,
                    'discount_percent' => $item->discount_percent,
                    'discount_amount' => $item->discount_amount,
                    'tax_rate' => $item->tax_rate,
                    'tax_amount' => $item->tax_amount,
                    'line_total' => $item->line_total,
                    'sort_order' => $item->sort_order,
                    'notes' => $item->notes,
                ]);
            }

            return $newPo->load('items', 'contact');
        });
    }

    /**
     * Get outstanding POs (approved but not fully received).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, PurchaseOrder>
     */
    public function getOutstanding(?int $contactId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = PurchaseOrder::query()
            ->with(['contact', 'items'])
            ->outstanding()
            ->orderBy('expected_date');

        if ($contactId) {
            $query->where('contact_id', $contactId);
        }

        return $query->get();
    }

    /**
     * Get PO statistics.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(?string $startDate = null, ?string $endDate = null): array
    {
        $query = PurchaseOrder::query();

        if ($startDate) {
            $query->where('po_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('po_date', '<=', $endDate);
        }

        $total = (clone $query)->count();
        $draft = (clone $query)->where('status', PurchaseOrder::STATUS_DRAFT)->count();
        $submitted = (clone $query)->where('status', PurchaseOrder::STATUS_SUBMITTED)->count();
        $approved = (clone $query)->where('status', PurchaseOrder::STATUS_APPROVED)->count();
        $rejected = (clone $query)->where('status', PurchaseOrder::STATUS_REJECTED)->count();
        $partial = (clone $query)->where('status', PurchaseOrder::STATUS_PARTIAL)->count();
        $received = (clone $query)->where('status', PurchaseOrder::STATUS_RECEIVED)->count();
        $cancelled = (clone $query)->where('status', PurchaseOrder::STATUS_CANCELLED)->count();

        $totalValue = (clone $query)->sum('total');
        $outstandingValue = (clone $query)->whereIn('status', [
            PurchaseOrder::STATUS_APPROVED,
            PurchaseOrder::STATUS_PARTIAL,
        ])->sum('total');

        return [
            'total' => $total,
            'by_status' => [
                'draft' => $draft,
                'submitted' => $submitted,
                'approved' => $approved,
                'rejected' => $rejected,
                'partial' => $partial,
                'received' => $received,
                'cancelled' => $cancelled,
            ],
            'total_value' => $totalValue,
            'outstanding_value' => $outstandingValue,
        ];
    }

    /**
     * Create PO items.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function createItems(PurchaseOrder $purchaseOrder, array $items): void
    {
        foreach ($items as $index => $itemData) {
            $quantity = $itemData['quantity'] ?? 1;
            $unitPrice = $itemData['unit_price'] ?? 0;
            $discountPercent = $itemData['discount_percent'] ?? 0;
            $taxRate = $itemData['tax_rate'] ?? $purchaseOrder->tax_rate;

            $grossAmount = (int) round($quantity * $unitPrice);
            $discountAmount = $discountPercent > 0
                ? (int) round($grossAmount * ($discountPercent / 100))
                : 0;
            $netAmount = $grossAmount - $discountAmount;
            $taxAmount = (int) round($netAmount * ($taxRate / 100));

            PurchaseOrderItem::create([
                'purchase_order_id' => $purchaseOrder->id,
                'product_id' => $itemData['product_id'] ?? null,
                'description' => $itemData['description'],
                'quantity' => $quantity,
                'quantity_received' => 0,
                'unit' => $itemData['unit'] ?? 'unit',
                'unit_price' => $unitPrice,
                'discount_percent' => $discountPercent,
                'discount_amount' => $discountAmount,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'line_total' => $netAmount,
                'sort_order' => $itemData['sort_order'] ?? $index,
                'notes' => $itemData['notes'] ?? null,
            ]);
        }
    }
}
