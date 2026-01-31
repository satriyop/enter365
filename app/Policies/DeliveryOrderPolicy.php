<?php

declare(strict_types=1);

namespace App\Policies;

class DeliveryOrderPolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'delivery_orders';
    }
}
