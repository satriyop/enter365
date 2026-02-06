<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Purchasing\Bill;
use App\Models\User;

class BillPolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'bills';
    }

    public function post(User $user, Bill $bill): bool
    {
        return $this->check($user, 'post');
    }

    public function void(User $user, Bill $bill): bool
    {
        return $this->check($user, 'void');
    }
}
