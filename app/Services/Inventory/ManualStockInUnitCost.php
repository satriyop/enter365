<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use Illuminate\Validation\ValidationException;

/**
 * SPA stock-in may send 0 to mean "keep current average".
 * Recording 0 would dilute inventory value.
 */
final class ManualStockInUnitCost
{
    public static function resolve(Product $product, Warehouse $warehouse, int $entered): int
    {
        if ($entered > 0) {
            return $entered;
        }

        $stock = ProductStock::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();

        $fallback = (int) ($stock?->average_cost ?: $product->purchase_price ?: 0);

        if ($fallback <= 0) {
            throw ValidationException::withMessages([
                'unit_cost' => 'Harga satuan wajib diisi untuk stok masuk.',
            ]);
        }

        return $fallback;
    }
}
