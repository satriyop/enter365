<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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
     * Only admin can list all users.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeAdmin($request);

        $query = User::with('roles');

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by role
        if ($request->has('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->input('role'));
            });
        }

        // Search by name or email
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('name')->paginate($request->input('per_page', 15));

        return UserResource::collection($users);
    }

    /**
     * Store a newly created user.
     * Only admin can create users.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
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

        return response()->json([
            'message' => 'User berhasil dibuat.',
            'user' => new UserResource($user),
        ], 201);
    }

    /**
     * Display the specified user.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        // Users can only view themselves unless admin
        if (! $request->user()->isAdmin() && $request->user()->id !== $user->id) {
            abort(403, 'Anda tidak memiliki akses untuk melihat user ini.');
        }

        $user->load('roles');

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->only(['name', 'email']);

        // Only admin can update is_active
        if ($request->user()->isAdmin() && $request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        $user->update($data);

        // Only admin can update roles
        if ($request->user()->isAdmin() && $request->has('roles')) {
            $user->roles()->sync($request->input('roles'));
        }

        $user->load('roles');

        return response()->json([
            'message' => 'User berhasil diperbarui.',
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Remove the specified user.
     * Only admin can delete users.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);

        // Cannot delete yourself
        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'Anda tidak dapat menghapus akun Anda sendiri.',
            ], 422);
        }

        // Revoke all tokens
        $user->tokens()->delete();

        $user->delete();

        return response()->json([
            'message' => 'User berhasil dihapus.',
        ]);
    }

    /**
     * Update user password.
     */
    public function updatePassword(UpdatePasswordRequest $request, User $user): JsonResponse
    {
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

        return response()->json([
            'message' => 'Password berhasil diperbarui.',
        ]);
    }

    /**
     * Assign roles to user.
     * Only admin can assign roles.
     */
    public function assignRoles(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);

        $request->validate([
            'roles' => ['required', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ]);

        $user->roles()->sync($request->input('roles'));
        $user->load('roles');

        return response()->json([
            'message' => 'Role berhasil diperbarui.',
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Toggle user active status.
     * Only admin can toggle status.
     */
    public function toggleActive(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);

        // Cannot deactivate yourself
        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.',
            ], 422);
        }

        $user->update([
            'is_active' => ! $user->is_active,
        ]);

        // If deactivating, revoke all tokens
        if (! $user->is_active) {
            $user->tokens()->delete();
        }

        return response()->json([
            'message' => $user->is_active
                ? 'User berhasil diaktifkan.'
                : 'User berhasil dinonaktifkan.',
            'user' => new UserResource($user->load('roles')),
        ]);
    }

    /**
     * Check if user is admin.
     */
    private function authorizeAdmin(Request $request): void
    {
        if (! $request->user()->isAdmin()) {
            abort(403, 'Hanya administrator yang dapat mengakses resource ini.');
        }
    }
}
