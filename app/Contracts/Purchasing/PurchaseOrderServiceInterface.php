<?php

declare(strict_types=1);

namespace App\Contracts\Purchasing;

use App\Models\Purchasing\Bill;
use App\Models\Purchasing\PurchaseOrder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Interface for Purchase Order service operations.
 */
interface PurchaseOrderServiceInterface
{
    /**
     * Create a new purchase order.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PurchaseOrder;

    /**
     * Update an existing purchase order.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(PurchaseOrder $purchaseOrder, array $data): PurchaseOrder;

    /**
     * Delete a draft purchase order (and line items).
     */
    public function delete(PurchaseOrder $purchaseOrder): bool;

    /**
     * Submit a purchase order for approval.
     */
    public function submit(PurchaseOrder $purchaseOrder, ?int $userId = null): PurchaseOrder;

    /**
     * Approve a purchase order.
     */
    public function approve(PurchaseOrder $purchaseOrder, ?int $userId = null): PurchaseOrder;

    /**
     * Reject a purchase order.
     */
    public function reject(PurchaseOrder $purchaseOrder, string $reason, ?int $userId = null): PurchaseOrder;

    /**
     * Cancel a purchase order.
     */
    public function cancel(PurchaseOrder $purchaseOrder, string $reason, ?int $userId = null): PurchaseOrder;

    /**
     * Convert a purchase order to a bill.
     */
    public function convertToBill(PurchaseOrder $purchaseOrder): Bill;

    /**
     * Duplicate a purchase order.
     */
    public function duplicate(PurchaseOrder $purchaseOrder): PurchaseOrder;

    /**
     * Get outstanding purchase orders.
     *
     * @return Collection<int, PurchaseOrder>
     */
    public function getOutstanding(?int $contactId = null): Collection;

    /**
     * Get purchase order statistics.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(?string $startDate = null, ?string $endDate = null): array;
}
