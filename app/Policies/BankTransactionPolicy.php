<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Accounting\BankTransaction;
use App\Models\User;

class BankTransactionPolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'bank_transactions';
    }

    public function reconcile(User $user, BankTransaction $transaction): bool
    {
        return $this->check($user, 'reconcile');
    }

    public function match(User $user, BankTransaction $transaction): bool
    {
        return $this->check($user, 'reconcile');
    }
}
