<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Filters\UserFilter;
use App\Http\Requests\Api\V1\StoreUserRequest;
use App\Http\Requests\Api\V1\UpdatePasswordRequest;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(UserFilter $filter): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with(['roles'])
            ->filter($filter)
            ->orderBy('name')
            ->paginate($filter->getRequest()->input('per_page', 15));

        return UserResource::collection($users);
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_active' => $request->boolean('is_active', true),
            'email_verified_at' => now(),
        ]);

        if ($request->has('roles')) {
            $user->roles()->attach($request->input('roles'));
        }

        $user->load('roles');

        return $this->created(new UserResource($user), 'User berhasil dibuat.');
    }

    /**
     * Display the specified user.
     */
    public function show(User $user, UserFilter $filter): UserResource
    {
        $this->authorize('view', $user);

        $filter->apply($user->newQuery());

        $user->loadMissing(['roles']);

        return new UserResource($user);
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $this->authorize('update', $user);

        $data = $request->only(['name', 'email']);

        // Only users with manageRoles permission can update is_active and roles
        if ($request->user()->can('manageRoles', User::class)) {
            if ($request->has('is_active')) {
                $data['is_active'] = $request->boolean('is_active');
            }
        }

        $user->update($data);

        if ($request->user()->can('manageRoles', User::class) && $request->has('roles')) {
            $user->roles()->sync($request->input('roles'));
        }

        $user->load('roles');

        return new UserResource($user);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        // Cannot delete yourself
        if ($request->user()->id === $user->id) {
            return $this->error('Anda tidak dapat menghapus akun Anda sendiri.', 422);
        }

        // Revoke all tokens
        $user->tokens()->delete();

        $user->delete();

        return $this->deleted('User berhasil dihapus.');
    }

    /**
     * Update user password.
     */
    public function updatePassword(UpdatePasswordRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Optionally revoke all tokens except current if user is changing own password
        if ($request->user()->id === $user->id) {
            $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();
        } else {
            // Admin changing other user's password - revoke all their tokens
            $user->tokens()->delete();
        }

        return $this->success(message: 'Password berhasil diperbarui.');
    }

    /**
     * Assign roles to user.
     */
    public function assignRoles(Request $request, User $user): JsonResponse
    {
        $this->authorize('manageRoles', User::class);

        $request->validate([
            'roles' => ['required', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ]);

        $user->roles()->sync($request->input('roles'));
        $user->load('roles');

        return $this->success(
            new UserResource($user),
            'Role berhasil diperbarui.'
        );
    }

    /**
     * Toggle user active status.
     */
    public function toggleActive(Request $request, User $user): JsonResponse
    {
        $this->authorize('manageRoles', User::class);

        // Cannot deactivate yourself
        if ($request->user()->id === $user->id) {
            return $this->error('Anda tidak dapat menonaktifkan akun Anda sendiri.', 422);
        }

        $user->update([
            'is_active' => ! $user->is_active,
        ]);

        // If deactivating, revoke all tokens
        if (! $user->is_active) {
            $user->tokens()->delete();
        }

        return $this->success(
            new UserResource($user->load('roles')),
            $user->is_active
                ? 'User berhasil diaktifkan.'
                : 'User berhasil dinonaktifkan.'
        );
    }
}
