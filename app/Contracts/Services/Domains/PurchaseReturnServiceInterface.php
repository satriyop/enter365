<?php

declare(strict_types=1);

namespace App\Contracts\Services\Domains;

use App\Models\Purchasing\Bill;
use App\Models\Purchasing\PurchaseReturn;
use Illuminate\Database\Eloquent\Collection;

/**
 * Interface for Purchase Return service operations.
 */
interface PurchaseReturnServiceInterface
{
    /**
     * Create a new purchase return.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PurchaseReturn;

    /**
     * Update an existing purchase return.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(PurchaseReturn $purchaseReturn, array $data): PurchaseReturn;

    /**
     * Delete a purchase return.
     */
    public function delete(PurchaseReturn $purchaseReturn): bool;

    /**
     * Create purchase return from bill.
     *
     * @param  array<string, mixed>  $data
     */
    public function createFromBill(Bill $bill, array $data = []): PurchaseReturn;

    /**
     * Submit a purchase return for approval.
     */
    public function submit(PurchaseReturn $purchaseReturn, ?int $userId = null): PurchaseReturn;

    /**
     * Approve a purchase return.
     */
    public function approve(PurchaseReturn $purchaseReturn, ?int $userId = null): PurchaseReturn;

    /**
     * Reject a purchase return.
     */
    public function reject(PurchaseReturn $purchaseReturn, ?string $reason = null, ?int $userId = null): PurchaseReturn;

    /**
     * Complete a purchase return.
     */
    public function complete(PurchaseReturn $purchaseReturn, ?int $userId = null): PurchaseReturn;

    /**
     * Cancel a purchase return.
     */
    public function cancel(PurchaseReturn $purchaseReturn, ?string $reason = null): PurchaseReturn;

    /**
     * Get purchase returns for a bill.
     *
     * @return Collection<int, PurchaseReturn>
     */
    public function getForBill(Bill $bill): Collection;
}
