<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;

/**
 * Exception when stock quantity is insufficient for an operation.
 *
 * Use when:
 * - Stock out operation exceeds available quantity
 * - Transfer exceeds source warehouse quantity
 * - Reservation cannot be fulfilled
 */
class InsufficientStockException extends DomainException
{
    protected string $errorCode = 'INSUFFICIENT_STOCK';

    protected int $statusCode = 422;

    /**
     * Create exception for insufficient product stock.
     */
    public static function forProduct(
        Product $product,
        float $requested,
        float $available,
        ?Warehouse $warehouse = null
    ): self {
        $location = $warehouse ? " di gudang {$warehouse->name}" : '';

        return new self(
            "Stok {$product->name} tidak mencukupi{$location}. Diminta: {$requested}, Tersedia: {$available}.",
            [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'requested' => $requested,
                'available' => $available,
                'shortage' => $requested - $available,
                'warehouse_id' => $warehouse?->id,
                'warehouse_name' => $warehouse?->name,
            ]
        );
    }

    /**
     * Create exception for transfer operation.
     */
    public static function forTransfer(
        Product $product,
        Warehouse $fromWarehouse,
        float $requested,
        float $available
    ): self {
        return new self(
            "Stok tidak mencukupi untuk transfer. {$product->name} di {$fromWarehouse->name}: Diminta {$requested}, Tersedia {$available}.",
            [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'from_warehouse_id' => $fromWarehouse->id,
                'from_warehouse_name' => $fromWarehouse->name,
                'requested' => $requested,
                'available' => $available,
            ]
        );
    }

    /**
     * Create generic insufficient stock exception.
     */
    public static function generic(string $message, float $requested, float $available): self
    {
        return new self($message, [
            'requested' => $requested,
            'available' => $available,
            'shortage' => $requested - $available,
        ]);
    }
}
