<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Inventory;

use App\Contracts\Repositories\Inventory\ProductStockRepositoryInterface;
use App\Infrastructure\Repositories\EloquentRepository;
use App\Models\Inventory\ProductStock;
use Illuminate\Support\Collection;

/**
 * Eloquent implementation of ProductStock repository.
 *
 * @extends EloquentRepository<ProductStock>
 */
class EloquentProductStockRepository extends EloquentRepository implements ProductStockRepositoryInterface
{
    protected string $modelClass = ProductStock::class;

    protected array $with = ['product', 'warehouse'];

    public function getStock(int $productId, int $warehouseId): ?ProductStock
    {
        return $this->findOneBy([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
        ]);
    }

    public function getOrCreateStock(int $productId, int $warehouseId): ProductStock
    {
        return ProductStock::firstOrCreate(
            ['product_id' => $productId, 'warehouse_id' => $warehouseId],
            ['quantity' => 0, 'reserved_quantity' => 0, 'average_cost' => 0, 'total_value' => 0]
        );
    }

    public function getStockByProduct(int $productId): Collection
    {
        return $this->findBy(['product_id' => $productId]);
    }

    public function getStockByWarehouse(int $warehouseId): Collection
    {
        return $this->newQuery()
            ->where('warehouse_id', $warehouseId)
            ->where('quantity', '>', 0)
            ->orderBy('product_id')
            ->get();
    }

    public function getTotalAvailable(int $productId): float
    {
        return (float) $this->newQuery()
            ->where('product_id', $productId)
            ->selectRaw('COALESCE(SUM(quantity - reserved_quantity), 0) as available')
            ->value('available');
    }

    public function hasSufficientStock(int $productId, int $warehouseId, float $required): bool
    {
        $stock = $this->getStock($productId, $warehouseId);

        if ($stock === null) {
            return false;
        }

        return $stock->getAvailableQuantity() >= $required;
    }

    public function findLowStock(): Collection
    {
        return $this->newQuery()
            ->with(['product', 'warehouse'])
            ->whereHas('product', function ($query) {
                $query->where('min_stock', '>', 0);
            })
            ->whereRaw('quantity - reserved_quantity <= (SELECT min_stock FROM products WHERE products.id = product_stocks.product_id)')
            ->get();
    }

    public function reserve(int $productId, int $warehouseId, float $quantity): bool
    {
        $stock = $this->getStock($productId, $warehouseId);

        if ($stock === null || $stock->getAvailableQuantity() < $quantity) {
            return false;
        }

        $stock->reserved_quantity = (float) $stock->reserved_quantity + $quantity;
        $stock->save();

        return true;
    }

    public function releaseReservation(int $productId, int $warehouseId, float $quantity): bool
    {
        $stock = $this->getStock($productId, $warehouseId);

        if ($stock === null) {
            return false;
        }

        $newReserved = max(0, (float) $stock->reserved_quantity - $quantity);
        $stock->reserved_quantity = $newReserved;
        $stock->save();

        return true;
    }
}
