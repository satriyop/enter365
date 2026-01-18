<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\PurchaseReturns\Handlers;

use App\Contracts\Handlers\PurchaseReturnApprovalHandlerInterface;
use App\Contracts\Services\Domains\InventoryServiceInterface;
use App\Models\Purchasing\PurchaseReturn;

/**
 * Handles inventory stock-out when a purchase return is approved.
 *
 * For purchase returns, items go OUT of inventory (returned to supplier).
 * Only processes items with inventory tracking enabled.
 */
class InventoryReturnHandler implements PurchaseReturnApprovalHandlerInterface
{
    public function __construct(
        private InventoryServiceInterface $inventoryService
    ) {}

    public function handle(PurchaseReturn $purchaseReturn): void
    {
        $purchaseReturn->loadMissing(['items.product', 'warehouse']);

        $warehouse = $purchaseReturn->warehouse;

        foreach ($purchaseReturn->items as $item) {
            if (! $item->product_id) {
                continue;
            }

            $product = $item->product;
            if (! $product || ! $product->track_inventory) {
                continue;
            }

            $this->inventoryService->stockOut(
                $product,
                $warehouse,
                (int) $item->quantity,
                'Retur pembelian: '.$purchaseReturn->return_number,
                PurchaseReturn::class,
                $purchaseReturn->id
            );
        }
    }

    public function priority(): int
    {
        // Inventory runs first (10) before journal entries (20)
        return 10;
    }

    public function shouldHandle(PurchaseReturn $purchaseReturn): bool
    {
        // Only handle if warehouse is specified
        return $purchaseReturn->warehouse_id !== null;
    }
}
