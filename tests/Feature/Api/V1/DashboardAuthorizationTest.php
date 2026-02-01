<?php

declare(strict_types=1);

use App\Models\Core\Permission;
use App\Models\Core\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
});

describe('Dashboard authorization', function () {
    it('admin can access dashboard summary', function () {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/dashboard/summary');

        $response->assertOk();
    });

    it('user without dashboard.view cannot access summary', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/dashboard/summary');

        $response->assertForbidden();
    });

    it('user with dashboard.view can access summary', function () {
        $user = User::factory()->create();
        $role = Role::factory()->create();
        $permission = Permission::where('name', 'dashboard.view')->first();
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/dashboard/summary');

        $response->assertOk();
    });

    it('user without dashboard.financials cannot access receivables', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/dashboard/receivables');

        $response->assertForbidden();
    });

    it('user without dashboard.financials cannot access payables', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/dashboard/payables');

        $response->assertForbidden();
    });

    it('user without dashboard.financials cannot access cash flow', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/dashboard/cash-flow');

        $response->assertForbidden();
    });

    it('user without dashboard.financials cannot access profit-loss', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/dashboard/profit-loss');

        $response->assertForbidden();
    });

    it('user without dashboard.kpis cannot access kpis', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/dashboard/kpis');

        $response->assertForbidden();
    });

    it('admin can access all dashboard endpoints', function () {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $endpoints = [
            '/api/v1/dashboard/summary',
            '/api/v1/dashboard/receivables',
            '/api/v1/dashboard/payables',
            '/api/v1/dashboard/cash-flow',
            '/api/v1/dashboard/profit-loss',
            '/api/v1/dashboard/kpis',
        ];

        foreach ($endpoints as $endpoint) {
            $this->getJson($endpoint)->assertOk();
        }
    });

    it('unauthenticated user cannot access dashboard', function () {
        $response = $this->getJson('/api/v1/dashboard/summary');

        $response->assertUnauthorized();
    });
});
