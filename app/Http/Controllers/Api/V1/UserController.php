<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Filters\UserFilter;
use App\Http\Requests\Api\V1\StoreUserRequest;
use App\Http\Requests\Api\V1\UpdatePasswordRequest;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Services\Shared\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function __construct(
        private UserService $userService
    ) {}

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

        $user = $this->userService->create($request->validated());

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

        $canManageRoles = $request->user()->can('manageRoles', User::class);
        $updatedUser = $this->userService->update($user, $request->validated(), $canManageRoles);

        return new UserResource($updatedUser);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        try {
            $this->userService->delete($user, $request->user()->id);

            return $this->deleted('User berhasil dihapus.');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Update user password.
     */
    public function updatePassword(UpdatePasswordRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $currentToken = $request->user()->currentAccessToken();
        $currentTokenId = ($currentToken && is_object($currentToken)) ? $currentToken->id : null;

        $this->userService->updatePassword(
            $user,
            $request->password,
            $request->user()->id,
            $currentTokenId
        );

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

        $updatedUser = $this->userService->assignRoles($user, $request->input('roles'));

        return $this->success(
            new UserResource($updatedUser),
            'Role berhasil diperbarui.'
        );
    }

    /**
     * Toggle user active status.
     */
    public function toggleActive(Request $request, User $user): JsonResponse
    {
        $this->authorize('manageRoles', User::class);

        try {
            $updatedUser = $this->userService->toggleActive($user, $request->user()->id);

            return $this->success(
                new UserResource($updatedUser),
                $updatedUser->is_active
                    ? 'User berhasil diaktifkan.'
                    : 'User berhasil dinonaktifkan.'
            );
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
