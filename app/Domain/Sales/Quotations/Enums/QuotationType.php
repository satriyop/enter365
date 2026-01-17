<?php

declare(strict_types=1);

namespace App\Domain\Sales\Quotations\Enums;

enum QuotationType: string
{
    case Single = 'single';
    case MultiOption = 'multi_option';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Single',
            self::MultiOption => 'Multi Option',
        };
    }
}
