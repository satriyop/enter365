<?php

declare(strict_types=1);

namespace App\Enums\Pos;

enum PosSessionStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}
