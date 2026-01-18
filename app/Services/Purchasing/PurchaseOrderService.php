<?php

namespace App\Services\Purchasing;

use App\Contracts\Services\Domain\DocumentNumberGeneratorInterface;
use App\Contracts\Services\Domains\PurchaseOrderServiceInterface;
use App\Domain\Purchasing\PurchaseOrderDefaults;
use App\Domain\Purchasing\PurchaseOrderItemCreator;
use App\Domain\Purchasing\PurchaseOrderWorkflow;
use App\Enums\DocumentStatus;
use App\Models\Purchasing\Bill;
use App\Models\Purchasing\BillItem;
use App\Models\Purchasing\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PurchaseOrderService implements PurchaseOrderServiceInterface
{
    public function __construct(
        private ?PurchaseOrderReceivingService $receivingService = null,
        private ?DocumentNumberGeneratorInterface $numberGenerator = null,
        private ?PurchaseOrderDefaults $defaults = null,
        private ?PurchaseOrderItemCreator $itemCreator = null,
        private ?PurchaseOrderWorkflow $workflow = null
    ) {
        $this->receivingService ??= app(PurchaseOrderReceivingService::class);
        $this->numberGenerator ??= app(DocumentNumberGeneratorInterface::class);
        $this->defaults ??= app(PurchaseOrderDefaults::class);
        $this->itemCreator ??= app(PurchaseOrderItemCreator::class);
        $this->workflow ??= app(PurchaseOrderWorkflow::class);
    }

    public function create(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            $data['po_number'] = $this->numberGenerator->generate('PO-'.now()->format('Ym').'-', 'purchase_orders', 'po_number');

            $defaults = $this->defaults->getForCreate($data, (int) auth()->id());
            $data = array_merge($defaults, $data);

            $purchaseOrder = PurchaseOrder::create($data);

            $this->itemCreator->create($purchaseOrder, $items);

            $purchaseOrder->refresh();
            $purchaseOrder->calculateTotals();
            $purchaseOrder->save();

            return $purchaseOrder->load('items', 'contact');
        });
    }

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
                $purchaseOrder->items()->delete();
                $this->itemCreator->create($purchaseOrder, $items);
            }

            $purchaseOrder->refresh();
            $purchaseOrder->calculateTotals();
            $purchaseOrder->save();

            return $purchaseOrder->load('items', 'contact');
        });
    }

    public function submit(PurchaseOrder $purchaseOrder, ?int $userId = null): PurchaseOrder
    {
        return $this->workflow->submit($purchaseOrder, $userId);
    }

    public function approve(PurchaseOrder $purchaseOrder, ?int $userId = null): PurchaseOrder
    {
        return $this->workflow->approve($purchaseOrder, $userId);
    }

    public function reject(PurchaseOrder $purchaseOrder, string $reason, ?int $userId = null): PurchaseOrder
    {
        return $this->workflow->reject($purchaseOrder, $reason, $userId);
    }

    public function cancel(PurchaseOrder $purchaseOrder, string $reason, ?int $userId = null): PurchaseOrder
    {
        return $this->workflow->cancel($purchaseOrder, $reason, $userId);
    }

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

            $purchaseOrder->refresh();
            $this->receivingService->updateReceivingStatus($purchaseOrder);

            return $purchaseOrder->fresh(['items', 'contact']);
        });
    }

    public function convertToBill(PurchaseOrder $purchaseOrder): Bill
    {
        if (! $purchaseOrder->canConvert()) {
            throw new InvalidArgumentException('PO tidak dapat dikonversi. Pastikan sudah menerima barang dan belum dikonversi.');
        }

        return DB::transaction(function () use ($purchaseOrder) {
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
                'status' => DocumentStatus::Draft,
                'created_by' => auth()->id(),
            ]);

            foreach ($purchaseOrder->items as $item) {
                if ($item->quantity_received > 0) {
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

            $purchaseOrder->update([
                'converted_to_bill_id' => $bill->id,
                'converted_at' => now(),
            ]);

            return $bill->load('items', 'contact');
        });
    }

    public function duplicate(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder) {
            $defaults = $this->defaults->forDuplication($purchaseOrder);
            $defaults['po_number'] = PurchaseOrder::generatePoNumber();
            $defaults['revision'] = 0;
            $defaults['created_by'] = auth()->id();

            $newPo = PurchaseOrder::create($defaults);

            $this->itemCreator->copyFromPurchaseOrder($purchaseOrder, $newPo);

            return $newPo->load('items', 'contact');
        });
    }

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
        $draft = (clone $query)->where('status', DocumentStatus::Draft)->count();
        $submitted = (clone $query)->where('status', DocumentStatus::Submitted)->count();
        $approved = (clone $query)->where('status', DocumentStatus::Approved)->count();
        $rejected = (clone $query)->where('status', DocumentStatus::Rejected)->count();
        $partial = (clone $query)->where('status', DocumentStatus::Partial)->count();
        $received = (clone $query)->where('status', DocumentStatus::Received)->count();
        $cancelled = (clone $query)->where('status', DocumentStatus::Cancelled)->count();

        $totalValue = (clone $query)->sum('total');
        $outstandingValue = (clone $query)->whereIn('status', [
            DocumentStatus::Approved,
            DocumentStatus::Partial,
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
}
