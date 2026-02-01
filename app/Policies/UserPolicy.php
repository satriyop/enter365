<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserPolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'users';
    }

    /**
     * Users can view themselves; admins/users.view can see anyone.
     */
    public function view(User $user, Model $model): bool
    {
        if ($user->getKey() === $model->getKey()) {
            return true;
        }

        return parent::view($user, $model);
    }

    /**
     * Users can update themselves; admins/users.edit can update anyone.
     */
    public function update(User $user, Model $model): bool
    {
        if ($user->getKey() === $model->getKey()) {
            return true;
        }

        return parent::update($user, $model);
    }

    public function manageRoles(User $user): bool
    {
        return $this->check($user, 'manage_roles');
    }
}
