<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Movements\Events;

readonly class InventoryAdjusted
{
    public function __construct(
        public int $productId,
        public int $warehouseId,
        public float $adjustmentQuantity,
        public float $previousQuantity,
        public float $newQuantity,
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
            'adjustment_quantity' => $this->adjustmentQuantity,
            'previous_quantity' => $this->previousQuantity,
            'new_quantity' => $this->newQuantity,
            'movement_id' => $this->movementId,
            'user_id' => $this->userId,
        ];
    }
}
