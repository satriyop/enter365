<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Convention-based policy that checks permissions by group name.
 *
 * Subclasses define a $permissionGroup (e.g., 'invoices') and this base class
 * maps policy methods to permission names:
 *   viewAny  → {group}.view
 *   view     → {group}.view
 *   create   → {group}.create
 *   update   → {group}.edit
 *   delete   → {group}.delete
 *
 * Custom permissions can be checked via the `check()` helper:
 *   $this->check($user, 'post')  → {group}.post
 *
 * Admin users bypass all checks via HasRolesAndPermissions::hasPermission().
 */
abstract class BaseResourcePolicy
{
    /**
     * The permission group name (e.g., 'invoices', 'contacts').
     * Must be defined by each subclass.
     */
    abstract protected function permissionGroup(): string;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission($this->permissionGroup().'.view');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->hasPermission($this->permissionGroup().'.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission($this->permissionGroup().'.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->hasPermission($this->permissionGroup().'.edit');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->hasPermission($this->permissionGroup().'.delete');
    }

    /**
     * Check a custom permission within the group.
     *
     * Usage: $this->check($user, 'post') → '{group}.post'
     */
    protected function check(User $user, string $action): bool
    {
        return $user->hasPermission($this->permissionGroup().'.'.$action);
    }
}
