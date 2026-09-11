<?php

namespace App\Policies;

class PaymentMethodPolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'journals';
    }
}
