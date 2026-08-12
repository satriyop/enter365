<?php

declare(strict_types=1);

namespace App\Services\Sales\DeliveryOrder;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Enums\DocumentStatus;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Sales\DeliveryOrder;
use App\Models\Sales\DeliveryOrderItem;
use App\Models\Sales\Invoice;
use App\Services\Base\Traits\WithDocuments;
use App\Services\Base\Traits\WithEventDispatching;
use App\Services\Base\Traits\WithOperationContext;
use App\Services\Base\Traits\WithTransaction;
use Illuminate\Database\Eloquent\Model;

/**
 * Handles CRUD operations for delivery orders.
 *
 * Extracted from DeliveryOrderService as part of the Coordinator Pattern refactoring.
 * This service focuses on create, update, delete, and document creation helpers.
 *
 * @see \App\Services\Sales\DeliveryOrderService The coordinator service
 */
class DeliveryOrderCrudService
{
    use WithDocuments;
    use WithEventDispatching;
    use WithOperationContext;
    use WithTransaction;

    protected EventDispatcherInterface $eventDispatcher;

    protected ContextualLoggerInterface $logger;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
    }

    protected function getModelClass(): string
    {
        return DeliveryOrder::class;
    }

    protected function getItemRelation(): string
    {
        return 'items';
    }

    protected function getInitialStatus(): DocumentStatus
    {
        return DocumentStatus::Draft;
    }

    protected function getEagerLoadRelations(): array
    {
        return ['items', 'contact', 'invoice', 'warehouse'];
    }

    /**
     * Create a new delivery order.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): DeliveryOrder
    {
        /** @var DeliveryOrder */
        return $this->createDocument($data);
    }

    /**
     * Update an existing delivery order.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(DeliveryOrder $deliveryOrder, array $data): DeliveryOrder
    {
        /** @var DeliveryOrder */
        return $this->updateDocument($deliveryOrder, $data);
    }

    /**
     * Delete a delivery order.
     */
    public function delete(DeliveryOrder $deliveryOrder): bool
    {
        return $this->deleteDocument($deliveryOrder);
    }

    protected function validateEditable(Model $document): void
    {
        /** @var DeliveryOrder $document */
        if (! $document->isEditable()) {
            throw \App\Exceptions\Domain\DocumentLockedException::cannotEdit($document, 'Delivery order can only be edited in draft status.');
        }
    }

    protected function validateDeletable(Model $document): void
    {
        /** @var DeliveryOrder $document */
        if (! $document->isEditable()) {
            throw \App\Exceptions\Domain\DocumentLockedException::cannotDelete($document, 'Only draft delivery orders can be deleted.');
        }
    }

    protected function createItems(Model $document, array $items): void
    {
        assert($document instanceof DeliveryOrder);
        foreach ($items as $itemData) {
            $document->items()->create($itemData);
        }
    }

    /**
     * Create delivery order from invoice.
     *
     * @param  array<string, mixed>  $data
     */
    public function createFromInvoice(Invoice $invoice, array $data = []): DeliveryOrder
    {
        return $this->executeInTransaction('create_from_invoice', function () use ($invoice, $data) {
            // Calculate already-allocated quantities from existing non-cancelled DOs
            $allocatedQuantities = $this->getAllocatedQuantitiesForInvoice($invoice);

            $deliveryOrder = new DeliveryOrder([
                'invoice_id' => $invoice->id,
                'contact_id' => $invoice->contact_id,
                'do_date' => $data['do_date'] ?? now()->toDateString(),
                'shipping_address' => $data['shipping_address'] ?? $invoice->contact->address ?? null,
                'shipping_method' => $data['shipping_method'] ?? null,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);
            $deliveryOrder->save();

            $hasItems = false;
            foreach ($invoice->items as $invoiceItem) {
                $allocated = $allocatedQuantities[$invoiceItem->id] ?? 0;
                $remaining = (float) $invoiceItem->quantity - $allocated;

                if ($remaining <= 0) {
                    continue;
                }

                $item = new DeliveryOrderItem;
                $item->delivery_order_id = $deliveryOrder->id;
                $item->fillFromInvoiceItem($invoiceItem, $remaining);
                $item->save();
                $hasItems = true;
            }

            if (! $hasItems) {
                throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                    'membuat surat jalan',
                    'Semua item invoice sudah dialokasikan ke surat jalan lain'
                );
            }

            return $deliveryOrder->fresh(['items', 'contact', 'invoice', 'warehouse']);
        }, ['invoice_id' => $invoice->id]);
    }

    /**
     * Get already-allocated quantities per invoice item from existing non-cancelled DOs.
     *
     * @return array<int, float> Map of invoice_item_id => allocated quantity
     */
    private function getAllocatedQuantitiesForInvoice(Invoice $invoice): array
    {
        $existingDoIds = DeliveryOrder::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', '!=', DocumentStatus::Cancelled)
            ->pluck('id');

        if ($existingDoIds->isEmpty()) {
            return [];
        }

        return DeliveryOrderItem::query()
            ->whereIn('delivery_order_id', $existingDoIds)
            ->whereNotNull('invoice_item_id')
            ->selectRaw('invoice_item_id, SUM(quantity) as total_qty')
            ->groupBy('invoice_item_id')
            ->pluck('total_qty', 'invoice_item_id')
            ->map(fn ($qty) => (float) $qty)
            ->toArray();
    }

    /**
     * Create delivery order from work order.
     */
    public function createFromWorkOrder(WorkOrder $workOrder): DeliveryOrder
    {
        return $this->executeInTransaction('create_from_work_order', function () use ($workOrder) {
            $project = $workOrder->project;

            $deliveryOrder = new DeliveryOrder([
                'contact_id' => $project->contact_id,
                'do_date' => now()->toDateString(),
                'shipping_address' => $project->contact->address ?? null,
                'warehouse_id' => $workOrder->warehouse_id,
                'notes' => "From Work Order: {$workOrder->wo_number}",
                'created_by' => $this->getUserId(),
            ]);
            $deliveryOrder->save();

            if ($workOrder->product_id) {
                $deliveryOrder->items()->create([
                    'product_id' => $workOrder->product_id,
                    'description' => $workOrder->name ?? $workOrder->product->name ?? '',
                    'quantity' => $workOrder->quantity_completed > 0
                        ? $workOrder->quantity_completed
                        : $workOrder->quantity_ordered,
                    'unit' => $workOrder->product->unit ?? 'pcs',
                    'quantity_delivered' => 0,
                ]);
            }

            return $deliveryOrder->fresh(['items', 'contact', 'warehouse']);
        }, ['work_order_id' => $workOrder->id]);
    }

    /**
     * Duplicate a delivery order.
     */
    public function duplicate(DeliveryOrder $deliveryOrder): DeliveryOrder
    {
        return $this->executeInTransaction('duplicate', function () use ($deliveryOrder) {
            $newDo = $deliveryOrder->replicate([
                'do_number',
                'status',
                'shipping_date',
                'received_date',
                'tracking_number',
                'received_by',
                'delivery_notes',
                'confirmed_by',
                'confirmed_at',
                'shipped_by',
                'shipped_at',
                'delivered_by',
                'delivered_at',
            ]);
            $newDo->status = DocumentStatus::Draft;
            $newDo->do_date = now();
            $newDo->save();

            foreach ($deliveryOrder->items as $item) {
                $newItem = $item->replicate(['quantity_delivered']);
                $newItem->delivery_order_id = $newDo->id;
                $newItem->quantity_delivered = 0;
                $newItem->save();
            }

            return $newDo->fresh(['items', 'contact', 'invoice']);
        }, ['source_delivery_order_id' => $deliveryOrder->id]);
    }

    /**
     * Get delivery orders for an invoice.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, DeliveryOrder>
     */
    public function getForInvoice(Invoice $invoice): \Illuminate\Database\Eloquent\Collection
    {
        return DeliveryOrder::query()
            ->where('invoice_id', $invoice->id)
            ->with(['items', 'creator'])
            ->orderBy('do_date', 'desc')
            ->get();
    }
}
