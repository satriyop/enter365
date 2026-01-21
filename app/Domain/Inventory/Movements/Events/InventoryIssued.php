<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Movements\Events;

readonly class InventoryIssued
{
    public function __construct(
        public int $productId,
        public int $warehouseId,
        public float $quantity,
        public int $movementId,
        public ?int $userId = null
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'warehouse_id' => $this->warehouseId,
            'quantity' => $this->quantity,
            'movement_id' => $this->movementId,
            'user_id' => $this->userId,
        ];
    }
}
