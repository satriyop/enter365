<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
});

describe('Feature flags - any authenticated user', function () {
    it('allows feature listing for regular authenticated users (SPA nav)', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/features')
            ->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    'modules',
                    'enabled',
                    'disabled',
                ],
            ]);
    });
});

describe('Feature authorization - admin access', function () {
    it('admin can view features', function () {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/features')->assertSuccessful();
    });
});

describe('Feature authorization - unauthenticated', function () {
    it('unauthenticated user cannot access features', function () {
        $this->getJson('/api/v1/features')->assertUnauthorized();
    });
});
