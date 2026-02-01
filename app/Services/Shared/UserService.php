<?php

declare(strict_types=1);

namespace App\Services\Shared;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Models\User;
use App\Services\Base\BaseService;
use Illuminate\Support\Facades\Hash;

class UserService extends BaseService
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Create a new user.
     *
     * @param  array{name: string, email: string, password: string, is_active?: bool, roles?: array<int>}  $data
     */
    public function create(array $data): User
    {
        return $this->executeInTransaction('create_user', function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'is_active' => $data['is_active'] ?? true,
                'email_verified_at' => now(),
            ]);

            if (isset($data['roles']) && ! empty($data['roles'])) {
                $user->roles()->attach($data['roles']);
            }

            $user->load('roles');

            return $user;
        }, ['email' => $data['email']]);
    }

    /**
     * Update a user.
     *
     * @param  array{name?: string, email?: string, is_active?: bool, roles?: array<int>}  $data
     */
    public function update(User $user, array $data, bool $canManageRoles = false): User
    {
        return $this->executeInTransaction('update_user', function () use ($user, $data, $canManageRoles) {
            $updateData = [];

            if (isset($data['name'])) {
                $updateData['name'] = $data['name'];
            }

            if (isset($data['email'])) {
                $updateData['email'] = $data['email'];
            }

            // Only users with manageRoles permission can update is_active
            if ($canManageRoles && isset($data['is_active'])) {
                $updateData['is_active'] = $data['is_active'];
            }

            if (! empty($updateData)) {
                $user->update($updateData);
            }

            // Only users with manageRoles permission can update roles
            if ($canManageRoles && isset($data['roles'])) {
                $user->roles()->sync($data['roles']);
            }

            $user->load('roles');

            return $user;
        }, ['user_id' => $user->id]);
    }

    /**
     * Delete a user.
     */
    public function delete(User $user, int $currentUserId): void
    {
        $this->executeInTransaction('delete_user', function () use ($user, $currentUserId) {
            if ($user->id === $currentUserId) {
                throw new \Exception('Anda tidak dapat menghapus akun Anda sendiri.');
            }

            // Revoke all tokens
            $user->tokens()->delete();

            $user->delete();
        }, ['user_id' => $user->id]);
    }

    /**
     * Update user password.
     */
    public function updatePassword(User $user, string $newPassword, int $currentUserId, int|false|null $currentTokenId = null): void
    {
        $this->executeInTransaction('update_password', function () use ($user, $newPassword, $currentUserId, $currentTokenId) {
            $user->update([
                'password' => Hash::make($newPassword),
            ]);

            // Optionally revoke all tokens except current if user is changing own password
            if ($user->id === $currentUserId && $currentTokenId) {
                $user->tokens()->where('id', '!=', $currentTokenId)->delete();
            } else {
                // Admin changing other user's password - revoke all their tokens
                $user->tokens()->delete();
            }
        }, ['user_id' => $user->id]);
    }

    /**
     * Assign roles to user.
     *
     * @param  array<int>  $roleIds
     */
    public function assignRoles(User $user, array $roleIds): User
    {
        return $this->executeInTransaction('assign_roles', function () use ($user, $roleIds) {
            $user->roles()->sync($roleIds);
            $user->load('roles');

            return $user;
        }, ['user_id' => $user->id]);
    }

    /**
     * Toggle user active status.
     */
    public function toggleActive(User $user, int $currentUserId): User
    {
        return $this->executeInTransaction('toggle_active', function () use ($user, $currentUserId) {
            if ($user->id === $currentUserId) {
                throw new \Exception('Anda tidak dapat menonaktifkan akun Anda sendiri.');
            }

            $user->update([
                'is_active' => ! $user->is_active,
            ]);

            // If deactivating, revoke all tokens
            if (! $user->is_active) {
                $user->tokens()->delete();
            }

            return $user->load('roles');
        }, ['user_id' => $user->id]);
    }
}
