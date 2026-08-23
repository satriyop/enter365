<?php

use App\Models\Core\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
});

describe('Login', function () {

    it('can login with valid credentials', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'is_active',
                    'roles',
                ],
                'token',
                'token_type',
            ])
            ->assertJsonPath('message', 'Login berhasil.')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', 'test@example.com');

        expect($response->json('token'))->not->toBeEmpty();
    });

    it('includes cashier pos permissions on login so the till can open', function () {
        $user = User::factory()->create([
            'email' => 'siti@kasir.test',
            'password' => bcrypt('password'),
        ]);
        $cashier = Role::query()->where('name', Role::CASHIER)->firstOrFail();
        $user->roles()->attach($cashier);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'siti@kasir.test',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.roles.0.name', Role::CASHIER);

        $names = collect($response->json('user.permissions'))->pluck('name');
        expect($names)->toContain('pos.sale.checkout')
            ->and($names)->toContain('pos.session.open');
    });

    it('cannot login with invalid email', function () {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'wrong@example.com',
            'password' => 'password123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('cannot login with invalid password', function () {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('cannot login with inactive account', function () {
        User::factory()->inactive()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        expect($response->json('errors.email.0'))->toContain('tidak aktif');
    });

    it('validates required fields', function () {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    });

    it('can login with custom device name', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'device_name' => 'iPhone 15 Pro',
        ]);

        $response->assertOk();

        // Check token was created with device name
        $token = $user->tokens()->first();
        expect($token->name)->toBe('iPhone 15 Pro');
    });

});

describe('Logout', function () {

    it('can logout and revoke current token', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertOk()
            ->assertJsonPath('message', 'Logout berhasil.');

        // Verify token is revoked
        expect($user->tokens()->count())->toBe(0);
    });

    it('can logout from all devices', function () {
        $user = User::factory()->create();

        // Create multiple tokens
        $user->createToken('device-1');
        $user->createToken('device-2');
        $user->createToken('device-3');

        expect($user->tokens()->count())->toBe(3);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/auth/logout-all');

        $response->assertOk()
            ->assertJsonPath('message', 'Logout dari semua perangkat berhasil.');

        // Verify all tokens are revoked
        expect($user->fresh()->tokens()->count())->toBe(0);
    });

    it('requires authentication to logout', function () {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertUnauthorized();
    });

});

describe('Get Current User', function () {

    it('can get current user info', function () {
        $user = User::factory()->admin()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'is_active',
                    'roles',
                    'permissions',
                ],
            ])
            ->assertJsonPath('data.name', 'John Doe')
            ->assertJsonPath('data.email', 'john@example.com');

        // Admin should have all permissions
        expect($response->json('data.permissions'))->not->toBeEmpty();
    });

    it('requires authentication', function () {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertUnauthorized();
    });

    it('includes user roles', function () {
        $user = User::factory()->admin()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk();

        $roles = $response->json('data.roles');
        expect($roles)->toBeArray();
        expect(collect($roles)->pluck('name'))->toContain(Role::ADMIN);
    });

});

describe('Refresh Token', function () {

    it('can refresh token', function () {
        $user = User::factory()->create();
        $oldToken = $user->createToken('test-device')->plainTextToken;

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/auth/refresh');

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'token',
                'token_type',
            ])
            ->assertJsonPath('message', 'Token berhasil diperbarui.');

        $newToken = $response->json('token');
        expect($newToken)->not->toBe($oldToken);
    });

    it('requires authentication', function () {
        $response = $this->postJson('/api/v1/auth/refresh');

        $response->assertUnauthorized();
    });

});
