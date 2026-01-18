<?php

declare(strict_types=1);

namespace App\Domain\Accounting\FiscalPeriods\Events;

/**
 * Dispatched when a closed fiscal period is reopened.
 *
 * Reopening a period typically involves:
 * - Reversing the closing journal entry
 * - Resetting status flags
 * - This is an audit-significant event
 */
class FiscalPeriodReopened
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
            'reopened_at' => now()->toIso8601String(),
        ];
    }
}
