<?php

use App\Models\Core\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Authenticate user
    $user = User::factory()->create();
    Sanctum::actingAs($user);
});

describe('Permission API', function () {

    it('can list all permissions', function () {
        Permission::factory()->count(10)->create();

        $response = $this->getJson('/api/v1/permissions');

        $response->assertOk()
            ->assertJsonCount(10, 'data');
    });

    it('can filter permissions by group', function () {
        Permission::factory()->count(3)->inGroup('invoices')->create();
        Permission::factory()->count(2)->inGroup('bills')->create();

        $response = $this->getJson('/api/v1/permissions?group=invoices');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can search permissions', function () {
        Permission::factory()->create(['name' => 'invoices.view', 'display_name' => 'View Invoices']);
        Permission::factory()->create(['name' => 'invoices.create', 'display_name' => 'Create Invoices']);
        Permission::factory()->create(['name' => 'bills.view', 'display_name' => 'View Bills']);

        $response = $this->getJson('/api/v1/permissions?search=invoice');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can show a permission', function () {
        $permission = Permission::factory()->create();

        $response = $this->getJson("/api/v1/permissions/{$permission->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $permission->id)
            ->assertJsonPath('data.name', $permission->name);
    });

    it('can get permissions grouped', function () {
        Permission::factory()->count(3)->inGroup('invoices')->create();
        Permission::factory()->count(2)->inGroup('bills')->create();
        Permission::factory()->count(4)->inGroup('reports')->create();

        $response = $this->getJson('/api/v1/permissions/grouped');

        $response->assertOk()
            ->assertJsonCount(3, 'data'); // 3 groups
    });

    it('can get permission groups', function () {
        Permission::factory()->count(2)->inGroup('invoices')->create();
        Permission::factory()->count(2)->inGroup('bills')->create();

        $response = $this->getJson('/api/v1/permissions/groups');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('returns group labels in Indonesian', function () {
        Permission::factory()->inGroup('invoices')->create();

        $response = $this->getJson('/api/v1/permissions/groups');

        $response->assertOk()
            ->assertJsonFragment(['label' => 'Faktur Penjualan']);
    });
});

describe('Permission Model', function () {

    it('can find permission by name', function () {
        $permission = Permission::factory()->create(['name' => 'invoices.view']);

        $found = Permission::findByName('invoices.view');

        expect($found)->not->toBeNull()
            ->and($found->id)->toBe($permission->id);
    });

    it('returns null when permission not found', function () {
        $found = Permission::findByName('nonexistent.permission');

        expect($found)->toBeNull();
    });

    it('can get all permissions grouped', function () {
        Permission::factory()->count(3)->inGroup('invoices')->create();
        Permission::factory()->count(2)->inGroup('bills')->create();

        $grouped = Permission::allGrouped();

        expect($grouped)->toHaveKeys(['invoices', 'bills'])
            ->and($grouped['invoices'])->toHaveCount(3)
            ->and($grouped['bills'])->toHaveCount(2);
    });

    it('has default permissions list', function () {
        $defaults = Permission::getDefaultPermissions();

        expect($defaults)->toBeArray()
            ->and(count($defaults))->toBeGreaterThan(0);

        // Check structure
        $first = $defaults[0];
        expect($first)->toHaveKeys(['name', 'display_name', 'group', 'description']);
    });
});
