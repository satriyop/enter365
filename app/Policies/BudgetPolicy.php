<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Accounting\Budget;
use App\Models\User;

class BudgetPolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'budgets';
    }

    public function approve(User $user, Budget $budget): bool
    {
        return $this->check($user, 'approve');
    }
}
