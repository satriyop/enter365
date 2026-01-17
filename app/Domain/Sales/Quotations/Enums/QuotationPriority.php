<?php

declare(strict_types=1);

namespace App\Domain\Sales\Quotations\Enums;

enum QuotationPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Rendah',
            self::Normal => 'Normal',
            self::High => 'Tinggi',
            self::Urgent => 'Mendesak',
        };
    }

    public function isHigh(): bool
    {
        return in_array($this, [self::High, self::Urgent], true);
    }

    public const ALL = [
        self::Low->value,
        self::Normal->value,
        self::High->value,
        self::Urgent->value,
    ];
}
