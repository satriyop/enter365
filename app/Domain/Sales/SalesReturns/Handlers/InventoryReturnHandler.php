<?php

declare(strict_types=1);

namespace App\Domain\Sales\SalesReturns\Handlers;

use App\Contracts\Handlers\SalesReturnApprovalHandlerInterface;
use App\Contracts\Services\Domains\InventoryServiceInterface;
use App\Models\Inventory\Product;
use App\Models\Sales\SalesReturn;

/**
 * Handles inventory stock-in when a sales return is approved.
 *
 * Returns goods back to inventory when customer returns them.
 */
class InventoryReturnHandler implements SalesReturnApprovalHandlerInterface
{
    public function __construct(
        private InventoryServiceInterface $inventoryService
    ) {}

    public function handle(SalesReturn $salesReturn): void
    {
        $warehouse = $salesReturn->warehouse;

        foreach ($salesReturn->items as $item) {
            if (! $item->product_id) {
                continue;
            }

            $product = Product::find($item->product_id);
            if (! $product || ! $product->track_inventory) {
                continue;
            }

            $this->inventoryService->stockIn(
                $product,
                $warehouse,
                (int) $item->quantity,
                $item->unit_price,
                'Retur penjualan: '.$salesReturn->return_number,
                SalesReturn::class,
                $salesReturn->id
            );
        }
    }

    public function priority(): int
    {
        // Inventory runs first - before journal entries
        return 10;
    }

    public function shouldHandle(SalesReturn $salesReturn): bool
    {
        // Only process if warehouse is specified
        return $salesReturn->warehouse_id !== null;
    }
}
