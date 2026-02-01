<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Contracts\Inventory\WarehouseServiceInterface;
use App\Models\Inventory\Warehouse;

class WarehouseService implements WarehouseServiceInterface
{
    /**
     * Create a new warehouse.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Warehouse
    {
        // Generate code if not provided
        if (empty($data['code'])) {
            $data['code'] = Warehouse::generateCode();
        }

        // Set defaults
        $data['is_active'] = $data['is_active'] ?? true;
        $data['is_default'] = $data['is_default'] ?? false;

        // If this is the first warehouse, make it default
        if (! Warehouse::exists()) {
            $data['is_default'] = true;
        }

        // If marked as default, unset other defaults
        if ($data['is_default']) {
            Warehouse::where('is_default', true)->update(['is_default' => false]);
        }

        return Warehouse::create($data);
    }

    /**
     * Update a warehouse.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Warehouse $warehouse, array $data): Warehouse
    {
        // If marked as default, unset other defaults
        if (isset($data['is_default']) && $data['is_default']) {
            Warehouse::where('is_default', true)
                ->where('id', '!=', $warehouse->id)
                ->update(['is_default' => false]);
        }

        $warehouse->update($data);

        return $warehouse->fresh();
    }

    /**
     * Delete a warehouse.
     *
     * Validates that the warehouse has no stock and is not the default warehouse.
     *
     * @throws \App\Exceptions\Domain\ValidationException
     */
    public function delete(Warehouse $warehouse): void
    {
        // Check for stock
        if ($warehouse->productStocks()->where('quantity', '>', 0)->exists()) {
            throw \App\Exceptions\Domain\ValidationException::invalid(
                'warehouse',
                'Gudang tidak bisa dihapus karena masih memiliki stok.'
            );
        }

        // Check if default
        if ($warehouse->is_default) {
            throw \App\Exceptions\Domain\ValidationException::invalid(
                'warehouse',
                'Gudang default tidak bisa dihapus. Tetapkan gudang lain sebagai default terlebih dahulu.'
            );
        }

        // Delete related stock records (with zero quantity)
        $warehouse->productStocks()->delete();
        $warehouse->delete();
    }

    /**
     * Set warehouse as default.
     *
     * @throws \App\Exceptions\Domain\ValidationException
     */
    public function setAsDefault(Warehouse $warehouse): Warehouse
    {
        if (! $warehouse->is_active) {
            throw \App\Exceptions\Domain\ValidationException::invalid(
                'warehouse',
                'Gudang tidak aktif tidak bisa dijadikan default.'
            );
        }

        $warehouse->setAsDefault();

        return $warehouse->fresh();
    }
}
