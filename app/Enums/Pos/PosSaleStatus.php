<?php

declare(strict_types=1);

namespace App\Enums\Pos;

enum PosSaleStatus: string
{
    case Completed = 'completed';
    case Voided = 'voided';
}
