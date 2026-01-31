<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Shared\Payment;
use App\Models\User;

class PaymentPolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'payments';
    }

    public function void(User $user, Payment $payment): bool
    {
        return $this->check($user, 'void');
    }
}
