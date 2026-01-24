<?php

use App\Models\Core\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
});

describe('List Users', function () {

    it('admin can list all users', function () {
        $admin = User::factory()->admin()->create();
        User::factory()->count(5)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/users');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'is_active',
                        'roles',
                    ],
                ],
                'meta',
                'links',
            ]);

        // 5 + 1 admin = 6 users total
        expect($response->json('meta.total'))->toBe(6);
    });

    it('non-admin cannot list users', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/users');

        $response->assertForbidden();
    });

    it('can filter by active status', function () {
        $admin = User::factory()->admin()->create();
        User::factory()->count(3)->create(['is_active' => true]);
        User::factory()->count(2)->inactive()->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/users?is_active=true');

        $response->assertOk();
        // 3 active + 1 admin = 4
        expect($response->json('meta.total'))->toBe(4);
    });

    it('can filter by role', function () {
        $admin = User::factory()->admin()->create();
        User::factory()->count(3)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/users?role='.Role::ADMIN);

        $response->assertOk();
        expect($response->json('meta.total'))->toBe(1);
    });

    it('can search by name or email', function () {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);
        User::factory()->create(['name' => 'Bob Wilson', 'email' => 'bob@example.com']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/users?search=john');

        $response->assertOk();
        expect($response->json('meta.total'))->toBe(1);
        expect($response->json('data.0.name'))->toBe('John Doe');
    });

    it('requires authentication', function () {
        $response = $this->getJson('/api/v1/users');

        $response->assertUnauthorized();
    });

});

describe('Create User', function () {

    it('admin can create user', function () {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'User berhasil dibuat.')
            ->assertJsonPath('user.name', 'New User')
            ->assertJsonPath('user.email', 'newuser@example.com')
            ->assertJsonPath('user.is_active', true);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
        ]);
    });

    it('admin can create user with roles', function () {
        $admin = User::factory()->admin()->create();
        $role = Role::where('name', Role::ACCOUNTANT)->first();

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Accountant User',
            'email' => 'accountant@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'roles' => [$role->id],
        ]);

        $response->assertCreated();

        $user = User::where('email', 'accountant@example.com')->first();
        expect($user->hasRole(Role::ACCOUNTANT))->toBeTrue();
    });

    it('non-admin cannot create user', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $response->assertForbidden();
    });

    it('validates required fields', function () {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/users', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    });

    it('validates unique email', function () {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'existing@example.com']);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'New User',
            'email' => 'existing@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('validates password confirmation', function () {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'DifferentPass123!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    });

});

describe('Show User', function () {

    it('admin can view any user', function () {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['name' => 'Test User']);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/users/{$user->id}");

        $response->assertOk()
            ->assertJsonPath('user.name', 'Test User');
    });

    it('user can view own profile', function () {
        $user = User::factory()->create(['name' => 'My Profile']);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/users/{$user->id}");

        $response->assertOk()
            ->assertJsonPath('user.name', 'My Profile');
    });

    it('user cannot view other users profile', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Sanctum::actingAs($user1);

        $response = $this->getJson("/api/v1/users/{$user2->id}");

        $response->assertForbidden();
    });

});

describe('Update User', function () {

    it('admin can update any user', function () {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/v1/users/{$user->id}", [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'is_active' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'User berhasil diperbarui.')
            ->assertJsonPath('user.name', 'Updated Name')
            ->assertJsonPath('user.email', 'updated@example.com')
            ->assertJsonPath('user.is_active', false);
    });

    it('user can update own profile', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/users/{$user->id}", [
            'name' => 'My New Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.name', 'My New Name');
    });

    it('user cannot update own is_active status', function () {
        $user = User::factory()->create(['is_active' => true]);
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/users/{$user->id}", [
            'name' => 'Updated',
            'is_active' => false,
        ]);

        $response->assertOk();
        // is_active should not be changed
        expect($user->fresh()->is_active)->toBeTrue();
    });

    it('user cannot update other users', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Sanctum::actingAs($user1);

        $response = $this->putJson("/api/v1/users/{$user2->id}", [
            'name' => 'Hacked Name',
        ]);

        $response->assertForbidden();
    });

    it('admin can update user roles', function () {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $role = Role::where('name', Role::ACCOUNTANT)->first();

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/v1/users/{$user->id}", [
            'roles' => [$role->id],
        ]);

        $response->assertOk();
        expect($user->fresh()->hasRole(Role::ACCOUNTANT))->toBeTrue();
    });

});

describe('Delete User', function () {

    it('admin can delete user', function () {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/v1/users/{$user->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'User berhasil dihapus.');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    });

    it('admin cannot delete themselves', function () {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/v1/users/{$admin->id}");

        $response->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    });

    it('non-admin cannot delete users', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Sanctum::actingAs($user1);

        $response = $this->deleteJson("/api/v1/users/{$user2->id}");

        $response->assertForbidden();
    });

    it('revokes all tokens when deleting user', function () {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $user->createToken('device-1');
        $user->createToken('device-2');

        expect($user->tokens()->count())->toBe(2);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/users/{$user->id}");

        // Tokens should be deleted with the user
        expect(\Laravel\Sanctum\PersonalAccessToken::where('tokenable_id', $user->id)->count())->toBe(0);
    });

});

describe('Update Password', function () {

    it('user can change own password with current password', function () {
        $user = User::factory()->create([
            'password' => bcrypt('OldPassword123!'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/users/{$user->id}/password", [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Password berhasil diperbarui.');

        // Verify can login with new password
        expect(\Hash::check('NewPassword123!', $user->fresh()->password))->toBeTrue();
    });

    it('user cannot change password with wrong current password', function () {
        $user = User::factory()->create([
            'password' => bcrypt('OldPassword123!'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/users/{$user->id}/password", [
            'current_password' => 'WrongPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);
    });

    it('admin can change other user password without current password', function () {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create([
            'password' => bcrypt('OldPassword123!'),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/users/{$user->id}/password", [
            'password' => 'AdminSetPassword123!',
            'password_confirmation' => 'AdminSetPassword123!',
        ]);

        $response->assertOk();
        expect(\Hash::check('AdminSetPassword123!', $user->fresh()->password))->toBeTrue();
    });

    it('user cannot change other users password', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Sanctum::actingAs($user1);

        $response = $this->postJson("/api/v1/users/{$user2->id}/password", [
            'current_password' => 'password',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertForbidden();
    });

    it('revokes other tokens when changing own password', function () {
        $user = User::factory()->create([
            'password' => bcrypt('OldPassword123!'),
        ]);

        // Create multiple tokens
        $user->createToken('device-1');
        $user->createToken('device-2');
        $currentToken = $user->createToken('current-device');

        expect($user->tokens()->count())->toBe(3);

        // Act as the user with current token
        $this->withHeader('Authorization', 'Bearer '.$currentToken->plainTextToken);

        $response = $this->postJson("/api/v1/users/{$user->id}/password", [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertOk();

        // Only current token should remain
        expect($user->fresh()->tokens()->count())->toBe(1);
    });

});

describe('Assign Roles', function () {

    it('admin can assign roles to user', function () {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $role = Role::where('name', Role::ACCOUNTANT)->first();

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/users/{$user->id}/roles", [
            'roles' => [$role->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Role berhasil diperbarui.');

        expect($user->fresh()->hasRole(Role::ACCOUNTANT))->toBeTrue();
    });

    it('admin can assign multiple roles', function () {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $accountant = Role::where('name', Role::ACCOUNTANT)->first();
        $cashierRole = Role::where('name', Role::CASHIER)->first();

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/users/{$user->id}/roles", [
            'roles' => [$accountant->id, $cashierRole->id],
        ]);

        $response->assertOk();
        expect($user->fresh()->roles()->count())->toBe(2);
    });

    it('non-admin cannot assign roles', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $role = Role::where('name', Role::ACCOUNTANT)->first();

        Sanctum::actingAs($user1);

        $response = $this->postJson("/api/v1/users/{$user2->id}/roles", [
            'roles' => [$role->id],
        ]);

        $response->assertForbidden();
    });

});

describe('Toggle Active Status', function () {

    it('admin can deactivate user', function () {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['is_active' => true]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/users/{$user->id}/toggle-active");

        $response->assertOk()
            ->assertJsonPath('user.is_active', false);

        expect($user->fresh()->is_active)->toBeFalse();
    });

    it('admin can activate user', function () {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->inactive()->create();

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/users/{$user->id}/toggle-active");

        $response->assertOk()
            ->assertJsonPath('user.is_active', true);

        expect($user->fresh()->is_active)->toBeTrue();
    });

    it('admin cannot deactivate themselves', function () {
        $admin = User::factory()->admin()->create(['is_active' => true]);
        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/users/{$admin->id}/toggle-active");

        $response->assertUnprocessable();
        expect($admin->fresh()->is_active)->toBeTrue();
    });

    it('revokes tokens when deactivating user', function () {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['is_active' => true]);
        $user->createToken('device-1');
        $user->createToken('device-2');

        expect($user->tokens()->count())->toBe(2);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/users/{$user->id}/toggle-active");

        expect($user->fresh()->tokens()->count())->toBe(0);
    });

    it('non-admin cannot toggle active status', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Sanctum::actingAs($user1);

        $response = $this->postJson("/api/v1/users/{$user2->id}/toggle-active");

        $response->assertForbidden();
    });

});
