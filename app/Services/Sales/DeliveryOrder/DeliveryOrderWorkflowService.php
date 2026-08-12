<?php

declare(strict_types=1);

namespace App\Services\Sales\DeliveryOrder;

use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Accounting\Strategies\COGSRecognitionStrategy;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Inventory\InventoryServiceInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Enums\DocumentStatus;
use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\InventoryMovement;
use App\Models\Sales\DeliveryOrder;
use App\Services\Base\Traits\WithEventDispatching;
use App\Services\Base\Traits\WithOperationContext;
use App\Services\Base\Traits\WithTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Handles workflow state transitions for delivery orders.
 *
 * Extracted from DeliveryOrderService as part of the Coordinator Pattern refactoring.
 * This service focuses on confirm, ship, deliver, cancel, reverseShipment, and progress updates.
 *
 * @see \App\Services\Sales\DeliveryOrderService The coordinator service
 */
class DeliveryOrderWorkflowService
{
    use WithEventDispatching;
    use WithOperationContext;
    use WithTransaction;

    protected EventDispatcherInterface $eventDispatcher;

    protected ContextualLoggerInterface $logger;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        private InventoryServiceInterface $inventoryService,
        private COGSRecognitionStrategy $cogsStrategy,
        private JournalServiceInterface $journalService,
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
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

        return $this->executeInTransaction('confirm', function () use ($deliveryOrder, $userId) {
            $deliveryOrder->transitionTo(DocumentStatus::Confirmed, $userId);

            return $deliveryOrder->fresh(['items', 'contact', 'invoice']);
        }, ['delivery_order_id' => $deliveryOrder->id]);
    }

    /**
     * Ship a delivery order.
     *
     * @param  array<string, mixed>  $data
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

        $userId = $data['shipped_by'] ?? $userId;

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
     *
     * @param  array<string, mixed>  $data
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
                'draft, confirmed, atau shipped'
            );
        }

        return $this->executeInTransaction('cancel', function () use ($deliveryOrder, $reason, $userId) {
            $deliveryOrder->transitionTo(DocumentStatus::Cancelled, $userId, [
                'cancellation_reason' => $reason,
            ]);

            return $deliveryOrder->fresh(['items', 'contact', 'invoice']);
        }, ['delivery_order_id' => $deliveryOrder->id]);
    }

    /**
     * Reverse a shipped delivery order.
     *
     * Restores inventory (stock-in for each item that was stock-out'd during ship),
     * reverses any COGS journal entry created for this DO, and transitions to Cancelled.
     *
     * @throws \App\Exceptions\Domain\StateTransitionException If DO is not in Shipped status
     */
    public function reverseShipment(DeliveryOrder $deliveryOrder, string $reason): DeliveryOrder
    {
        if ($deliveryOrder->status !== DocumentStatus::Shipped) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Delivery Order',
                'dibatalkan (reverse shipment)',
                $deliveryOrder->status->value,
                'shipped'
            );
        }

        return $this->executeInTransaction('reverse_shipment', function () use ($deliveryOrder, $reason) {
            // Restore inventory for each item
            if ($deliveryOrder->warehouse_id) {
                $this->restoreInventory($deliveryOrder);
            }

            // Reverse COGS journal entry (created by COGSOnDeliveryStrategy)
            $cogsJournalEntries = JournalEntry::where('source_type', DeliveryOrder::class)
                ->where('source_id', $deliveryOrder->id)
                ->where('is_reversed', false)
                ->get();

            foreach ($cogsJournalEntries as $je) {
                $this->journalService->reverseEntry(
                    $je,
                    "Pembatalan pengiriman: {$deliveryOrder->do_number} — {$reason}"
                );
            }

            // Transition to Cancelled via state machine
            $deliveryOrder->transitionTo(DocumentStatus::Cancelled, $this->getUserId(), [
                'cancellation_reason' => $reason,
            ]);

            return $deliveryOrder->fresh(['items', 'contact', 'invoice']);
        }, ['delivery_order_id' => $deliveryOrder->id, 'reason' => $reason]);
    }

    /**
     * Update delivery progress (partial delivery).
     *
     * @param  array<int, array{item_id: int, quantity_delivered: float}>  $itemsDelivered
     */
    public function updateDeliveryProgress(DeliveryOrder $deliveryOrder, array $itemsDelivered, ?int $userId = null): DeliveryOrder
    {
        if ($deliveryOrder->status !== DocumentStatus::Shipped) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Delivery Order',
                'update delivery progress',
                $deliveryOrder->status->value,
                'shipped'
            );
        }

        return $this->executeInTransaction('update_delivery_progress', function () use ($deliveryOrder, $itemsDelivered, $userId) {
            foreach ($itemsDelivered as $itemData) {
                $item = $deliveryOrder->items()->find($itemData['item_id']);
                if (! $item) {
                    throw new \InvalidArgumentException(
                        "Item #{$itemData['item_id']} bukan milik delivery order ini."
                    );
                }

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

            $allDelivered = $deliveryOrder->items()
                ->whereRaw('quantity_delivered < quantity')
                ->doesntExist();

            if ($allDelivered) {
                $deliveryOrder->transitionTo(DocumentStatus::Delivered, $userId, [
                    'received_date' => now()->toDateString(),
                ]);
            }

            return $deliveryOrder->fresh(['items']);
        }, ['delivery_order_id' => $deliveryOrder->id, 'items_count' => count($itemsDelivered)]);
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
                        (int) round((float) $item->quantity),
                        'Delivery: '.$deliveryOrder->do_number,
                        DeliveryOrder::class,
                        $deliveryOrder->id
                    );
                }
            }
        }
    }

    /**
     * Restore inventory when reversing a shipment (stock-in for each item).
     *
     * Looks up original movement's unit_cost to maintain cost accuracy.
     */
    private function restoreInventory(DeliveryOrder $deliveryOrder): void
    {
        $warehouse = $deliveryOrder->warehouse;

        foreach ($deliveryOrder->items as $item) {
            if (! $item->product_id) {
                continue;
            }

            $product = \App\Models\Inventory\Product::find($item->product_id);
            if (! $product) {
                continue;
            }

            // Look up original stock-out movement to get unit_cost
            $originalMovement = InventoryMovement::where('reference_type', DeliveryOrder::class)
                ->where('reference_id', $deliveryOrder->id)
                ->where('product_id', $item->product_id)
                ->where('type', InventoryMovement::TYPE_OUT)
                ->first();

            $unitCost = $originalMovement
                ? $originalMovement->unit_cost
                : ($product->purchase_price ?? 0);

            $this->inventoryService->stockIn(
                $product,
                $warehouse,
                (int) round((float) $item->quantity),
                (int) $unitCost,
                'Pembatalan pengiriman: '.$deliveryOrder->do_number,
                DeliveryOrder::class,
                $deliveryOrder->id
            );
        }
    }
}
