<?php

use App\Models\Core\Permission;
use App\Models\Core\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
});

describe('Role CRUD', function () {

    it('can list all roles', function () {
        // Create 10 unique roles
        Role::factory()->count(10)->create();

        $this->assertMaxQueries(15, function () {
            $response = $this->getJson('/api/v1/roles');
            $response->assertOk();
        });

        $response = $this->getJson('/api/v1/roles');

        $response->assertOk();
        // Assert that we have AT LEAST the 10 we just created (plus seeded ones)
        expect($response->json('meta.total'))->toBeGreaterThanOrEqual(10);
    });

    it('can filter roles by is_system', function () {
        // Create a non-system role
        Role::factory()->create(['is_system' => false, 'name' => 'non-sys-role']);

        $response = $this->getJson('/api/v1/roles?is_system=true');

        $response->assertOk();
        // Every item in the response must have is_system = true
        foreach ($response->json('data') as $role) {
            expect($role['is_system'])->toBeTrue();
        }
    });

    it('can search roles by name or display_name', function () {
        // Use a unique search term to avoid clashing with seeded data
        $uniqueTerm = 'unique-search-xyz';
        Role::factory()->create(['name' => "role-{$uniqueTerm}", 'display_name' => 'Alpha Role', 'description' => null]);
        Role::factory()->create(['name' => 'other', 'display_name' => "Display-{$uniqueTerm}", 'description' => null]);
        Role::factory()->create(['name' => 'ignored', 'display_name' => 'Ignored', 'description' => null]);

        $response = $this->getJson("/api/v1/roles?search={$uniqueTerm}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can create a role', function () {
        $response = $this->postJson('/api/v1/roles', [
            'name' => 'custom_role',
            'display_name' => 'Custom Role',
            'description' => 'A custom role',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'custom_role')
            ->assertJsonPath('data.display_name', 'Custom Role')
            ->assertJsonPath('data.is_system', false);

        $this->assertDatabaseHas('roles', [
            'name' => 'custom_role',
        ]);
    });

    it('can create a role with permissions', function () {
        $permissions = Permission::factory()->count(3)->create();

        $response = $this->postJson('/api/v1/roles', [
            'name' => 'new_role',
            'display_name' => 'New Role',
            'permissions' => $permissions->pluck('id')->toArray(),
        ]);

        $response->assertCreated()
            ->assertJsonCount(3, 'data.permissions');
    });

    it('validates required fields when creating role', function () {
        $response = $this->postJson('/api/v1/roles', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'display_name']);
    });

    it('validates role name format', function () {
        $response = $this->postJson('/api/v1/roles', [
            'name' => 'Invalid Name!',
            'display_name' => 'Test',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    });

    it('prevents duplicate role names', function () {
        Role::factory()->create(['name' => 'existing_role']);

        $response = $this->postJson('/api/v1/roles', [
            'name' => 'existing_role',
            'display_name' => 'Test',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    });

    it('can show a role with permissions', function () {
        $role = Role::factory()->create();
        $permissions = Permission::factory()->count(3)->create();
        $role->permissions()->attach($permissions);

        $response = $this->getJson("/api/v1/roles/{$role->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $role->id)
            ->assertJsonCount(3, 'data.permissions');
    });

    it('can update a role', function () {
        $role = Role::factory()->create(['is_system' => false]);

        $response = $this->putJson("/api/v1/roles/{$role->id}", [
            'display_name' => 'Updated Name',
            'description' => 'Updated description',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.display_name', 'Updated Name');
    });

    it('cannot rename system role name', function () {
        // Create a unique system role specifically for this test
        $role = Role::factory()->system()->create(['name' => 'unique-system-role']);

        $response = $this->putJson("/api/v1/roles/{$role->id}", [
            'name' => 'renamed_name',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Nama role sistem tidak bisa diubah.');
    });

    it('can update system role display name', function () {
        $role = Role::factory()->system()->create(['name' => 'other-system-role']);

        $response = $this->putJson("/api/v1/roles/{$role->id}", [
            'display_name' => 'Updated Display Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.display_name', 'Updated Display Name');
    });

    it('can delete a custom role', function () {
        $role = Role::factory()->create(['is_system' => false]);

        $response = $this->deleteJson("/api/v1/roles/{$role->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    });

    it('cannot delete a system role', function () {
        $role = Role::factory()->system()->create();

        $response = $this->deleteJson("/api/v1/roles/{$role->id}");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Role sistem tidak bisa dihapus.');
    });

    it('cannot delete a role with users', function () {
        $role = Role::factory()->create(['is_system' => false]);
        $user = User::factory()->create();
        $role->users()->attach($user);

        $response = $this->deleteJson("/api/v1/roles/{$role->id}");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Role tidak bisa dihapus karena masih memiliki pengguna.');
    });
});

describe('Role Permissions', function () {

    it('can sync permissions for a role', function () {
        $role = Role::factory()->create();
        $permissions = Permission::factory()->count(5)->create();

        $response = $this->postJson("/api/v1/roles/{$role->id}/sync-permissions", [
            'permissions' => $permissions->take(3)->pluck('id')->toArray(),
        ]);

        $response->assertOk();
        expect($role->fresh()->permissions->count())->toBe(3);
    });

    it('can get users with a role', function () {
        $role = Role::factory()->create();
        $users = User::factory()->count(3)->create();
        $role->users()->attach($users);

        $response = $this->getJson("/api/v1/roles/{$role->id}/users");

        $response->assertOk()
            ->assertJsonPath('role.id', $role->id)
            ->assertJsonCount(3, 'users');
    });

    it('role can check if it has a permission', function () {
        $role = Role::factory()->create();
        $permission = Permission::factory()->create(['name' => 'invoices.view']);
        $role->permissions()->attach($permission);

        expect($role->hasPermission('invoices.view'))->toBeTrue()
            ->and($role->hasPermission('invoices.create'))->toBeFalse();
    });

    it('admin role has all permissions', function () {
        $role = Role::factory()->admin()->create();

        expect($role->hasPermission('anything'))->toBeTrue()
            ->and($role->hasPermission('invoices.view'))->toBeTrue()
            ->and($role->hasPermission('random.permission'))->toBeTrue();
    });
});

describe('User Roles', function () {

    it('can assign role to user', function () {
        $user = User::factory()->create();
        $role = Role::factory()->create();

        $user->assignRole($role->name);

        expect($user->hasRole($role->name))->toBeTrue();
    });

    it('can remove role from user', function () {
        $user = User::factory()->create();
        $role = Role::factory()->create();
        $user->roles()->attach($role);

        $user->removeRole($role->name);

        expect($user->hasRole($role->name))->toBeFalse();
    });

    it('can sync roles for user', function () {
        $user = User::factory()->create();
        $roles = Role::factory()->count(3)->create();

        $user->syncRoles($roles->take(2)->pluck('name')->toArray());

        expect($user->roles->count())->toBe(2);
    });

    it('user can check if has role', function () {
        $user = User::factory()->create();
        $role = Role::factory()->create(['name' => 'tester']);
        $user->roles()->attach($role);

        expect($user->hasRole('tester'))->toBeTrue()
            ->and($user->hasRole('other'))->toBeFalse();
    });

    it('user can check if has any role', function () {
        $user = User::factory()->create();
        $role = Role::factory()->create(['name' => 'tester']);
        $user->roles()->attach($role);

        expect($user->hasAnyRole(['tester', 'admin']))->toBeTrue()
            ->and($user->hasAnyRole(['admin', 'manager']))->toBeFalse();
    });

    it('user can check if has permission through role', function () {
        $user = User::factory()->create();
        $role = Role::factory()->create();
        $permission = Permission::factory()->create(['name' => 'invoices.view']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        expect($user->hasPermission('invoices.view'))->toBeTrue()
            ->and($user->hasPermission('invoices.create'))->toBeFalse();
    });

    it('admin user has all permissions', function () {
        $user = User::factory()->create();
        $role = Role::factory()->admin()->create();
        $user->roles()->attach($role);

        expect($user->isAdmin())->toBeTrue()
            ->and($user->hasPermission('anything'))->toBeTrue()
            ->and($user->hasPermission('random.permission'))->toBeTrue();
    });

    it('user can get all permissions', function () {
        $user = User::factory()->create();
        $role = Role::factory()->create();
        $permissions = Permission::factory()->count(5)->create();
        $role->permissions()->attach($permissions->take(3));
        $user->roles()->attach($role);

        $userPermissions = $user->getAllPermissions();

        expect($userPermissions->count())->toBe(3);
    });
});
