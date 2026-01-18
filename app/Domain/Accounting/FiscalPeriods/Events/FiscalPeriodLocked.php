<?php

declare(strict_types=1);

namespace App\Domain\Accounting\FiscalPeriods\Events;

/**
 * Dispatched when a fiscal period is locked for closing.
 *
 * When locked:
 * - New transactions cannot be created
 * - Existing drafts can still be posted
 * - Period is being prepared for closing review
 */
class FiscalPeriodLocked
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
            'locked_at' => now()->toIso8601String(),
        ];
    }
}
