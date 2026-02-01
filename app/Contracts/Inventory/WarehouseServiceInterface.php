<?php

declare(strict_types=1);

namespace App\Contracts\Inventory;

use App\Models\Inventory\Warehouse;

/**
 * Interface for Warehouse management operations.
 */
interface WarehouseServiceInterface
{
    /**
     * Create a new warehouse.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Warehouse;

    /**
     * Update an existing warehouse.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Warehouse $warehouse, array $data): Warehouse;

    /**
     * Delete a warehouse.
     */
    public function delete(Warehouse $warehouse): void;

    /**
     * Set a warehouse as the default.
     */
    public function setAsDefault(Warehouse $warehouse): Warehouse;
}
