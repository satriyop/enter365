<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Accounting\JournalEntry;
use App\Models\User;

class JournalEntryPolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'journals';
    }

    public function post(User $user, JournalEntry $journalEntry): bool
    {
        return $this->check($user, 'post');
    }

    public function reverse(User $user, JournalEntry $journalEntry): bool
    {
        return $this->check($user, 'reverse');
    }
}
