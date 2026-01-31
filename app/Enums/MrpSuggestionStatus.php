<?php

declare(strict_types=1);

namespace App\Enums;

enum MrpSuggestionStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Converted = 'converted';

    /**
     * Get human-readable label (Indonesian).
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Accepted => 'Diterima',
            self::Rejected => 'Ditolak',
            self::Converted => 'Dikonversi',
        };
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::Accepted => 'green',
            self::Rejected => 'red',
            self::Converted => 'blue',
        };
    }

    /**
     * Check if this is a terminal/final status.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Rejected, self::Converted], true);
    }

    /**
     * Check if this is an editable status.
     */
    public function isEditable(): bool
    {
        return $this === self::Pending;
    }
}
