<?php

declare(strict_types=1);

namespace App\Policies;

class WorkOrderPolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'work_orders';
    }
}
