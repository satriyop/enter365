<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreRoleRequest;
use App\Http\Requests\Api\V1\UpdateRoleRequest;
use App\Http\Resources\Api\V1\RoleResource;
use App\Models\Accounting\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoleController extends Controller
{
    /**
     * List all roles.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Role::query()->withCount(['permissions', 'users']);

        // Filter by system roles
        if ($request->has('is_system')) {
            $query->where('is_system', $request->boolean('is_system'));
        }

        // Search
        if ($request->has('search')) {
            $search = strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(display_name) LIKE ?', ["%{$search}%"]);
            });
        }

        $roles = $query->orderBy('name')
            ->paginate($request->input('per_page', 25));

        return RoleResource::collection($roles);
    }

    /**
     * Create a new role.
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $permissions = $data['permissions'] ?? [];
        unset($data['permissions']);

        $role = Role::create($data);

        if (! empty($permissions)) {
            $role->permissions()->sync($permissions);
        }

        return (new RoleResource($role->fresh()->load('permissions')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a role.
     */
    public function show(Role $role): RoleResource
    {
        return new RoleResource(
            $role->load('permissions')->loadCount('users')
        );
    }

    /**
     * Update a role.
     */
    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        if ($role->is_system && $request->has('name') && $request->input('name') !== $role->name) {
            return response()->json([
                'message' => 'Nama role sistem tidak bisa diubah.',
            ], 422);
        }

        $data = $request->validated();
        $permissions = $data['permissions'] ?? null;
        unset($data['permissions']);

        $role->update($data);

        if ($permissions !== null) {
            $role->permissions()->sync($permissions);
        }

        return response()->json([
            'message' => 'Role berhasil diperbarui.',
            'data' => new RoleResource($role->fresh('permissions')),
        ]);
    }

    /**
     * Delete a role.
     */
    public function destroy(Role $role): JsonResponse
    {
        if ($role->is_system) {
            return response()->json([
                'message' => 'Role sistem tidak bisa dihapus.',
            ], 422);
        }

        if ($role->users()->count() > 0) {
            return response()->json([
                'message' => 'Role tidak bisa dihapus karena masih memiliki pengguna.',
            ], 422);
        }

        $role->permissions()->detach();
        $role->delete();

        return response()->json([
            'message' => 'Role berhasil dihapus.',
        ]);
    }

    /**
     * Sync permissions for a role.
     */
    public function syncPermissions(Request $request, Role $role): JsonResponse
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->permissions()->sync($request->input('permissions'));

        return response()->json([
            'message' => 'Permission berhasil diperbarui.',
            'data' => new RoleResource($role->fresh('permissions')),
        ]);
    }

    /**
     * Get users with this role.
     */
    public function users(Role $role): JsonResponse
    {
        $users = $role->users()->select(['id', 'name', 'email'])->get();

        return response()->json([
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
            ],
            'users' => $users,
        ]);
    }
}
