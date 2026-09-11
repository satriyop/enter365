<?php

namespace App\Policies;

class PaymentTermPolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'journals';
    }
}
