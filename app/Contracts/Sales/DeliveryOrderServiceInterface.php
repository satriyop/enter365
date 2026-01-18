<?php

declare(strict_types=1);

namespace App\Contracts\Sales;

use App\Models\Sales\DeliveryOrder;
use App\Models\Sales\Invoice;
use Illuminate\Database\Eloquent\Collection;

/**
 * Interface for Delivery Order service operations.
 */
interface DeliveryOrderServiceInterface
{
    /**
     * Create a new delivery order.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): DeliveryOrder;

    /**
     * Update an existing delivery order.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(DeliveryOrder $deliveryOrder, array $data): DeliveryOrder;

    /**
     * Delete a delivery order.
     */
    public function delete(DeliveryOrder $deliveryOrder): bool;

    /**
     * Create delivery order from invoice.
     *
     * @param  array<string, mixed>  $data
     */
    public function createFromInvoice(Invoice $invoice, array $data = []): DeliveryOrder;

    /**
     * Confirm a delivery order.
     */
    public function confirm(DeliveryOrder $deliveryOrder, ?int $userId = null): DeliveryOrder;

    /**
     * Ship a delivery order.
     *
     * @param  array<string, mixed>  $data
     */
    public function ship(DeliveryOrder $deliveryOrder, array $data = []): DeliveryOrder;

    /**
     * Mark delivery order as delivered.
     *
     * @param  array<string, mixed>  $data
     */
    public function deliver(DeliveryOrder $deliveryOrder, array $data = []): DeliveryOrder;

    /**
     * Cancel a delivery order.
     */
    public function cancel(DeliveryOrder $deliveryOrder, ?string $reason = null): DeliveryOrder;

    /**
     * Update delivery progress (partial delivery).
     *
     * @param  array<int, array{item_id: int, quantity_delivered: float}>  $itemsDelivered
     */
    public function updateDeliveryProgress(DeliveryOrder $deliveryOrder, array $itemsDelivered): DeliveryOrder;

    /**
     * Duplicate a delivery order.
     */
    public function duplicate(DeliveryOrder $deliveryOrder): DeliveryOrder;

    /**
     * Get delivery orders for an invoice.
     *
     * @return Collection<int, DeliveryOrder>
     */
    public function getForInvoice(Invoice $invoice): Collection;
}
