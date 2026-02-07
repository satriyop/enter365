<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Sales\DownPayment;
use App\Models\User;

class DownPaymentPolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'down_payments';
    }

    public function apply(User $user, DownPayment $downPayment): bool
    {
        return $this->check($user, 'apply');
    }

    public function refund(User $user, DownPayment $downPayment): bool
    {
        return $this->check($user, 'refund');
    }

    public function cancel(User $user, DownPayment $downPayment): bool
    {
        return $this->check($user, 'cancel');
    }
}
