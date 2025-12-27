<?php

namespace App\Traits;

use App\Models\Accounting\Permission;
use App\Models\Accounting\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasRolesAndPermissions
{
    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    /**
     * Check if user has any of the given roles.
     *
     * @param  array<string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()->whereIn('name', $roles)->exists();
    }

    /**
     * Check if user has all of the given roles.
     *
     * @param  array<string>  $roles
     */
    public function hasAllRoles(array $roles): bool
    {
        $count = $this->roles()->whereIn('name', $roles)->count();

        return $count === count($roles);
    }

    /**
     * Check if user is admin.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(Role::ADMIN);
    }

    /**
     * Check if user has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        // Admin has all permissions
        if ($this->isAdmin()) {
            return true;
        }

        // Check through roles
        foreach ($this->roles as $role) {
            if ($role->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has any of the given permissions.
     *
     * @param  array<string>  $permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has all of the given permissions.
     *
     * @param  array<string>  $permissions
     */
    public function hasAllPermissions(array $permissions): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        foreach ($permissions as $permission) {
            if (! $this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get all permissions for user through roles.
     *
     * @return \Illuminate\Support\Collection<int, Permission>
     */
    public function getAllPermissions(): \Illuminate\Support\Collection
    {
        if ($this->isAdmin()) {
            return Permission::all();
        }

        return $this->roles()
            ->with('permissions')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->unique('id');
    }

    /**
     * Assign role to user.
     */
    public function assignRole(string|Role $role): void
    {
        if (is_string($role)) {
            $role = Role::where('name', $role)->firstOrFail();
        }

        $this->roles()->syncWithoutDetaching($role->id);
    }

    /**
     * Remove role from user.
     */
    public function removeRole(string|Role $role): void
    {
        if (is_string($role)) {
            $role = Role::where('name', $role)->first();
        }

        if ($role) {
            $this->roles()->detach($role->id);
        }
    }

    /**
     * Sync roles for user.
     *
     * @param  array<int>|array<string>  $roles
     */
    public function syncRoles(array $roles): void
    {
        $roleIds = Role::whereIn('name', $roles)
            ->orWhereIn('id', $roles)
            ->pluck('id');

        $this->roles()->sync($roleIds);
    }

    /**
     * Check if user can perform action based on permission.
     */
    public function can($ability, $arguments = []): bool
    {
        // First check Laravel's gate
        if (parent::can($ability, $arguments)) {
            return true;
        }

        // Then check our permission system
        return $this->hasPermission($ability);
    }
}
