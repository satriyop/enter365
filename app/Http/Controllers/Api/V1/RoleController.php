<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Filters\RoleFilter;
use App\Http\Requests\Api\V1\StoreRoleRequest;
use App\Http\Requests\Api\V1\UpdateRoleRequest;
use App\Http\Resources\Api\V1\RoleResource;
use App\Models\Core\Role;
use App\Services\Shared\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoleController extends Controller
{
    public function __construct(
        private RoleService $roleService
    ) {}

    /**
     * List all roles.
     */
    public function index(RoleFilter $filter): AnonymousResourceCollection
    {
        $roles = Role::query()
            ->withCount(['permissions', 'users'])
            ->filter($filter)
            ->orderBy('name')
            ->paginate($filter->getRequest()->input('per_page', 25));

        return RoleResource::collection($roles);
    }

    /**
     * Create a new role.
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = $this->roleService->create($request->validated());

        return $this->created(
            new RoleResource($role),
            'Role berhasil dibuat.'
        );
    }

    /**
     * Show a role.
     */
    public function show(Role $role, RoleFilter $filter): RoleResource
    {
        $filter->apply($role->newQuery());

        $role->loadMissing(['permissions']);
        $role->loadCount('users');

        return new RoleResource($role);
    }

    /**
     * Update a role.
     */
    public function update(UpdateRoleRequest $request, Role $role): RoleResource|JsonResponse
    {
        try {
            $updatedRole = $this->roleService->update($role, $request->validated());

            return new RoleResource($updatedRole);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Delete a role.
     */
    public function destroy(Role $role): JsonResponse
    {
        try {
            $this->roleService->delete($role);

            return $this->deleted('Role berhasil dihapus.');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Sync permissions for a role.
     *
     * @bodyParam permissions array required The list of permission IDs. Example: [1, 2, 3]
     * @bodyParam permissions.* integer required Permission ID.
     */
    public function syncPermissions(Request $request, Role $role): JsonResponse
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $updatedRole = $this->roleService->syncPermissions($role, $request->input('permissions'));

        return $this->success(
            new RoleResource($updatedRole),
            'Permission berhasil diperbarui.'
        );
    }

    /**
     * Get users with this role.
     *
     * @response array{data: array{role: array{id: int, name: string, display_name: string}, users: array<array{id: int, name: string, email: string}>}}
     */
    public function users(Role $role): JsonResponse
    {
        $users = $role->users()->select(['id', 'name', 'email'])->get();

        return $this->success([
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
            ],
            'users' => $users,
        ]);
    }
}
