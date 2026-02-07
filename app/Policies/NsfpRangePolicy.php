<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tax\NsfpRange;
use App\Models\User;

class NsfpRangePolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'nsfp_ranges';
    }

    public function deactivate(User $user, NsfpRange $range): bool
    {
        return $this->check($user, 'edit');
    }
}
