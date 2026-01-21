<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\Inventory;

use App\Contracts\Repositories\RepositoryInterface;
use App\Models\Inventory\ProductStock;
use Illuminate\Support\Collection;

/**
 * Repository interface for ProductStock entities.
 *
 * @extends RepositoryInterface<ProductStock>
 */
interface ProductStockRepositoryInterface extends RepositoryInterface
{
    /**
     * Get stock for product at warehouse.
     */
    public function getStock(int $productId, int $warehouseId): ?ProductStock;

    /**
     * Get stock for product at warehouse, create if not exists.
     */
    public function getOrCreateStock(int $productId, int $warehouseId): ProductStock;

    /**
     * Get all stock for product across warehouses.
     *
     * @return Collection<int, ProductStock>
     */
    public function getStockByProduct(int $productId): Collection;

    /**
     * Get all stock for warehouse.
     *
     * @return Collection<int, ProductStock>
     */
    public function getStockByWarehouse(int $warehouseId): Collection;

    /**
     * Get total available quantity for product.
     */
    public function getTotalAvailable(int $productId): float;

    /**
     * Check if sufficient stock available.
     */
    public function hasSufficientStock(int $productId, int $warehouseId, float $required): bool;

    /**
     * Get low stock products (below reorder level).
     *
     * @return Collection<int, ProductStock>
     */
    public function findLowStock(): Collection;

    /**
     * Reserve stock.
     */
    public function reserve(int $productId, int $warehouseId, float $quantity): bool;

    /**
     * Release reserved stock.
     */
    public function releaseReservation(int $productId, int $warehouseId, float $quantity): bool;
}
