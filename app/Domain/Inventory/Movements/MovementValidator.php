<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Movements;

use App\Exceptions\Domain\BusinessRuleException;
use App\Exceptions\Domain\EntityNotFoundException;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;

/**
 * Validates inventory movements before execution.
 */
class MovementValidator
{
    /**
     * Validate receipt of inventory.
     *
     * @throws EntityNotFoundException|BusinessRuleException
     */
    public function validateReceipt(int $productId, int $warehouseId, float $quantity): void
    {
        $this->validateProduct($productId);
        $this->validateWarehouse($warehouseId);

        if ($quantity <= 0) {
            throw BusinessRuleException::operationNotAllowed(
                'receipt',
                'Jumlah harus lebih dari 0.'
            );
        }
    }

    /**
     * Validate issue (removal) of inventory.
     *
     * @throws EntityNotFoundException|BusinessRuleException
     */
    public function validateIssue(int $productId, int $warehouseId, float $quantity): void
    {
        $product = $this->validateProduct($productId);
        $warehouse = $this->validateWarehouse($warehouseId);

        if ($quantity <= 0) {
            throw BusinessRuleException::operationNotAllowed(
                'issue',
                'Jumlah harus lebih dari 0.'
            );
        }

        // Check available stock
        $stock = ProductStock::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        if (! $stock) {
            throw BusinessRuleException::insufficientStock(
                $product->name,
                $quantity,
                0
            );
        }

        $available = $stock->getAvailableQuantity();
        if ($quantity > $available) {
            throw BusinessRuleException::insufficientStock(
                $product->name,
                $quantity,
                $available
            );
        }
    }

    /**
     * Validate inventory adjustment.
     *
     * @throws EntityNotFoundException|BusinessRuleException
     */
    public function validateAdjustment(int $productId, int $warehouseId, float $adjustmentQuantity): void
    {
        $product = $this->validateProduct($productId);
        $this->validateWarehouse($warehouseId);

        if ($adjustmentQuantity == 0) {
            throw BusinessRuleException::operationNotAllowed(
                'adjustment',
                'Jumlah adjustment tidak boleh 0.'
            );
        }

        // If negative adjustment, check stock availability
        if ($adjustmentQuantity < 0) {
            $stock = ProductStock::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->first();

            $currentQty = $stock?->quantity ?? 0;
            $resultQty = $currentQty + $adjustmentQuantity;

            if ($resultQty < 0) {
                throw BusinessRuleException::insufficientStock(
                    $product->name,
                    abs($adjustmentQuantity),
                    $currentQty
                );
            }
        }
    }

    /**
     * Validate inventory transfer between warehouses.
     *
     * @throws EntityNotFoundException|BusinessRuleException
     */
    public function validateTransfer(
        int $productId,
        int $fromWarehouseId,
        int $toWarehouseId,
        float $quantity
    ): void {
        $product = $this->validateProduct($productId);
        $this->validateWarehouse($fromWarehouseId);
        $this->validateWarehouse($toWarehouseId);

        if ($fromWarehouseId === $toWarehouseId) {
            throw BusinessRuleException::operationNotAllowed(
                'transfer',
                'Gudang asal dan tujuan tidak boleh sama.'
            );
        }

        if ($quantity <= 0) {
            throw BusinessRuleException::operationNotAllowed(
                'transfer',
                'Jumlah harus lebih dari 0.'
            );
        }

        // Check source warehouse stock
        $sourceStock = ProductStock::where('product_id', $productId)
            ->where('warehouse_id', $fromWarehouseId)
            ->first();

        if (! $sourceStock) {
            throw BusinessRuleException::insufficientStock(
                $product->name,
                $quantity,
                0
            );
        }

        $available = $sourceStock->getAvailableQuantity();
        if ($quantity > $available) {
            throw BusinessRuleException::insufficientStock(
                $product->name,
                $quantity,
                $available
            );
        }
    }

    /**
     * Validate product exists and can be used in inventory.
     *
     * @throws EntityNotFoundException|BusinessRuleException
     */
    private function validateProduct(int $productId): Product
    {
        $product = Product::find($productId);

        if (! $product) {
            throw new EntityNotFoundException('Produk', $productId);
        }

        if ($product->type === 'service') {
            throw BusinessRuleException::operationNotAllowed(
                'inventory movement',
                'Produk jasa tidak memiliki inventori.'
            );
        }

        return $product;
    }

    /**
     * Validate warehouse exists and is active.
     *
     * @throws EntityNotFoundException|BusinessRuleException
     */
    private function validateWarehouse(int $warehouseId): Warehouse
    {
        $warehouse = Warehouse::find($warehouseId);

        if (! $warehouse) {
            throw new EntityNotFoundException('Gudang', $warehouseId);
        }

        if (! $warehouse->is_active) {
            throw BusinessRuleException::operationNotAllowed(
                'inventory movement',
                "Gudang {$warehouse->name} tidak aktif."
            );
        }

        return $warehouse;
    }
}
