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

    public function lock(User $user, FiscalPeriod $period): bool
    {
        return $this->check($user, 'lock');
    }

    public function unlock(User $user, FiscalPeriod $period): bool
    {
        return $this->check($user, 'unlock');
    }

    public function close(User $user, FiscalPeriod $period): bool
    {
        return $this->check($user, 'close');
    }

    public function reopen(User $user, FiscalPeriod $period): bool
    {
        return $this->check($user, 'reopen');
    }

    public function viewChecklist(User $user, FiscalPeriod $period): bool
    {
        return $this->check($user, 'close');
    }
}
