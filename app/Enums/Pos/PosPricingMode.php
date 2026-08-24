<?php

declare(strict_types=1);

namespace App\Enums\Pos;

enum PosPricingMode: string
{
    case Inclusive = 'inclusive';
    case Add = 'add';

    public function isAdd(): bool
    {
        return $this === self::Add;
    }
}
