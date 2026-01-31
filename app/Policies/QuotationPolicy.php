<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Sales\Quotation;
use App\Models\User;

class QuotationPolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'quotations';
    }

    public function approve(User $user, Quotation $quotation): bool
    {
        return $this->check($user, 'approve');
    }

    /**
     * Submit, reject, cancel, mark-as-sent, revise, convert, duplicate
     * all require edit permission.
     */
    public function manage(User $user, Quotation $quotation): bool
    {
        return $this->check($user, 'edit');
    }
}
