<?php

declare(strict_types=1);

namespace App\Domain\Accounting\FiscalPeriods\Events;

/**
 * Dispatched when a fiscal period is successfully closed.
 *
 * Contains the results of the closing process:
 * - Net income transferred to retained earnings
 * - ID of the closing journal entry
 */
class FiscalPeriodClosed
{
    public function __construct(
        public readonly int $periodId,
        public readonly int $netIncome,
        public readonly ?int $closingEntryId,
        public readonly ?int $userId
    ) {}

    /**
     * Check if the period had a profit.
     */
    public function hadProfit(): bool
    {
        return $this->netIncome > 0;
    }

    /**
     * Check if the period had a loss.
     */
    public function hadLoss(): bool
    {
        return $this->netIncome < 0;
    }

    /**
     * Convert to array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'period_id' => $this->periodId,
            'net_income' => $this->netIncome,
            'closing_entry_id' => $this->closingEntryId,
            'user_id' => $this->userId,
            'closed_at' => now()->toIso8601String(),
        ];
    }
}
