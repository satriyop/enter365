<?php

namespace App\Policies;

class PaymentProviderPolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'journals';
    }
}
