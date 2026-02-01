<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
});

describe('Permission authorization - forbidden without permission', function () {
    it('denies permission listing without users.manage_roles', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $endpoints = [
            '/api/v1/permissions',
            '/api/v1/permissions/grouped',
            '/api/v1/permissions/groups',
        ];

        foreach ($endpoints as $endpoint) {
            $this->getJson($endpoint)->assertForbidden("Expected 403 for {$endpoint}");
        }
    });
});

describe('Permission authorization - admin access', function () {
    it('admin can list permissions', function () {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/permissions')->assertSuccessful();
    });

    it('admin can view grouped permissions', function () {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/permissions/grouped')->assertSuccessful();
    });

    it('admin can view permission groups', function () {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/permissions/groups')->assertSuccessful();
    });
});

describe('Permission authorization - unauthenticated', function () {
    it('unauthenticated user cannot access permissions', function () {
        $this->getJson('/api/v1/permissions')->assertUnauthorized();
    });
});
