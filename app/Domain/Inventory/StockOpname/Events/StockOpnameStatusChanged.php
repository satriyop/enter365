<?php

declare(strict_types=1);

namespace App\Domain\Inventory\StockOpname\Events;

readonly class StockOpnameStatusChanged
{
    public function __construct(
        public int $stockOpnameId,
        public string $opnameNumber,
        public string $fromStatus,
        public string $toStatus,
        public ?int $userId = null
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'stock_opname_id' => $this->stockOpnameId,
            'opname_number' => $this->opnameNumber,
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
            'user_id' => $this->userId,
        ];
    }
}
