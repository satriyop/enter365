<?php

declare(strict_types=1);

namespace App\Domain\Tax\Events;

class NsfpLowStock
{
    public function __construct(
        public readonly int $rangeId,
        public readonly int $remaining,
        public readonly int $threshold
    ) {}

    public function toArray(): array
    {
        return [
            'range_id' => $this->rangeId,
            'remaining' => $this->remaining,
            'threshold' => $this->threshold,
        ];
    }
}
