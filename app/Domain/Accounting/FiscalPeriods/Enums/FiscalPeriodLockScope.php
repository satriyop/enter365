<?php

declare(strict_types=1);

namespace App\Domain\Accounting\FiscalPeriods\Enums;

enum FiscalPeriodLockScope: string
{
    case Sales = 'sales';
    case Purchases = 'purchases';
    case Tax = 'tax';
    case Everything = 'everything';
    case Hard = 'hard';
}
