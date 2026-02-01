<?php

declare(strict_types=1);

use App\Models\Manufacturing\MrpRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
});

describe('MRP authorization - unauthenticated', function () {
    it('unauthenticated user cannot list MRP runs', function () {
        $this->getJson('/api/v1/mrp-runs')->assertUnauthorized();
    });

    it('unauthenticated user cannot create MRP run', function () {
        $this->postJson('/api/v1/mrp-runs')->assertUnauthorized();
    });

    it('unauthenticated user cannot view MRP run', function () {
        $run = MrpRun::factory()->create();
        $this->getJson("/api/v1/mrp-runs/{$run->id}")->assertUnauthorized();
    });

    it('unauthenticated user cannot execute MRP run', function () {
        $run = MrpRun::factory()->create();
        $this->postJson("/api/v1/mrp-runs/{$run->id}/execute")->assertUnauthorized();
    });

    it('unauthenticated user cannot view MRP statistics', function () {
        $this->getJson('/api/v1/mrp/statistics')->assertUnauthorized();
    });
});

describe('MRP authorization - user without mrp permission', function () {
    it('forbidden to list MRP runs', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/mrp-runs')->assertForbidden();
    });

    it('forbidden to create MRP run', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/mrp-runs', [
            'name' => 'Test Run',
            'planning_horizon_start' => now()->toDateString(),
            'planning_horizon_end' => now()->addWeeks(4)->toDateString(),
        ])->assertForbidden();
    });

    it('forbidden to view MRP run', function () {
        Sanctum::actingAs(User::factory()->create());

        $run = MrpRun::factory()->create();
        $this->getJson("/api/v1/mrp-runs/{$run->id}")->assertForbidden();
    });

    it('forbidden to update MRP run', function () {
        Sanctum::actingAs(User::factory()->create());

        $run = MrpRun::factory()->draft()->create();
        $this->putJson("/api/v1/mrp-runs/{$run->id}", [
            'name' => 'Updated',
            'planning_horizon_start' => now()->toDateString(),
            'planning_horizon_end' => now()->addWeeks(4)->toDateString(),
        ])->assertForbidden();
    });

    it('forbidden to delete MRP run', function () {
        Sanctum::actingAs(User::factory()->create());

        $run = MrpRun::factory()->create();
        $this->deleteJson("/api/v1/mrp-runs/{$run->id}")->assertForbidden();
    });

    it('forbidden to execute MRP run', function () {
        Sanctum::actingAs(User::factory()->create());

        $run = MrpRun::factory()->create();
        $this->postJson("/api/v1/mrp-runs/{$run->id}/execute")->assertForbidden();
    });

    it('forbidden to view MRP statistics', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/mrp/statistics')->assertForbidden();
    });

    it('forbidden to view shortage report', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/mrp/shortage-report')->assertForbidden();
    });
});

describe('MRP authorization - admin bypass', function () {
    it('admin can list MRP runs', function () {
        authenticatedAdmin();

        $this->getJson('/api/v1/mrp-runs')->assertSuccessful();
    });

    it('admin can view MRP run', function () {
        authenticatedAdmin();

        $run = MrpRun::factory()->create();
        $this->getJson("/api/v1/mrp-runs/{$run->id}")->assertSuccessful();
    });

    it('admin can view MRP statistics', function () {
        authenticatedAdmin();

        $this->getJson('/api/v1/mrp/statistics')->assertSuccessful();
    });

    it('admin can view shortage report', function () {
        authenticatedAdmin();

        $this->getJson('/api/v1/mrp/shortage-report?horizon_start='.now()->toDateString().'&horizon_end='.now()->addMonth()->toDateString())
            ->assertSuccessful();
    });
});
