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

describe('Report authorization - forbidden without permission', function () {
    it('denies financial reports without reports.financial', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $financialEndpoints = [
            '/api/v1/reports/trial-balance',
            '/api/v1/reports/balance-sheet',
            '/api/v1/reports/income-statement',
            '/api/v1/reports/general-ledger',
            '/api/v1/reports/changes-in-equity',
        ];

        foreach ($financialEndpoints as $endpoint) {
            $this->getJson($endpoint)->assertForbidden("Expected 403 for {$endpoint}");
        }
    });

    it('denies tax reports without reports.tax', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $taxEndpoints = [
            '/api/v1/reports/ppn-summary',
            '/api/v1/reports/ppn-monthly',
            '/api/v1/reports/tax-invoice-list',
            '/api/v1/reports/input-tax-list',
        ];

        foreach ($taxEndpoints as $endpoint) {
            $this->getJson($endpoint)->assertForbidden("Expected 403 for {$endpoint}");
        }
    });

    it('denies aging reports without reports.aging', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $agingEndpoints = [
            '/api/v1/reports/receivable-aging',
            '/api/v1/reports/payable-aging',
        ];

        foreach ($agingEndpoints as $endpoint) {
            $this->getJson($endpoint)->assertForbidden("Expected 403 for {$endpoint}");
        }
    });

    it('denies cash flow reports without reports.cash_flow', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $cashFlowEndpoints = [
            '/api/v1/reports/cash-flow',
            '/api/v1/reports/daily-cash-movement',
        ];

        foreach ($cashFlowEndpoints as $endpoint) {
            $this->getJson($endpoint)->assertForbidden("Expected 403 for {$endpoint}");
        }
    });

    it('denies project reports without reports.project', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $projectEndpoints = [
            '/api/v1/reports/project-profitability',
            '/api/v1/reports/project-cost-analysis',
        ];

        foreach ($projectEndpoints as $endpoint) {
            $this->getJson($endpoint)->assertForbidden("Expected 403 for {$endpoint}");
        }
    });

    it('denies manufacturing reports without reports.manufacturing', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $mfgEndpoints = [
            '/api/v1/reports/work-order-costs',
            '/api/v1/reports/cost-variance',
            '/api/v1/reports/subcontractor-summary',
            '/api/v1/reports/subcontractor-retention',
        ];

        foreach ($mfgEndpoints as $endpoint) {
            $this->getJson($endpoint)->assertForbidden("Expected 403 for {$endpoint}");
        }
    });

    it('denies COGS reports without reports.cogs', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $cogsEndpoints = [
            '/api/v1/reports/cogs-summary',
            '/api/v1/reports/cogs-by-product',
            '/api/v1/reports/cogs-by-category',
            '/api/v1/reports/cogs-monthly-trend',
        ];

        foreach ($cogsEndpoints as $endpoint) {
            $this->getJson($endpoint)->assertForbidden("Expected 403 for {$endpoint}");
        }
    });
});

describe('Report authorization - admin access', function () {
    it('admin can access financial reports', function () {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/reports/trial-balance')->assertOk();
    });

    it('admin can access tax reports', function () {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/reports/ppn-summary')->assertOk();
    });

    it('admin can access aging reports', function () {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/reports/receivable-aging')->assertOk();
    });

    it('admin can access cash flow reports', function () {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/reports/cash-flow')->assertOk();
    });

    it('admin can access COGS reports', function () {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/reports/cogs-summary')->assertOk();
    });
});

describe('Report authorization - role-based access', function () {
    it('accountant role has all report permissions', function () {
        $user = User::factory()->create();
        $accountantRole = Role::where('name', Role::ACCOUNTANT)->first();
        $user->roles()->attach($accountantRole);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/reports/trial-balance')->assertOk();
        $this->getJson('/api/v1/reports/ppn-summary')->assertOk();
        $this->getJson('/api/v1/reports/receivable-aging')->assertOk();
        $this->getJson('/api/v1/reports/cash-flow')->assertOk();
        $this->getJson('/api/v1/reports/cogs-summary')->assertOk();
    });

    it('viewer role can access financial and aging reports', function () {
        $user = User::factory()->create();
        $viewerRole = Role::where('name', Role::VIEWER)->first();
        $user->roles()->attach($viewerRole);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/reports/trial-balance')->assertOk();
        $this->getJson('/api/v1/reports/receivable-aging')->assertOk();
    });

    it('viewer role cannot access tax reports', function () {
        $user = User::factory()->create();
        $viewerRole = Role::where('name', Role::VIEWER)->first();
        $user->roles()->attach($viewerRole);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/reports/ppn-summary')->assertForbidden();
    });
});

describe('Report response format', function () {
    it('returns standardized success envelope', function () {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/reports/trial-balance');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ])
            ->assertJson([
                'success' => true,
            ]);
    });

    it('returns report data nested in data key', function () {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/reports/trial-balance');

        $response->assertOk()
            ->assertJsonPath('data.report_name', 'Neraca Saldo');
    });
});

describe('Report authorization - unauthenticated', function () {
    it('unauthenticated user cannot access reports', function () {
        $this->getJson('/api/v1/reports/trial-balance')->assertUnauthorized();
    });
});
