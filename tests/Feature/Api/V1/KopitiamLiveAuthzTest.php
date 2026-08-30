<?php

declare(strict_types=1);

use App\Models\Accounting\JournalEntry;
use App\Models\Core\Role;
use App\Models\Inventory\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
});

it('lets the accountant list journals and open financial reports', function () {
    $rina = authenticatedAccountant();
    JournalEntry::factory()->create();

    $list = $this->getJson('/api/v1/journal-entries');
    $list->assertOk();
    expect($list->json('data'))->toBeArray()->not->toBeEmpty();

    $this->getJson('/api/v1/reports/trial-balance')->assertOk();

    expect($rina->hasPermission('journals.view'))->toBeTrue()
        ->and($rina->hasPermission('reports.financial'))->toBeTrue()
        ->and($rina->hasPermission('inventory.view'))->toBeFalse();
});

it('forbids the inventory clerk from journals and trial balance', function () {
    authenticatedInventoryClerk();

    $this->getJson('/api/v1/journal-entries')->assertForbidden();
    $this->getJson('/api/v1/reports/trial-balance')->assertForbidden();
});

it('forbids the accountant from inventory summary without inventory.view', function () {
    authenticatedAccountant();
    Warehouse::factory()->default()->create();

    $this->getJson('/api/v1/inventory/summary')->assertForbidden();
});

it('returns 404 for payment reminders when the invoices pack is off', function () {
    applyFeaturePreset('pos');
    authenticatedAccountant();

    $this->getJson('/api/v1/payment-reminders')
        ->assertNotFound();
});

it('marks the administrator role as a permission bypass without attaching rows', function () {
    authenticatedAdmin();

    $response = $this->getJson('/api/v1/roles');
    $response->assertOk();

    $admin = collect($response->json('data'))->firstWhere('name', Role::ADMIN);

    expect($admin)->not->toBeNull()
        ->and($admin['grants_all_permissions'])->toBeTrue()
        ->and($admin['permissions_count'])->toBe(0);

    $accountant = collect($response->json('data'))->firstWhere('name', Role::ACCOUNTANT);

    expect($accountant['grants_all_permissions'])->toBeFalse()
        ->and($accountant['permissions_count'])->toBeGreaterThan(0);
});
