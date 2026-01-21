<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Contracts\Purchasing\PurchaseOrderServiceInterface;
use App\Enums\DocumentStatus;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderItem;
use App\Services\Base\AbstractDocumentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PurchaseOrderService extends AbstractDocumentService implements PurchaseOrderServiceInterface
{
    private PurchaseOrderReceivingService $receivingService;

    private \App\Domain\Purchasing\PurchaseOrderBillConverter $billConverter;

    private \App\Domain\Purchasing\PurchaseOrderStatistics $statistics;

    public function __construct(
        PurchaseOrderReceivingService $receivingService,
        \App\Domain\Purchasing\PurchaseOrderBillConverter $billConverter,
        \App\Domain\Purchasing\PurchaseOrderStatistics $statistics,
        \App\Contracts\Shared\DocumentNumberGeneratorInterface $numberGenerator
    ) {
        parent::__construct(numberGenerator: $numberGenerator);

        $this->receivingService = $receivingService;
        $this->billConverter = $billConverter;
        $this->statistics = $statistics;
    }

    protected function getModelClass(): string
    {
        return PurchaseOrder::class;
    }

    protected function getItemRelation(): string
    {
        return 'items';
    }

    protected function generateDocumentNumber(?Model $context = null): string
    {
        $prefix = 'PO-'.now()->format('Ym').'-';

        return $this->numberGenerator->generate($prefix, 'purchase_orders', 'po_number');
    }

    protected function getDocumentNumberField(): string
    {
        return 'po_number';
    }

    protected function getDocumentNumberPrefix(): string
    {
        return 'PO-'.now()->format('Ym').'-';
    }

    protected function getDocumentNumberConfig(): array
    {
        return ['table' => 'purchase_orders', 'column' => 'po_number'];
    }

    protected function getInitialStatus(): DocumentStatus
    {
        return DocumentStatus::Draft;
    }

    protected function getDefaultData(): array
    {
        return [
            ...parent::getDefaultData(),
            'revision' => 0,
        ];
    }

    protected function getEagerLoadRelations(): array
    {
        return ['items', 'contact'];
    }

    /**
     * Create a new purchase order.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PurchaseOrder
    {
        $result = $this->createDocument($data);

        return $result->getDataOrFail();
    }

    /**
     * Update an existing purchase order.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(PurchaseOrder $purchaseOrder, array $data): PurchaseOrder
    {
        $result = $this->updateDocument($purchaseOrder, $data);

        return $result->getDataOrFail();
    }

    protected function validateEditable(Model $document): void
    {
        /** @var PurchaseOrder $document */
        if (! $document->isEditable()) {
            throw new InvalidArgumentException('Hanya PO draft yang dapat diubah.');
        }
    }

    protected function validateDeletable(Model $document): void
    {
        /** @var PurchaseOrder $document */
        if (! $document->isDraft()) {
            throw new InvalidArgumentException('Hanya PO draft yang dapat dihapus.');
        }
    }

    protected function createItems(Model $document, array $items): void
    {
        foreach ($items as $index => $itemData) {
            $quantity = $itemData['quantity'] ?? 1;
            $unitPrice = $itemData['unit_price'] ?? 0;
            $discountPercent = $itemData['discount_percent'] ?? 0;
            $taxRate = $itemData['tax_rate'] ?? $document->tax_rate;

            $grossAmount = (int) round($quantity * $unitPrice);
            $discountAmount = $discountPercent > 0
                ? (int) round($grossAmount * ($discountPercent / 100))
                : 0;
            $netAmount = $grossAmount - $discountAmount;
            $taxAmount = (int) round($netAmount * ($taxRate / 100));

            PurchaseOrderItem::create([
                'purchase_order_id' => $document->id,
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

    public function submit(PurchaseOrder $purchaseOrder, ?int $userId = null): PurchaseOrder
    {
        if (! $purchaseOrder->stateMachine()->canSubmit()) {
            throw new InvalidArgumentException('PO tidak dapat diajukan. Pastikan status draft dan memiliki item.');
        }

        $purchaseOrder->transitionTo(DocumentStatus::Submitted, $userId);

        return $purchaseOrder->fresh(['items', 'contact']);
    }

    public function approve(PurchaseOrder $purchaseOrder, ?int $userId = null): PurchaseOrder
    {
        if (! $purchaseOrder->stateMachine()->canApprove()) {
            throw new InvalidArgumentException('PO tidak dapat disetujui. Pastikan sudah diajukan.');
        }

        $purchaseOrder->transitionTo(DocumentStatus::Approved, $userId);

        return $purchaseOrder->fresh(['items', 'contact']);
    }

    public function reject(PurchaseOrder $purchaseOrder, string $reason, ?int $userId = null): PurchaseOrder
    {
        if (! $purchaseOrder->stateMachine()->canReject()) {
            throw new InvalidArgumentException('PO tidak dapat ditolak. Pastikan sudah diajukan.');
        }

        if (empty($reason)) {
            throw new InvalidArgumentException('Alasan penolakan harus diisi.');
        }

        $purchaseOrder->transitionTo(DocumentStatus::Rejected, $userId, ['rejection_reason' => $reason]);

        return $purchaseOrder->fresh(['items', 'contact']);
    }

    public function cancel(PurchaseOrder $purchaseOrder, string $reason, ?int $userId = null): PurchaseOrder
    {
        if (! $purchaseOrder->stateMachine()->canCancel()) {
            throw new InvalidArgumentException('PO tidak dapat dibatalkan.');
        }

        if (empty($reason)) {
            throw new InvalidArgumentException('Alasan pembatalan harus diisi.');
        }

        $purchaseOrder->transitionTo(DocumentStatus::Cancelled, $userId, ['cancellation_reason' => $reason]);

        return $purchaseOrder->fresh(['items', 'contact']);
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
            $defaults = $this->getDefaultsForDuplication($purchaseOrder);
            $defaults['po_number'] = PurchaseOrder::generatePoNumber();
            $defaults['revision'] = 0;
            $defaults['created_by'] = auth()->id();

            $newPo = PurchaseOrder::create($defaults);

            $this->copyItemsFromPurchaseOrder($purchaseOrder, $newPo);

            return $newPo->load('items', 'contact');
        });
    }

    private function getDefaultsForDuplication(PurchaseOrder $source): array
    {
        return [
            'contact_id' => $source->contact_id,
            'po_date' => now(),
            'expected_date' => now()->addDays(14),
            'reference' => null,
            'subject' => $source->subject,
            'status' => 'draft',
            'currency' => $source->currency,
            'exchange_rate' => $source->exchange_rate,
            'subtotal' => $source->subtotal,
            'discount_type' => $source->discount_type,
            'discount_value' => $source->discount_value,
            'discount_amount' => $source->discount_amount,
            'tax_rate' => $source->tax_rate,
            'tax_amount' => $source->tax_amount,
            'total' => $source->total,
            'base_currency_total' => $source->base_currency_total,
            'notes' => $source->notes,
            'terms_conditions' => $source->terms_conditions,
            'shipping_address' => $source->shipping_address,
        ];
    }

    private function copyItemsFromPurchaseOrder(PurchaseOrder $source, PurchaseOrder $target): void
    {
        foreach ($source->items as $item) {
            PurchaseOrderItem::create([
                'purchase_order_id' => $target->id,
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
