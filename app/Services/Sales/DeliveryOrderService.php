<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Contracts\Accounting\Strategies\COGSRecognitionStrategy;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Inventory\InventoryServiceInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Sales\DeliveryOrderServiceInterface;
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
use Illuminate\Support\Facades\DB;

class DeliveryOrderService implements DeliveryOrderServiceInterface
{
    use WithDocuments;
    use WithEventDispatching;
    use WithOperationContext;
    use WithTransaction;

    protected EventDispatcherInterface $eventDispatcher;

    protected ContextualLoggerInterface $logger;

    private InventoryServiceInterface $inventoryService;

    private COGSRecognitionStrategy $cogsStrategy;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        InventoryServiceInterface $inventoryService,
        COGSRecognitionStrategy $cogsStrategy
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
        $this->inventoryService = $inventoryService;
        $this->cogsStrategy = $cogsStrategy;
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
        return $this->createDocument($data);
    }

    /**
     * Update an existing delivery order.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(DeliveryOrder $deliveryOrder, array $data): DeliveryOrder
    {
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
        foreach ($items as $itemData) {
            $document->items()->create($itemData);
        }
    }

    /**
     * Create delivery order from invoice.
     */
    public function createFromInvoice(Invoice $invoice, array $data = []): DeliveryOrder
    {
        return $this->executeInTransaction('create_from_invoice', function () use ($invoice, $data) {
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

            foreach ($invoice->items as $invoiceItem) {
                $item = new DeliveryOrderItem;
                $item->delivery_order_id = $deliveryOrder->id;
                $item->fillFromInvoiceItem($invoiceItem);
                $item->save();
            }

            return $deliveryOrder->fresh(['items', 'contact', 'invoice', 'warehouse']);
        }, ['invoice_id' => $invoice->id]);
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
     * Confirm a delivery order.
     */
    public function confirm(DeliveryOrder $deliveryOrder, ?int $userId = null): DeliveryOrder
    {
        if (! $deliveryOrder->stateMachine()->canConfirm()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Delivery Order',
                'dikonfirmasi',
                $deliveryOrder->status->value,
                'draft dengan item'
            );
        }

        // State machine dispatches DeliveryOrderConfirmed event
        $deliveryOrder->transitionTo(DocumentStatus::Confirmed, $userId);

        return $deliveryOrder->fresh(['items', 'contact', 'invoice']);
    }

    /**
     * Ship a delivery order.
     */
    public function ship(DeliveryOrder $deliveryOrder, array $data = [], ?int $userId = null): DeliveryOrder
    {
        if (! $deliveryOrder->stateMachine()->canShip()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Delivery Order',
                'dikirim',
                $deliveryOrder->status->value,
                'confirmed'
            );
        }

        return $this->executeInTransaction('ship', function () use ($deliveryOrder, $data, $userId) {
            $deliveryOrder->update([
                'tracking_number' => $data['tracking_number'] ?? $deliveryOrder->tracking_number,
                'driver_name' => $data['driver_name'] ?? $deliveryOrder->driver_name,
                'vehicle_number' => $data['vehicle_number'] ?? $deliveryOrder->vehicle_number,
            ]);

            // State machine dispatches DeliveryOrderShipped event
            $deliveryOrder->transitionTo(DocumentStatus::Shipped, $userId, [
                'shipped_by' => $data['shipped_by'] ?? null,
                'shipping_date' => $data['shipping_date'] ?? now()->toDateString(),
            ]);

            if ($deliveryOrder->warehouse_id) {
                $this->deductInventory($deliveryOrder);
                $this->cogsStrategy->onDeliveryShip($deliveryOrder);
            }

            return $deliveryOrder->fresh(['items', 'contact', 'invoice']);
        }, ['delivery_order_id' => $deliveryOrder->id]);
    }

    /**
     * Mark delivery order as delivered.
     */
    public function deliver(DeliveryOrder $deliveryOrder, array $data = [], ?int $userId = null): DeliveryOrder
    {
        if (! $deliveryOrder->stateMachine()->canDeliver()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Delivery Order',
                'ditandai delivered',
                $deliveryOrder->status->value,
                'shipped'
            );
        }

        return $this->executeInTransaction('deliver', function () use ($deliveryOrder, $data, $userId) {
            $deliveryOrder->update([
                'received_by' => $data['received_by'] ?? null,
                'delivery_notes' => $data['delivery_notes'] ?? null,
            ]);

            $deliveryOrder->items()->update([
                'quantity_delivered' => DB::raw('quantity'),
            ]);

            // State machine dispatches DeliveryOrderDelivered event
            $deliveryOrder->transitionTo(DocumentStatus::Delivered, $userId, [
                'received_date' => $data['received_date'] ?? now()->toDateString(),
            ]);

            return $deliveryOrder->fresh(['items']);
        }, ['delivery_order_id' => $deliveryOrder->id]);
    }

    /**
     * Cancel a delivery order.
     */
    public function cancel(DeliveryOrder $deliveryOrder, ?string $reason = null, ?int $userId = null): DeliveryOrder
    {
        if (! $deliveryOrder->stateMachine()->canCancel()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Delivery Order',
                'dibatalkan',
                $deliveryOrder->status->value,
                'draft atau confirmed'
            );
        }

        // State machine dispatches DeliveryOrderCancelled event
        $deliveryOrder->transitionTo(DocumentStatus::Cancelled, $userId, [
            'cancellation_reason' => $reason,
        ]);

        return $deliveryOrder->fresh(['items', 'contact', 'invoice']);
    }

    /**
     * Update delivery progress (partial delivery).
     */
    public function updateDeliveryProgress(DeliveryOrder $deliveryOrder, array $itemsDelivered): DeliveryOrder
    {
        if ($deliveryOrder->status !== DocumentStatus::Shipped) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Delivery Order',
                'update delivery progress',
                $deliveryOrder->status->value,
                'shipped'
            );
        }

        return $this->executeInTransaction('update_delivery_progress', function () use ($deliveryOrder, $itemsDelivered) {
            foreach ($itemsDelivered as $itemData) {
                $item = $deliveryOrder->items()->find($itemData['item_id']);
                if ($item) {
                    $newDelivered = (float) $itemData['quantity_delivered'];
                    $orderedQty = (float) $item->quantity;
                    if ($newDelivered > $orderedQty) {
                        throw \App\Exceptions\Domain\BusinessRuleException::quantityValidation(
                            'Jumlah delivered untuk item '.$item->id,
                            $newDelivered,
                            $orderedQty,
                            'exceeds'
                        );
                    }
                    $item->quantity_delivered = $newDelivered;
                    $item->save();
                }
            }

            $allDelivered = $deliveryOrder->items()
                ->whereRaw('quantity_delivered < quantity')
                ->doesntExist();

            if ($allDelivered) {
                $deliveryOrder->transitionTo(DocumentStatus::Delivered, null, [
                    'received_date' => now()->toDateString(),
                ]);
            }

            return $deliveryOrder->fresh(['items']);
        }, ['delivery_order_id' => $deliveryOrder->id, 'items_count' => count($itemsDelivered)]);
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

    /**
     * Deduct inventory when shipping.
     */
    private function deductInventory(DeliveryOrder $deliveryOrder): void
    {
        $warehouse = $deliveryOrder->warehouse;

        foreach ($deliveryOrder->items as $item) {
            if ($item->product_id) {
                $product = \App\Models\Inventory\Product::find($item->product_id);
                if ($product) {
                    $this->inventoryService->stockOut(
                        $product,
                        $warehouse,
                        (int) $item->quantity,
                        'Delivery: '.$deliveryOrder->do_number,
                        DeliveryOrder::class,
                        $deliveryOrder->id
                    );
                }
            }
        }
    }
}
