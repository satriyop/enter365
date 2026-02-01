<?php

declare(strict_types=1);

use App\Models\Core\Role;
use App\Models\User;
use App\Services\Shared\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);

    $this->currentUser = User::factory()->create();
    $this->actingAs($this->currentUser);

    $this->service = app(UserService::class);
});

describe('create', function () {
    it('creates a user with hashed password', function () {
        $user = $this->service->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'secret123',
        ]);

        expect($user)
            ->toBeInstanceOf(User::class)
            ->name->toBe('John Doe')
            ->email->toBe('john@example.com')
            ->is_active->toBeTrue()
            ->and(Hash::check('secret123', $user->password))->toBeTrue();
    });

    it('creates a user with roles', function () {
        $role = Role::where('name', 'admin')->first();

        $user = $this->service->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'secret123',
            'roles' => [$role->id],
        ]);

        expect($user->roles)->toHaveCount(1)
            ->and($user->roles->first()->name)->toBe('admin');
    });

    it('creates inactive user', function () {
        $user = $this->service->create([
            'name' => 'Inactive User',
            'email' => 'inactive@example.com',
            'password' => 'secret123',
            'is_active' => false,
        ]);

        expect($user->is_active)->toBeFalse();
    });
});

describe('update', function () {
    it('updates user name and email', function () {
        $user = User::factory()->create();

        $updated = $this->service->update($user, [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);

        expect($updated->name)->toBe('Updated Name')
            ->and($updated->email)->toBe('updated@example.com');
    });

    it('does not update roles without canManageRoles', function () {
        $user = User::factory()->create();
        $role = Role::where('name', 'admin')->first();

        $this->service->update($user, [
            'roles' => [$role->id],
        ], canManageRoles: false);

        expect($user->roles)->toHaveCount(0);
    });

    it('updates roles with canManageRoles', function () {
        $user = User::factory()->create();
        $role = Role::where('name', 'admin')->first();

        $updated = $this->service->update($user, [
            'roles' => [$role->id],
        ], canManageRoles: true);

        expect($updated->roles)->toHaveCount(1);
    });
});

describe('delete', function () {
    it('deletes another user', function () {
        $targetUser = User::factory()->create();

        $this->service->delete($targetUser, $this->currentUser->id);

        expect(User::find($targetUser->id))->toBeNull();
    });

    it('prevents self-deletion', function () {
        expect(fn () => $this->service->delete($this->currentUser, $this->currentUser->id))
            ->toThrow(\Exception::class, 'Anda tidak dapat menghapus akun Anda sendiri.');
    });
});

describe('updatePassword', function () {
    it('updates password for another user and revokes tokens', function () {
        $targetUser = User::factory()->create();

        $this->service->updatePassword($targetUser, 'newpassword123', $this->currentUser->id);

        $targetUser->refresh();
        expect(Hash::check('newpassword123', $targetUser->password))->toBeTrue();
    });
});

describe('assignRoles', function () {
    it('assigns roles to user', function () {
        $user = User::factory()->create();
        $role = Role::where('name', 'admin')->first();

        $result = $this->service->assignRoles($user, [$role->id]);

        expect($result->roles)->toHaveCount(1)
            ->and($result->roles->first()->name)->toBe('admin');
    });

    it('replaces existing roles on sync', function () {
        $user = User::factory()->create();
        $adminRole = Role::where('name', 'admin')->first();
        $user->roles()->attach($adminRole);

        // Create a non-system role for testing
        $customRole = Role::create([
            'name' => 'custom',
            'display_name' => 'Custom Role',
        ]);

        $result = $this->service->assignRoles($user, [$customRole->id]);

        expect($result->roles)->toHaveCount(1)
            ->and($result->roles->first()->name)->toBe('custom');
    });
});

describe('toggleActive', function () {
    it('deactivates an active user', function () {
        $targetUser = User::factory()->create(['is_active' => true]);

        $result = $this->service->toggleActive($targetUser, $this->currentUser->id);

        expect($result->is_active)->toBeFalse();
    });

    it('activates an inactive user', function () {
        $targetUser = User::factory()->create(['is_active' => false]);

        $result = $this->service->toggleActive($targetUser, $this->currentUser->id);

        expect($result->is_active)->toBeTrue();
    });

    it('prevents self-deactivation', function () {
        expect(fn () => $this->service->toggleActive($this->currentUser, $this->currentUser->id))
            ->toThrow(\Exception::class, 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
    });
});
