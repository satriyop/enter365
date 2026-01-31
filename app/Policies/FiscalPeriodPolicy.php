<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Accounting\FiscalPeriod;
use App\Models\User;

class FiscalPeriodPolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'fiscal_periods';
    }

    public function close(User $user, FiscalPeriod $period): bool
    {
        return $this->check($user, 'close');
    }

    public function reopen(User $user, FiscalPeriod $period): bool
    {
        return $this->check($user, 'reopen');
    }
}
