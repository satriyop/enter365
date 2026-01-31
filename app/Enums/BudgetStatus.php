<?php

declare(strict_types=1);

namespace App\Enums;

enum BudgetStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Closed = 'closed';

    /**
     * Get human-readable label (Indonesian).
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Approved => 'Disetujui',
            self::Closed => 'Ditutup',
        };
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'zinc',
            self::Approved => 'green',
            self::Closed => 'gray',
        };
    }

    /**
     * Check if this is a terminal/final status.
     */
    public function isTerminal(): bool
    {
        return $this === self::Closed;
    }

    /**
     * Check if this is an editable status.
     */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}
