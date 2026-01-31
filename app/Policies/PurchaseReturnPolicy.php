<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Purchasing\PurchaseReturn;
use App\Models\User;

class PurchaseReturnPolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'purchase_returns';
    }

    public function approve(User $user, PurchaseReturn $purchaseReturn): bool
    {
        return $this->check($user, 'approve');
    }
}
