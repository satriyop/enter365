<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Purchasing\PurchaseOrderServiceInterface;
use App\Domain\Purchasing\PurchaseOrderBillConverter;
use App\Domain\Purchasing\PurchaseOrders\PurchaseOrderDomainFactory;
use App\Domain\Purchasing\PurchaseOrderStatistics;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\DocumentLockedException;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderItem;
use App\Services\Base\Traits\WithDocuments;
use App\Services\Base\Traits\WithEventDispatching;
use App\Services\Base\Traits\WithOperationContext;
use App\Services\Base\Traits\WithTransaction;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderService implements PurchaseOrderServiceInterface
{
    use WithDocuments;
    use WithEventDispatching;
    use WithOperationContext;
    use WithTransaction;

    protected EventDispatcherInterface $eventDispatcher;

    protected ContextualLoggerInterface $logger;

    private PurchaseOrderBillConverter $billConverter;

    private PurchaseOrderStatistics $statistics;

    private PurchaseOrderDomainFactory $domainFactory;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        PurchaseOrderBillConverter $billConverter,
        PurchaseOrderStatistics $statistics,
        PurchaseOrderDomainFactory $domainFactory
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;

        $this->billConverter = $billConverter;
        $this->statistics = $statistics;
        $this->domainFactory = $domainFactory;
    }

    protected function getModelClass(): string
    {
        return PurchaseOrder::class;
    }

    protected function getItemRelation(): string
    {
        return 'items';
    }

    protected function recalculateTotals(Model $document): void
    {
        $document->refresh();
        $document->load($this->getItemRelation());

        /** @var PurchaseOrder $document */
        $this->domainFactory->applyTotals($document);
        $document->save();
    }

    protected function getInitialStatus(): DocumentStatus
    {
        return DocumentStatus::Draft;
    }

    protected function getDefaultData(): array
    {
        return [
            'currency' => 'IDR',
            'exchange_rate' => 1,
            'tax_rate' => config('accounting.tax.default_rate', 11.00),
            'subtotal' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'base_currency_total' => 0,
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
        /** @var PurchaseOrder */
        return $this->createDocument($data);
    }

    /**
     * Update an existing purchase order.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(PurchaseOrder $purchaseOrder, array $data): PurchaseOrder
    {
        /** @var PurchaseOrder */
        return $this->updateDocument($purchaseOrder, $data);
    }

    /**
     * Delete a draft purchase order and its items.
     */
    public function delete(PurchaseOrder $purchaseOrder): bool
    {
        return $this->deleteDocument($purchaseOrder);
    }

    protected function validateEditable(Model $document): void
    {
        /** @var PurchaseOrder $document */
        if (! $document->isEditable()) {
            throw DocumentLockedException::cannotEdit($document, 'Hanya PO draft yang dapat diubah.');
        }
    }

    protected function validateDeletable(Model $document): void
    {
        /** @var PurchaseOrder $document */
        if ($document->status !== DocumentStatus::Draft) {
            throw DocumentLockedException::cannotDelete($document, 'Hanya PO draft yang dapat dihapus.');
        }
    }

    protected function createItems(Model $document, array $items): void
    {
        assert($document instanceof PurchaseOrder);
        foreach ($items as $index => $itemData) {
            $quantity = $itemData['quantity'] ?? 1;
            $unitPrice = $itemData['unit_price'] ?? 0;
            $discountPercent = $itemData['discount_percent'] ?? 0;
            $taxRate = $itemData['tax_rate'] ?? $document->getAttribute('tax_rate');

            $grossAmount = (int) round($quantity * $unitPrice);
            $discountAmount = $discountPercent > 0
                ? (int) round($grossAmount * ($discountPercent / 100))
                : 0;
            $netAmount = $grossAmount - $discountAmount;
            $taxAmount = (int) round($netAmount * ($taxRate / 100));

            PurchaseOrderItem::create([
                'purchase_order_id' => $document->getKey(),
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
        if (! $this->domainFactory->stateMachine($purchaseOrder)->canSubmit()) {
            throw \App\Exceptions\Domain\StateTransitionException::actionNotAvailable('diajukan', $purchaseOrder->status->value, 'PO harus dalam status draft dan memiliki item.');
        }

        $purchaseOrder->transitionTo(DocumentStatus::Submitted, $userId);

        return $purchaseOrder->fresh(['items', 'contact']);
    }

    public function approve(PurchaseOrder $purchaseOrder, ?int $userId = null): PurchaseOrder
    {
        if (! $this->domainFactory->stateMachine($purchaseOrder)->canApprove()) {
            throw \App\Exceptions\Domain\StateTransitionException::actionNotAvailable('disetujui', $purchaseOrder->status->value, 'PO harus sudah diajukan.');
        }

        $purchaseOrder->transitionTo(DocumentStatus::Approved, $userId);

        return $purchaseOrder->fresh(['items', 'contact']);
    }

    public function reject(PurchaseOrder $purchaseOrder, string $reason, ?int $userId = null): PurchaseOrder
    {
        if (! $this->domainFactory->stateMachine($purchaseOrder)->canReject()) {
            throw \App\Exceptions\Domain\StateTransitionException::actionNotAvailable('ditolak', $purchaseOrder->status->value, 'PO harus sudah diajukan.');
        }

        if (empty($reason)) {
            throw \App\Exceptions\Domain\BusinessRuleException::missingRequiredData('Penolakan PO', 'alasan');
        }

        $purchaseOrder->transitionTo(DocumentStatus::Rejected, $userId, ['rejection_reason' => $reason]);

        return $purchaseOrder->fresh(['items', 'contact']);
    }

    public function cancel(PurchaseOrder $purchaseOrder, string $reason, ?int $userId = null): PurchaseOrder
    {
        if (! $this->domainFactory->stateMachine($purchaseOrder)->canCancel()) {
            throw \App\Exceptions\Domain\StateTransitionException::actionNotAvailable('dibatalkan', $purchaseOrder->status->value);
        }

        if (empty($reason)) {
            throw \App\Exceptions\Domain\BusinessRuleException::missingRequiredData('Pembatalan PO', 'alasan');
        }

        $purchaseOrder->transitionTo(DocumentStatus::Cancelled, $userId, ['cancellation_reason' => $reason]);

        return $purchaseOrder->fresh(['items', 'contact']);
    }

    public function convertToBill(PurchaseOrder $purchaseOrder): \App\Models\Purchasing\Bill
    {
        return $this->billConverter->convert($purchaseOrder);
    }

    public function duplicate(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return $this->executeInTransaction('duplicate', function () use ($purchaseOrder) {
            $defaults = $this->getDefaultsForDuplication($purchaseOrder);
            $defaults['revision'] = 0;
            $defaults['created_by'] = $this->getUserId();

            $newPo = PurchaseOrder::create($defaults);

            $this->copyItemsFromPurchaseOrder($purchaseOrder, $newPo);

            return $newPo->load('items', 'contact');
        }, ['source_purchase_order_id' => $purchaseOrder->id]);
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
            'total_amount' => $source->total_amount,
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
