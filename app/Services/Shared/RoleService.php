<?php

declare(strict_types=1);

namespace App\Services\Shared;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Models\Core\Role;
use App\Services\Base\BaseService;

class RoleService extends BaseService
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Create a new role.
     *
     * @param  array{name: string, display_name?: string, description?: string, permissions?: array<int>}  $data
     */
    public function create(array $data): Role
    {
        return $this->executeInTransaction('create_role', function () use ($data) {
            $permissions = $data['permissions'] ?? [];
            unset($data['permissions']);

            $role = Role::create($data);

            if (! empty($permissions)) {
                $role->permissions()->sync($permissions);
            }

            return $role->fresh()->load('permissions');
        }, ['name' => $data['name']]);
    }

    /**
     * Update a role.
     *
     * @param  array{name?: string, display_name?: string, description?: string, permissions?: array<int>}  $data
     */
    public function update(Role $role, array $data): Role
    {
        return $this->executeInTransaction('update_role', function () use ($role, $data) {
            if ($role->is_system && isset($data['name']) && $data['name'] !== $role->name) {
                throw new \Exception('Nama role sistem tidak bisa diubah.');
            }

            $permissions = $data['permissions'] ?? null;
            unset($data['permissions']);

            if (! empty($data)) {
                $role->update($data);
            }

            if ($permissions !== null) {
                $role->permissions()->sync($permissions);
            }

            return $role->fresh('permissions');
        }, ['role_id' => $role->id]);
    }

    /**
     * Delete a role.
     */
    public function delete(Role $role): void
    {
        $this->executeInTransaction('delete_role', function () use ($role) {
            if ($role->is_system) {
                throw new \Exception('Role sistem tidak bisa dihapus.');
            }

            if ($role->users()->count() > 0) {
                throw new \Exception('Role tidak bisa dihapus karena masih memiliki pengguna.');
            }

            $role->permissions()->detach();
            $role->delete();
        }, ['role_id' => $role->id]);
    }

    /**
     * Sync permissions for a role.
     *
     * @param  array<int>  $permissionIds
     */
    public function syncPermissions(Role $role, array $permissionIds): Role
    {
        return $this->executeInTransaction('sync_permissions', function () use ($role, $permissionIds) {
            $role->permissions()->sync($permissionIds);

            return $role->fresh('permissions');
        }, ['role_id' => $role->id]);
    }
}
