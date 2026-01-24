<?php

namespace App\Models\Core;

use App\Models\User;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use Filterable, HasFactory;

    public const ADMIN = 'admin';

    public const ACCOUNTANT = 'accountant';

    public const CASHIER = 'cashier';

    public const SALES = 'sales';

    public const PURCHASING = 'purchasing';

    public const INVENTORY = 'inventory';

    public const VIEWER = 'viewer';

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)->withTimestamps();
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Check if role has a specific permission.
     */
    public function hasPermission(string $permissionName): bool
    {
        // Admin has all permissions
        if ($this->name === self::ADMIN) {
            return true;
        }

        return $this->permissions()->where('name', $permissionName)->exists();
    }

    /**
     * Check if role has any of the given permissions.
     *
     * @param  array<string>  $permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        if ($this->name === self::ADMIN) {
            return true;
        }

        return $this->permissions()->whereIn('name', $permissions)->exists();
    }

    /**
     * Check if role has all of the given permissions.
     *
     * @param  array<string>  $permissions
     */
    public function hasAllPermissions(array $permissions): bool
    {
        if ($this->name === self::ADMIN) {
            return true;
        }

        $count = $this->permissions()->whereIn('name', $permissions)->count();

        return $count === count($permissions);
    }

    /**
     * Assign permissions to role.
     *
     * @param  array<int>|array<string>  $permissions
     */
    public function givePermissions(array $permissions): void
    {
        $resolvedIds = $this->resolvePermissionIds($permissions);
        $this->permissions()->syncWithoutDetaching($resolvedIds);
    }

    /**
     * Remove permissions from role.
     *
     * @param  array<int>|array<string>  $permissions
     */
    public function revokePermissions(array $permissions): void
    {
        $resolvedIds = $this->resolvePermissionIds($permissions);
        $this->permissions()->detach($resolvedIds);
    }

    /**
     * Sync permissions for role.
     *
     * @param  array<int>|array<string>  $permissions
     */
    public function syncPermissions(array $permissions): void
    {
        $resolvedIds = $this->resolvePermissionIds($permissions);
        $this->permissions()->sync($resolvedIds);
    }

    /**
     * Resolve permission names/IDs to IDs.
     */
    private function resolvePermissionIds(array $permissions): \Illuminate\Support\Collection
    {
        $names = [];
        $ids = [];

        foreach ($permissions as $p) {
            if (is_numeric($p)) {
                $ids[] = (int) $p;
            } else {
                $names[] = $p;
            }
        }

        return Permission::query()
            ->when(! empty($names), fn ($q) => $q->whereIn('name', $names))
            ->when(! empty($ids), fn ($q) => $q->orWhereIn('id', $ids))
            ->pluck('id');
    }

    /**
     * Get role by name.
     */
    public static function findByName(string $name): ?self
    {
        return static::where('name', $name)->first();
    }

    /**
     * Scope for system roles.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Role>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Role>
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Scope for non-system roles.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Role>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Role>
     */
    public function scopeCustom($query)
    {
        return $query->where('is_system', false);
    }
}
