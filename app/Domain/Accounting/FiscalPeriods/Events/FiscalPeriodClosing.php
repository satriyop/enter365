<?php

declare(strict_types=1);

namespace App\Domain\Accounting\FiscalPeriods\Events;

/**
 * Dispatched when the fiscal period closing process begins.
 *
 * This event fires at the start of the multi-step closing process,
 * before any closing journal entries are created.
 */
class FiscalPeriodClosing
{
    public function __construct(
        public readonly int $periodId,
        public readonly ?int $userId
    ) {}

    /**
     * Convert to array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'period_id' => $this->periodId,
            'user_id' => $this->userId,
            'started_at' => now()->toIso8601String(),
        ];
    }
}
