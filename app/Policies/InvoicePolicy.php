<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Sales\Invoice;
use App\Models\User;

class InvoicePolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'invoices';
    }

    public function post(User $user, Invoice $invoice): bool
    {
        return $this->check($user, 'post');
    }

    public function void(User $user, Invoice $invoice): bool
    {
        return $this->check($user, 'void');
    }
}
