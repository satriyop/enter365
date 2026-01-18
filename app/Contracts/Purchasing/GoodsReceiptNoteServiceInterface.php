<?php

declare(strict_types=1);

namespace App\Contracts\Purchasing;

use App\Models\Purchasing\GoodsReceiptNote;
use App\Models\Purchasing\PurchaseOrder;
use Illuminate\Support\Collection;

/**
 * Interface for Goods Receipt Note service operations.
 */
interface GoodsReceiptNoteServiceInterface
{
    /**
     * Create a new goods receipt note.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): GoodsReceiptNote;

    /**
     * Update an existing goods receipt note.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(GoodsReceiptNote $grn, array $data): GoodsReceiptNote;

    /**
     * Delete a goods receipt note.
     */
    public function delete(GoodsReceiptNote $grn): void;

    /**
     * Create GRN from purchase order.
     *
     * @param  array<string, mixed>  $data
     */
    public function createFromPurchaseOrder(PurchaseOrder $po, array $data): GoodsReceiptNote;

    /**
     * Complete a GRN and update inventory.
     */
    public function complete(GoodsReceiptNote $grn, int $userId): GoodsReceiptNote;

    /**
     * Cancel a GRN.
     */
    public function cancel(GoodsReceiptNote $grn, int $userId): GoodsReceiptNote;

    /**
     * Get GRNs for a purchase order.
     *
     * @return Collection<int, GoodsReceiptNote>
     */
    public function getForPurchaseOrder(PurchaseOrder $po): Collection;
}
