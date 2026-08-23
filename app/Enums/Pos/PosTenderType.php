<?php

declare(strict_types=1);

namespace App\Enums\Pos;

enum PosTenderType: string
{
    case Cash = 'cash';
    case Qris = 'qris';
}
