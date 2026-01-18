<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Contracts\Sales\DeliveryOrderServiceInterface;
use App\Enums\DocumentStatus;
use App\Models\Sales\DeliveryOrder;
use App\Models\Sales\DeliveryOrderItem;
use App\Models\Sales\Invoice;
use App\Services\Base\AbstractDocumentService;
use App\Services\Inventory\InventoryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeliveryOrderService extends AbstractDocumentService implements DeliveryOrderServiceInterface
{
    public function __construct(
        private InventoryService $inventoryService,
        private \App\Contracts\Shared\DocumentNumberGeneratorInterface $numberGenerator
    ) {}

    protected function getModelClass(): string
    {
        return DeliveryOrder::class;
    }

    protected function getItemRelation(): string
    {
        return 'items';
    }

    protected function generateDocumentNumber(?Model $context = null): string
    {
        $prefix = 'DO-'.now()->format('Ym').'-';

        return $this->numberGenerator->generate($prefix, 'delivery_orders', 'do_number');
    }

    protected function getDocumentNumberField(): string
    {
        return 'do_number';
    }

    protected function getInitialStatus(): string
    {
        return DocumentStatus::Draft->value;
    }

    protected function getEagerLoadRelations(): array
    {
        return ['items', 'contact', 'invoice', 'warehouse'];
    }

    public function create(array $data): DeliveryOrder
    {
        /** @var DeliveryOrder $result */
        $result = parent::create($data);

        return $result;
    }

    public function update(Model $document, array $data): DeliveryOrder
    {
        /** @var DeliveryOrder $document */
        /** @var DeliveryOrder $result */
        $result = parent::update($document, $data);

        return $result;
    }

    protected function validateEditable(Model $document): void
    {
        /** @var DeliveryOrder $document */
        if (! $document->isEditable()) {
            throw new InvalidArgumentException('Delivery order can only be edited in draft status.');
        }
    }

    protected function validateDeletable(Model $document): void
    {
        /** @var DeliveryOrder $document */
        if (! $document->isEditable()) {
            throw new InvalidArgumentException('Only draft delivery orders can be deleted.');
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
        return DB::transaction(function () use ($invoice, $data) {
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
            $deliveryOrder->do_number = $this->numberGenerator->generate('DO-'.now()->format('Ym').'-', 'delivery_orders', 'do_number');
            $deliveryOrder->save();

            foreach ($invoice->items as $invoiceItem) {
                $item = new DeliveryOrderItem;
                $item->delivery_order_id = $deliveryOrder->id;
                $item->fillFromInvoiceItem($invoiceItem);
                $item->save();
            }

            return $deliveryOrder->fresh(['items', 'contact', 'invoice', 'warehouse']);
        });
    }

    /**
     * Confirm a delivery order.
     */
    public function confirm(DeliveryOrder $deliveryOrder, ?int $userId = null): DeliveryOrder
    {
        if (! $deliveryOrder->stateMachine()->canConfirm()) {
            throw new InvalidArgumentException('Delivery order cannot be confirmed. Ensure it has items and is in draft status.');
        }

        $deliveryOrder->transitionTo(DocumentStatus::Confirmed, $userId);

        return $deliveryOrder->fresh(['items', 'contact', 'invoice']);
    }

    /**
     * Ship a delivery order.
     */
    public function ship(DeliveryOrder $deliveryOrder, array $data = [], ?int $userId = null): DeliveryOrder
    {
        if (! $deliveryOrder->stateMachine()->canShip()) {
            throw new InvalidArgumentException('Only confirmed delivery orders can be shipped.');
        }

        return DB::transaction(function () use ($deliveryOrder, $data, $userId) {
            $deliveryOrder->update([
                'tracking_number' => $data['tracking_number'] ?? $deliveryOrder->tracking_number,
                'driver_name' => $data['driver_name'] ?? $deliveryOrder->driver_name,
                'vehicle_number' => $data['vehicle_number'] ?? $deliveryOrder->vehicle_number,
            ]);

            $deliveryOrder->transitionTo(DocumentStatus::Shipped, $userId, [
                'shipped_by' => $data['shipped_by'] ?? null,
                'shipping_date' => $data['shipping_date'] ?? now()->toDateString(),
            ]);

            if ($deliveryOrder->warehouse_id) {
                $this->deductInventory($deliveryOrder);
            }

            return $deliveryOrder->fresh(['items', 'contact', 'invoice']);
        });
    }

    /**
     * Mark delivery order as delivered.
     */
    public function deliver(DeliveryOrder $deliveryOrder, array $data = [], ?int $userId = null): DeliveryOrder
    {
        if (! $deliveryOrder->stateMachine()->canDeliver()) {
            throw new InvalidArgumentException('Only shipped delivery orders can be marked as delivered.');
        }

        return DB::transaction(function () use ($deliveryOrder, $data, $userId) {
            $deliveryOrder->update([
                'received_by' => $data['received_by'] ?? null,
                'delivery_notes' => $data['delivery_notes'] ?? null,
            ]);

            $deliveryOrder->items()->update([
                'quantity_delivered' => DB::raw('quantity'),
            ]);

            $deliveryOrder->transitionTo(DocumentStatus::Delivered, $userId, [
                'received_date' => $data['received_date'] ?? now()->toDateString(),
            ]);

            return $deliveryOrder->fresh(['items']);
        });
    }

    /**
     * Cancel a delivery order.
     */
    public function cancel(DeliveryOrder $deliveryOrder, ?string $reason = null, ?int $userId = null): DeliveryOrder
    {
        if (! $deliveryOrder->stateMachine()->canCancel()) {
            throw new InvalidArgumentException('Only draft, confirmed, or shipped delivery orders can be cancelled.');
        }

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
            throw new InvalidArgumentException('Only shipped delivery orders can have delivery progress updated.');
        }

        return DB::transaction(function () use ($deliveryOrder, $itemsDelivered) {
            foreach ($itemsDelivered as $itemData) {
                $item = $deliveryOrder->items()->find($itemData['item_id']);
                if ($item) {
                    $newDelivered = $itemData['quantity_delivered'];
                    if ($newDelivered > $item->quantity) {
                        throw new InvalidArgumentException("Delivered quantity cannot exceed ordered quantity for item {$item->id}.");
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
        });
    }

    /**
     * Duplicate a delivery order.
     */
    public function duplicate(DeliveryOrder $deliveryOrder): DeliveryOrder
    {
        return DB::transaction(function () use ($deliveryOrder) {
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
            $newDo->do_number = DeliveryOrder::generateDoNumber();
            $newDo->status = DocumentStatus::Draft;
            $newDo->do_date = now()->toDateString();
            $newDo->save();

            foreach ($deliveryOrder->items as $item) {
                $newItem = $item->replicate(['quantity_delivered']);
                $newItem->delivery_order_id = $newDo->id;
                $newItem->quantity_delivered = 0;
                $newItem->save();
            }

            return $newDo->fresh(['items', 'contact', 'invoice']);
        });
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
