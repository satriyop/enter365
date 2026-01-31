<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Purchasing\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'purchase_orders';
    }

    public function approve(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->check($user, 'approve');
    }

    /**
     * Submit, reject, cancel, receive, convert, duplicate
     * all require edit permission.
     */
    public function manage(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->check($user, 'edit');
    }
}
