<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Inventory\StockOpname;
use App\Models\User;

class StockOpnamePolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'stock_opnames';
    }

    public function approve(User $user, StockOpname $stockOpname): bool
    {
        return $this->check($user, 'approve');
    }
}
