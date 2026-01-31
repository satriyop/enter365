<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Sales\SalesReturn;
use App\Models\User;

class SalesReturnPolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'sales_returns';
    }

    public function approve(User $user, SalesReturn $salesReturn): bool
    {
        return $this->check($user, 'approve');
    }
}
