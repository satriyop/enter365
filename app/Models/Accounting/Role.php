<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

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
        $permissionIds = Permission::whereIn('name', $permissions)
            ->orWhereIn('id', $permissions)
            ->pluck('id');

        $this->permissions()->syncWithoutDetaching($permissionIds);
    }

    /**
     * Remove permissions from role.
     *
     * @param  array<int>|array<string>  $permissions
     */
    public function revokePermissions(array $permissions): void
    {
        $permissionIds = Permission::whereIn('name', $permissions)
            ->orWhereIn('id', $permissions)
            ->pluck('id');

        $this->permissions()->detach($permissionIds);
    }

    /**
     * Sync permissions for role.
     *
     * @param  array<int>|array<string>  $permissions
     */
    public function syncPermissions(array $permissions): void
    {
        $permissionIds = Permission::whereIn('name', $permissions)
            ->orWhereIn('id', $permissions)
            ->pluck('id');

        $this->permissions()->sync($permissionIds);
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
