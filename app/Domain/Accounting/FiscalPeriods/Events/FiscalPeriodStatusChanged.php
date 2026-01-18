<?php

declare(strict_types=1);

namespace App\Domain\Accounting\FiscalPeriods\Events;

use App\Domain\Accounting\FiscalPeriods\Enums\FiscalPeriodStatus;

/**
 * Dispatched when a fiscal period status changes.
 *
 * This is the generic status change event; specific events like
 * FiscalPeriodLocked and FiscalPeriodClosed are also dispatched.
 */
class FiscalPeriodStatusChanged
{
    public function __construct(
        public readonly int $periodId,
        public readonly FiscalPeriodStatus $fromStatus,
        public readonly FiscalPeriodStatus $toStatus,
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
            'from_status' => $this->fromStatus->value,
            'to_status' => $this->toStatus->value,
            'user_id' => $this->userId,
            'changed_at' => now()->toIso8601String(),
        ];
    }
}
