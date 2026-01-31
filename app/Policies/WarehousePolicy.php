<?php

declare(strict_types=1);

namespace App\Policies;

class WarehousePolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'warehouses';
    }
}
