<?php

use App\Models\Core\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Authenticate as admin (has all permissions)
    authenticatedAdmin();
});

describe('Permission API', function () {

    it('can list all permissions', function () {
        $existingCount = Permission::count();
        Permission::factory()->count(10)->create();

        $response = $this->getJson('/api/v1/permissions');

        $response->assertOk();
        expect($response->json('meta.total'))->toBe($existingCount + 10);
    });

    it('can filter permissions by group', function () {
        // Use a unique group name to avoid collision with seeded permissions
        Permission::factory()->count(3)->inGroup('test_widgets')->create();
        Permission::factory()->count(2)->inGroup('test_gadgets')->create();

        $response = $this->getJson('/api/v1/permissions?group=test_widgets');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can search permissions', function () {
        // Use unique names to avoid collision with seeded permissions
        Permission::factory()->create(['name' => 'xwidgets.view', 'display_name' => 'View Xwidgets', 'group' => 'xwidgets', 'description' => null]);
        Permission::factory()->create(['name' => 'xwidgets.create', 'display_name' => 'Create Xwidgets', 'group' => 'xwidgets', 'description' => null]);
        Permission::factory()->create(['name' => 'xgadgets.view', 'display_name' => 'View Xgadgets', 'group' => 'xgadgets', 'description' => null]);

        $response = $this->getJson('/api/v1/permissions?search=xwidget');

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
        $existingGroups = Permission::query()->distinct('group')->count('group');
        Permission::factory()->count(3)->inGroup('test_alpha')->create();
        Permission::factory()->count(2)->inGroup('test_beta')->create();
        Permission::factory()->count(4)->inGroup('test_gamma')->create();

        $response = $this->getJson('/api/v1/permissions/grouped');

        $response->assertOk();
        // 3 new groups + whatever was seeded by migration
        expect(count($response->json('data')))->toBe($existingGroups + 3);
    });

    it('can get permission groups', function () {
        $existingGroups = Permission::query()->distinct('group')->count('group');
        Permission::factory()->count(2)->inGroup('test_delta')->create();
        Permission::factory()->count(2)->inGroup('test_epsilon')->create();

        $response = $this->getJson('/api/v1/permissions/groups');

        $response->assertOk();
        expect(count($response->json('data')))->toBe($existingGroups + 2);
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
        Permission::factory()->count(3)->inGroup('test_foo')->create();
        Permission::factory()->count(2)->inGroup('test_bar')->create();

        $grouped = Permission::allGrouped();

        expect($grouped)->toHaveKeys(['test_foo', 'test_bar'])
            ->and($grouped['test_foo'])->toHaveCount(3)
            ->and($grouped['test_bar'])->toHaveCount(2);
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
