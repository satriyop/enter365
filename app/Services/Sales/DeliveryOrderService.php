<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Contracts\Sales\DeliveryOrderServiceInterface;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Sales\DeliveryOrder;
use App\Models\Sales\Invoice;
use App\Services\Sales\DeliveryOrder\DeliveryOrderCrudService;
use App\Services\Sales\DeliveryOrder\DeliveryOrderWorkflowService;
use App\Support\OperationContext;
use Illuminate\Database\Eloquent\Collection;

/**
 * Delivery order service coordinator.
 *
 * Thin coordinator that delegates to focused services:
 * - DeliveryOrderCrudService: create, update, delete, createFromInvoice, createFromWorkOrder, duplicate, getForInvoice
 * - DeliveryOrderWorkflowService: confirm, ship, deliver, cancel, reverseShipment, updateDeliveryProgress
 *
 * @see \App\Services\Sales\DeliveryOrder\DeliveryOrderCrudService
 * @see \App\Services\Sales\DeliveryOrder\DeliveryOrderWorkflowService
 */
class DeliveryOrderService implements DeliveryOrderServiceInterface
{
    public function __construct(
        private DeliveryOrderCrudService $crud,
        private DeliveryOrderWorkflowService $workflow,
    ) {}

    /**
     * Set operation context for all underlying services.
     *
     * Returns a clone with context-aware services for fluent chaining.
     */
    public function withContext(OperationContext $context): static
    {
        $clone = clone $this;
        $clone->crud = $this->crud->withContext($context);
        $clone->workflow = $this->workflow->withContext($context);

        return $clone;
    }

    // ─────────────────────────────────────────────────────────────
    // CRUD Operations (delegated to DeliveryOrderCrudService)
    // ─────────────────────────────────────────────────────────────

    /**
     * Create a new delivery order.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): DeliveryOrder
    {
        return $this->crud->create($data);
    }

    /**
     * Update an existing delivery order.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(DeliveryOrder $deliveryOrder, array $data): DeliveryOrder
    {
        return $this->crud->update($deliveryOrder, $data);
    }

    /**
     * Delete a delivery order.
     */
    public function delete(DeliveryOrder $deliveryOrder): bool
    {
        return $this->crud->delete($deliveryOrder);
    }

    /**
     * Create delivery order from invoice.
     *
     * @param  array<string, mixed>  $data
     */
    public function createFromInvoice(Invoice $invoice, array $data = []): DeliveryOrder
    {
        return $this->crud->createFromInvoice($invoice, $data);
    }

    /**
     * Create delivery order from work order.
     */
    public function createFromWorkOrder(WorkOrder $workOrder): DeliveryOrder
    {
        return $this->crud->createFromWorkOrder($workOrder);
    }

    /**
     * Duplicate a delivery order.
     */
    public function duplicate(DeliveryOrder $deliveryOrder): DeliveryOrder
    {
        return $this->crud->duplicate($deliveryOrder);
    }

    /**
     * Get delivery orders for an invoice.
     *
     * @return Collection<int, DeliveryOrder>
     */
    public function getForInvoice(Invoice $invoice): Collection
    {
        return $this->crud->getForInvoice($invoice);
    }

    // ─────────────────────────────────────────────────────────────
    // Workflow Operations (delegated to DeliveryOrderWorkflowService)
    // ─────────────────────────────────────────────────────────────

    /**
     * Confirm a delivery order.
     */
    public function confirm(DeliveryOrder $deliveryOrder, ?int $userId = null): DeliveryOrder
    {
        return $this->workflow->confirm($deliveryOrder, $userId);
    }

    /**
     * Ship a delivery order.
     *
     * @param  array<string, mixed>  $data
     */
    public function ship(DeliveryOrder $deliveryOrder, array $data = [], ?int $userId = null): DeliveryOrder
    {
        return $this->workflow->ship($deliveryOrder, $data, $userId);
    }

    /**
     * Mark delivery order as delivered.
     *
     * @param  array<string, mixed>  $data
     */
    public function deliver(DeliveryOrder $deliveryOrder, array $data = [], ?int $userId = null): DeliveryOrder
    {
        return $this->workflow->deliver($deliveryOrder, $data, $userId);
    }

    /**
     * Cancel a delivery order.
     */
    public function cancel(DeliveryOrder $deliveryOrder, ?string $reason = null, ?int $userId = null): DeliveryOrder
    {
        return $this->workflow->cancel($deliveryOrder, $reason, $userId);
    }

    /**
     * Reverse a shipped delivery order.
     */
    public function reverseShipment(DeliveryOrder $deliveryOrder, string $reason): DeliveryOrder
    {
        return $this->workflow->reverseShipment($deliveryOrder, $reason);
    }

    /**
     * Update delivery progress (partial delivery).
     *
     * @param  array<int, array{item_id: int, quantity_delivered: float}>  $itemsDelivered
     */
    public function updateDeliveryProgress(DeliveryOrder $deliveryOrder, array $itemsDelivered, ?int $userId = null): DeliveryOrder
    {
        return $this->workflow->updateDeliveryProgress($deliveryOrder, $itemsDelivered, $userId);
    }
}
