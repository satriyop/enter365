<?php

declare(strict_types=1);

namespace App\Enums;

enum BankTransactionStatus: string
{
    case Unmatched = 'unmatched';
    case Matched = 'matched';
    case Reconciled = 'reconciled';

    /**
     * Get human-readable label (Indonesian).
     */
    public function label(): string
    {
        return match ($this) {
            self::Unmatched => 'Belum Di-match',
            self::Matched => 'Sudah Di-match',
            self::Reconciled => 'Sudah Rekonsiliasi',
        };
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Unmatched => 'zinc',
            self::Matched => 'yellow',
            self::Reconciled => 'green',
        };
    }

    /**
     * Check if this is a terminal/final status.
     */
    public function isTerminal(): bool
    {
        return $this === self::Reconciled;
    }

    /**
     * Check if this is an editable status.
     */
    public function isEditable(): bool
    {
        return $this === self::Unmatched;
    }
}
