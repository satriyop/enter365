<?php

declare(strict_types=1);

use App\Models\Core\Permission;
use App\Models\Core\Role;
use App\Models\User;
use App\Services\Shared\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);

    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->service = app(RoleService::class);
});

describe('create', function () {
    it('creates a role', function () {
        $role = $this->service->create([
            'name' => 'manager',
            'display_name' => 'Manager',
            'description' => 'Project manager role',
        ]);

        expect($role)
            ->toBeInstanceOf(Role::class)
            ->name->toBe('manager')
            ->display_name->toBe('Manager');
    });

    it('creates a role with permissions', function () {
        $permissions = Permission::take(3)->pluck('id')->toArray();

        $role = $this->service->create([
            'name' => 'editor',
            'display_name' => 'Editor',
            'permissions' => $permissions,
        ]);

        expect($role->permissions)->toHaveCount(3);
    });
});

describe('update', function () {
    it('updates role display name and description', function () {
        $role = Role::create([
            'name' => 'custom',
            'display_name' => 'Custom',
        ]);

        $updated = $this->service->update($role, [
            'display_name' => 'Updated Custom',
            'description' => 'Updated description',
        ]);

        expect($updated->display_name)->toBe('Updated Custom')
            ->and($updated->description)->toBe('Updated description');
    });

    it('prevents renaming system role', function () {
        $systemRole = Role::where('is_system', true)->first();

        expect(fn () => $this->service->update($systemRole, ['name' => 'renamed']))
            ->toThrow(\Exception::class, 'Nama role sistem tidak bisa diubah.');
    });

    it('updates permissions on role', function () {
        $role = Role::create([
            'name' => 'custom',
            'display_name' => 'Custom',
        ]);
        $permissions = Permission::take(5)->pluck('id')->toArray();

        $updated = $this->service->update($role, [
            'permissions' => $permissions,
        ]);

        expect($updated->permissions)->toHaveCount(5);
    });
});

describe('delete', function () {
    it('deletes a non-system role', function () {
        $role = Role::create([
            'name' => 'temporary',
            'display_name' => 'Temporary',
        ]);

        $this->service->delete($role);

        expect(Role::find($role->id))->toBeNull();
    });

    it('prevents deleting system role', function () {
        $systemRole = Role::where('is_system', true)->first();

        expect(fn () => $this->service->delete($systemRole))
            ->toThrow(\Exception::class, 'Role sistem tidak bisa dihapus.');
    });

    it('prevents deleting role with users', function () {
        $role = Role::create([
            'name' => 'used_role',
            'display_name' => 'Used Role',
        ]);
        $this->user->roles()->attach($role);

        expect(fn () => $this->service->delete($role))
            ->toThrow(\Exception::class, 'Role tidak bisa dihapus karena masih memiliki pengguna.');
    });
});

describe('syncPermissions', function () {
    it('syncs permissions on a role', function () {
        $role = Role::create([
            'name' => 'custom',
            'display_name' => 'Custom',
        ]);
        $permissions = Permission::take(3)->pluck('id')->toArray();

        $result = $this->service->syncPermissions($role, $permissions);

        expect($result->permissions)->toHaveCount(3);
    });

    it('replaces existing permissions', function () {
        $role = Role::create([
            'name' => 'custom',
            'display_name' => 'Custom',
        ]);
        $oldPerms = Permission::take(3)->pluck('id')->toArray();
        $role->permissions()->sync($oldPerms);

        $newPerms = Permission::skip(3)->take(2)->pluck('id')->toArray();
        $result = $this->service->syncPermissions($role, $newPerms);

        expect($result->permissions)->toHaveCount(2);
    });
});
