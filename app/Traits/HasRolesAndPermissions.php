<?php

namespace App\Traits;

use App\Models\Core\Permission;
use App\Models\Core\Role;
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
     * Request-lifetime cache of this user's role and permission names.
     *
     * Authorization is checked many times per request (route middleware,
     * policies, resource visibility). Resolving it from the database each
     * time cost 2-3 queries per check; this collapses it to one.
     *
     * @var array{roles: list<string>, permissions: list<string>}|null
     */
    protected ?array $authorizationCache = null;

    /**
     * Request-level cache of the full permission catalogue, used for admins.
     *
     * @var \Illuminate\Support\Collection<int, Permission>|null
     */
    protected static ?\Illuminate\Support\Collection $allPermissionsCache = null;

    /**
     * Load this user's role and permission names in a single query.
     *
     * @return array{roles: list<string>, permissions: list<string>}
     */
    protected function authorizationSet(): array
    {
        if ($this->authorizationCache !== null) {
            return $this->authorizationCache;
        }

        $roles = $this->relationLoaded('roles') && $this->roles->every(fn ($role) => $role->relationLoaded('permissions'))
            ? $this->roles
            : $this->roles()->with('permissions:id,name')->get();

        return $this->authorizationCache = [
            'roles' => $roles->pluck('name')->all(),
            'permissions' => $roles
                ->pluck('permissions')
                ->flatten()
                ->pluck('name')
                ->unique()
                ->values()
                ->all(),
        ];
    }

    /**
     * Drop the memoized authorization set.
     *
     * Call after any role or permission change so later checks in the same
     * request see the new state.
     */
    public function flushAuthorizationCache(): static
    {
        $this->authorizationCache = null;
        static::$allPermissionsCache = null;
        $this->unsetRelation('roles');

        return $this;
    }

    /**
     * Scope query to users with a specific role.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<$this>  $query
     */
    public function scopeRole(\Illuminate\Database\Eloquent\Builder $query, string $roleName): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereHas('roles', function ($q) use ($roleName) {
            $q->where('name', $roleName);
        });
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $roleName): bool
    {
        return in_array($roleName, $this->authorizationSet()['roles'], true);
    }

    /**
     * Check if user has any of the given roles.
     *
     * @param  array<string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return array_intersect($roles, $this->authorizationSet()['roles']) !== [];
    }

    /**
     * Check if user has all of the given roles.
     *
     * @param  array<string>  $roles
     */
    public function hasAllRoles(array $roles): bool
    {
        $held = $this->authorizationSet()['roles'];

        foreach ($roles as $role) {
            if (! in_array($role, $held, true)) {
                return false;
            }
        }

        return true;
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
        $set = $this->authorizationSet();

        // Admin has all permissions
        if (in_array(Role::ADMIN, $set['roles'], true)) {
            return true;
        }

        return in_array($permission, $set['permissions'], true);
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
        // Admins hold no permission rows — they bypass checks in hasPermission() —
        // so the full catalogue stands in. Cached per request: serialising a list
        // of users would otherwise re-fetch it once per row.
        if ($this->isAdmin()) {
            return static::$allPermissionsCache ??= Permission::all();
        }

        // Reuse the eager-loaded relation when the caller provided it
        // (->with('roles.permissions')), which keeps list endpoints at O(1) queries.
        $roles = $this->relationLoaded('roles') && $this->roles->every(fn ($role) => $role->relationLoaded('permissions'))
            ? $this->roles
            : $this->roles()->with('permissions')->get();

        /** @var \Illuminate\Support\Collection<int, Permission> $permissions */
        $permissions = $roles
            ->pluck('permissions')
            ->flatten()
            ->unique('id')
            ->values();

        return $permissions;
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
        $this->flushAuthorizationCache();
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
            $this->flushAuthorizationCache();
        }
    }

    /**
     * Sync roles for user.
     *
     * @param  array<int>|array<string>  $roles
     */
    public function syncRoles(array $roles): void
    {
        $roleNames = [];
        $roleIds = [];

        foreach ($roles as $role) {
            if (is_numeric($role)) {
                $roleIds[] = (int) $role;
            } else {
                $roleNames[] = $role;
            }
        }

        $resolvedIds = Role::query()
            ->when(! empty($roleNames), fn ($q) => $q->whereIn('name', $roleNames))
            ->when(! empty($roleIds), fn ($q) => $q->orWhereIn('id', $roleIds))
            ->pluck('id');

        $this->roles()->sync($resolvedIds);
        $this->flushAuthorizationCache();
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
