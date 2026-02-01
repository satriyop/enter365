<?php

declare(strict_types=1);

use App\Models\Core\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
});

describe('Export authorization - forbidden without permission', function () {
    it('denies all export endpoints without reports.export', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $endpoints = [
            '/api/v1/export/trial-balance',
            '/api/v1/export/balance-sheet',
            '/api/v1/export/income-statement',
            '/api/v1/export/general-ledger?account_id=1',
            '/api/v1/export/receivable-aging',
            '/api/v1/export/payable-aging',
            '/api/v1/export/invoices',
            '/api/v1/export/bills',
            '/api/v1/export/tax-report',
        ];

        foreach ($endpoints as $endpoint) {
            $this->getJson($endpoint)->assertForbidden("Expected 403 for {$endpoint}");
        }
    });
});

describe('Export authorization - admin access', function () {
    it('admin can access export endpoints', function () {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/export/trial-balance')->assertSuccessful();
    });
});

describe('Export authorization - unauthenticated', function () {
    it('unauthenticated user cannot access exports', function () {
        $this->getJson('/api/v1/export/trial-balance')->assertUnauthorized();
    });
});

describe('Export authorization - role-based access', function () {
    it('accountant role can access exports', function () {
        $user = User::factory()->create();
        $accountantRole = Role::where('name', Role::ACCOUNTANT)->first();
        $user->roles()->attach($accountantRole);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/export/trial-balance')->assertSuccessful();
    });
});
