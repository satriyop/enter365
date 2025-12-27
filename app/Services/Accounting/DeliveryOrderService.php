<?php

namespace App\Services\Accounting;

use App\Models\Accounting\DeliveryOrder;
use App\Models\Accounting\DeliveryOrderItem;
use App\Models\Accounting\Invoice;
use Illuminate\Support\Facades\DB;

class DeliveryOrderService
{
    public function __construct(
        private InventoryService $inventoryService
    ) {}

    /**
     * Create a new delivery order.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): DeliveryOrder
    {
        return DB::transaction(function () use ($data) {
            $deliveryOrder = new DeliveryOrder($data);
            $deliveryOrder->do_number = DeliveryOrder::generateDoNumber();
            $deliveryOrder->save();

            // Create items
            if (! empty($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    $this->createItem($deliveryOrder, $itemData);
                }
            }

            return $deliveryOrder->fresh(['items', 'contact', 'invoice', 'warehouse']);
        });
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
            $deliveryOrder->do_number = DeliveryOrder::generateDoNumber();
            $deliveryOrder->save();

            // Create items from invoice items
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
     * Update a delivery order.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(DeliveryOrder $deliveryOrder, array $data): DeliveryOrder
    {
        if (! $deliveryOrder->canBeEdited()) {
            throw new \InvalidArgumentException('Delivery order can only be edited in draft status.');
        }

        return DB::transaction(function () use ($deliveryOrder, $data) {
            $deliveryOrder->fill($data);
            $deliveryOrder->save();

            // Update items if provided
            if (isset($data['items'])) {
                // Remove existing items and recreate
                $deliveryOrder->items()->delete();

                foreach ($data['items'] as $itemData) {
                    $this->createItem($deliveryOrder, $itemData);
                }
            }

            return $deliveryOrder->fresh(['items', 'contact', 'invoice', 'warehouse']);
        });
    }

    /**
     * Delete a delivery order.
     */
    public function delete(DeliveryOrder $deliveryOrder): bool
    {
        if (! $deliveryOrder->canBeEdited()) {
            throw new \InvalidArgumentException('Only draft delivery orders can be deleted.');
        }

        return DB::transaction(function () use ($deliveryOrder) {
            $deliveryOrder->items()->delete();

            return $deliveryOrder->delete();
        });
    }

    /**
     * Confirm a delivery order.
     */
    public function confirm(DeliveryOrder $deliveryOrder, ?int $userId = null): DeliveryOrder
    {
        if (! $deliveryOrder->canBeConfirmed()) {
            throw new \InvalidArgumentException('Delivery order cannot be confirmed. Ensure it has items and is in draft status.');
        }

        $deliveryOrder->status = DeliveryOrder::STATUS_CONFIRMED;
        $deliveryOrder->confirmed_by = $userId;
        $deliveryOrder->confirmed_at = now();
        $deliveryOrder->save();

        return $deliveryOrder->fresh();
    }

    /**
     * Ship a delivery order.
     *
     * @param  array<string, mixed>  $data
     */
    public function ship(DeliveryOrder $deliveryOrder, array $data = []): DeliveryOrder
    {
        if (! $deliveryOrder->canBeShipped()) {
            throw new \InvalidArgumentException('Only confirmed delivery orders can be shipped.');
        }

        return DB::transaction(function () use ($deliveryOrder, $data) {
            $deliveryOrder->status = DeliveryOrder::STATUS_SHIPPED;
            $deliveryOrder->shipped_by = $data['shipped_by'] ?? null;
            $deliveryOrder->shipped_at = now();
            $deliveryOrder->shipping_date = $data['shipping_date'] ?? now()->toDateString();
            $deliveryOrder->tracking_number = $data['tracking_number'] ?? $deliveryOrder->tracking_number;
            $deliveryOrder->driver_name = $data['driver_name'] ?? $deliveryOrder->driver_name;
            $deliveryOrder->vehicle_number = $data['vehicle_number'] ?? $deliveryOrder->vehicle_number;
            $deliveryOrder->save();

            // Deduct inventory if warehouse is set
            if ($deliveryOrder->warehouse_id) {
                $this->deductInventory($deliveryOrder);
            }

            return $deliveryOrder->fresh();
        });
    }

    /**
     * Mark delivery order as delivered.
     *
     * @param  array<string, mixed>  $data
     */
    public function deliver(DeliveryOrder $deliveryOrder, array $data = []): DeliveryOrder
    {
        if (! $deliveryOrder->canBeDelivered()) {
            throw new \InvalidArgumentException('Only shipped delivery orders can be marked as delivered.');
        }

        $deliveryOrder->status = DeliveryOrder::STATUS_DELIVERED;
        $deliveryOrder->delivered_by = $data['delivered_by'] ?? null;
        $deliveryOrder->delivered_at = now();
        $deliveryOrder->received_date = $data['received_date'] ?? now()->toDateString();
        $deliveryOrder->received_by = $data['received_by'] ?? null;
        $deliveryOrder->delivery_notes = $data['delivery_notes'] ?? null;
        $deliveryOrder->save();

        // Mark all items as fully delivered
        $deliveryOrder->items()->update([
            'quantity_delivered' => DB::raw('quantity'),
        ]);

        return $deliveryOrder->fresh(['items']);
    }

    /**
     * Cancel a delivery order.
     */
    public function cancel(DeliveryOrder $deliveryOrder, ?string $reason = null): DeliveryOrder
    {
        if (! $deliveryOrder->canBeCancelled()) {
            throw new \InvalidArgumentException('Only draft or confirmed delivery orders can be cancelled.');
        }

        $deliveryOrder->status = DeliveryOrder::STATUS_CANCELLED;
        if ($reason) {
            $deliveryOrder->notes = ($deliveryOrder->notes ? $deliveryOrder->notes."\n" : '').'Cancelled: '.$reason;
        }
        $deliveryOrder->save();

        return $deliveryOrder->fresh();
    }

    /**
     * Update delivery progress (partial delivery).
     *
     * @param  array<int, array{item_id: int, quantity_delivered: float}>  $itemsDelivered
     */
    public function updateDeliveryProgress(DeliveryOrder $deliveryOrder, array $itemsDelivered): DeliveryOrder
    {
        if ($deliveryOrder->status !== DeliveryOrder::STATUS_SHIPPED) {
            throw new \InvalidArgumentException('Only shipped delivery orders can have delivery progress updated.');
        }

        return DB::transaction(function () use ($deliveryOrder, $itemsDelivered) {
            foreach ($itemsDelivered as $itemData) {
                $item = $deliveryOrder->items()->find($itemData['item_id']);
                if ($item) {
                    $newDelivered = $itemData['quantity_delivered'];
                    if ($newDelivered > $item->quantity) {
                        throw new \InvalidArgumentException("Delivered quantity cannot exceed ordered quantity for item {$item->id}.");
                    }
                    $item->quantity_delivered = $newDelivered;
                    $item->save();
                }
            }

            // Check if all items are fully delivered
            $allDelivered = $deliveryOrder->items()
                ->whereRaw('quantity_delivered < quantity')
                ->doesntExist();

            if ($allDelivered) {
                $deliveryOrder->status = DeliveryOrder::STATUS_DELIVERED;
                $deliveryOrder->delivered_at = now();
                $deliveryOrder->received_date = now()->toDateString();
                $deliveryOrder->save();
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
            $newDo->status = DeliveryOrder::STATUS_DRAFT;
            $newDo->do_date = now()->toDateString();
            $newDo->save();

            // Duplicate items
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
     * Create a delivery order item.
     *
     * @param  array<string, mixed>  $data
     */
    private function createItem(DeliveryOrder $deliveryOrder, array $data): DeliveryOrderItem
    {
        $item = new DeliveryOrderItem($data);
        $item->delivery_order_id = $deliveryOrder->id;
        $item->save();

        return $item;
    }

    /**
     * Deduct inventory when shipping.
     */
    private function deductInventory(DeliveryOrder $deliveryOrder): void
    {
        foreach ($deliveryOrder->items as $item) {
            if ($item->product_id) {
                $this->inventoryService->stockOut([
                    'product_id' => $item->product_id,
                    'warehouse_id' => $deliveryOrder->warehouse_id,
                    'quantity' => $item->quantity,
                    'reference_type' => DeliveryOrder::class,
                    'reference_id' => $deliveryOrder->id,
                    'notes' => 'Delivery: '.$deliveryOrder->do_number,
                ]);
            }
        }
    }
}
