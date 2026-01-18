<?php

declare(strict_types=1);

namespace App\Domain\Accounting\FiscalPeriods\Enums;

/**
 * Status enum for fiscal period lifecycle.
 *
 * Represents the states a fiscal period can be in:
 * - Open: Normal operations, transactions allowed
 * - Locked: Soft lock for review, no new transactions
 * - Closing: Closing process in progress
 * - Closed: Period finalized, no modifications allowed
 */
enum FiscalPeriodStatus: string
{
    case Open = 'open';
    case Locked = 'locked';
    case Closing = 'closing';
    case Closed = 'closed';

    /**
     * Get human-readable label (Indonesian).
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Terbuka',
            self::Locked => 'Dikunci',
            self::Closing => 'Proses Penutupan',
            self::Closed => 'Ditutup',
        };
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Open => 'green',
            self::Locked => 'yellow',
            self::Closing => 'orange',
            self::Closed => 'zinc',
        };
    }

    /**
     * Check if transactions can be created in this status.
     */
    public function allowsTransactions(): bool
    {
        return $this === self::Open;
    }

    /**
     * Check if journal entries can be posted in this status.
     */
    public function allowsPosting(): bool
    {
        return in_array($this, [self::Open, self::Locked], true);
    }

    /**
     * Check if the period can be modified.
     */
    public function allowsModifications(): bool
    {
        return $this === self::Open;
    }

    /**
     * Check if this is a terminal state.
     */
    public function isTerminal(): bool
    {
        return $this === self::Closed;
    }

    /**
     * Get all statuses as options for forms.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn (self $status) => $status->label(), self::cases())
        );
    }
}
