<?php

namespace App\Services\Purchasing;

use App\Contracts\Services\Domain\DocumentNumberGeneratorInterface;
use App\Contracts\Services\Domains\PurchaseOrderServiceInterface;
use App\Domain\Purchasing\PurchaseOrderBillConverter;
use App\Domain\Purchasing\PurchaseOrderDefaults;
use App\Domain\Purchasing\PurchaseOrderItemCreator;
use App\Domain\Purchasing\PurchaseOrderStatistics;
use App\Domain\Purchasing\PurchaseOrderWorkflow;
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
        private ?PurchaseOrderWorkflow $workflow = null,
        private ?PurchaseOrderBillConverter $billConverter = null,
        private ?PurchaseOrderStatistics $statistics = null
    ) {
        $this->receivingService ??= app(PurchaseOrderReceivingService::class);
        $this->numberGenerator ??= app(DocumentNumberGeneratorInterface::class);
        $this->defaults ??= app(PurchaseOrderDefaults::class);
        $this->itemCreator ??= app(PurchaseOrderItemCreator::class);
        $this->workflow ??= app(PurchaseOrderWorkflow::class);
        $this->billConverter ??= app(PurchaseOrderBillConverter::class);
        $this->statistics ??= app(PurchaseOrderStatistics::class);
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

    public function convertToBill(PurchaseOrder $purchaseOrder): \App\Models\Purchasing\Bill
    {
        return $this->billConverter->convert($purchaseOrder);
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
        return $this->statistics->getOutstanding($contactId);
    }

    public function getStatistics(?string $startDate = null, ?string $endDate = null): array
    {
        return $this->statistics->getStatistics($startDate, $endDate);
    }
}
