<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Movements\Events;

readonly class InventoryTransferred
{
    public function __construct(
        public int $productId,
        public int $fromWarehouseId,
        public int $toWarehouseId,
        public float $quantity,
        public int $outMovementId,
        public int $inMovementId,
        public ?int $userId = null
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'from_warehouse_id' => $this->fromWarehouseId,
            'to_warehouse_id' => $this->toWarehouseId,
            'quantity' => $this->quantity,
            'out_movement_id' => $this->outMovementId,
            'in_movement_id' => $this->inMovementId,
            'user_id' => $this->userId,
        ];
    }
}
