<?php

declare(strict_types=1);

use App\Models\Manufacturing\BomVariantGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
});

describe('BomVariantGroup authorization - unauthenticated', function () {
    it('unauthenticated user cannot list variant groups', function () {
        $this->getJson('/api/v1/bom-variant-groups')->assertUnauthorized();
    });

    it('unauthenticated user cannot create variant group', function () {
        $this->postJson('/api/v1/bom-variant-groups')->assertUnauthorized();
    });

    it('unauthenticated user cannot view variant group', function () {
        $group = BomVariantGroup::factory()->create();
        $this->getJson("/api/v1/bom-variant-groups/{$group->id}")->assertUnauthorized();
    });

    it('unauthenticated user cannot delete variant group', function () {
        $group = BomVariantGroup::factory()->create();
        $this->deleteJson("/api/v1/bom-variant-groups/{$group->id}")->assertUnauthorized();
    });
});

describe('BomVariantGroup authorization - user without boms permission', function () {
    it('forbidden to list variant groups', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/bom-variant-groups')->assertForbidden();
    });

    it('forbidden to create variant group', function () {
        Sanctum::actingAs(User::factory()->create());

        $product = \App\Models\Inventory\Product::factory()->create();
        $this->postJson('/api/v1/bom-variant-groups', [
            'name' => 'Test Group',
            'product_id' => $product->id,
        ])->assertForbidden();
    });

    it('forbidden to view variant group', function () {
        Sanctum::actingAs(User::factory()->create());

        $group = BomVariantGroup::factory()->create();
        $this->getJson("/api/v1/bom-variant-groups/{$group->id}")->assertForbidden();
    });

    it('forbidden to update variant group', function () {
        Sanctum::actingAs(User::factory()->create());

        $group = BomVariantGroup::factory()->create();
        $this->putJson("/api/v1/bom-variant-groups/{$group->id}", [
            'name' => 'Updated',
        ])->assertForbidden();
    });

    it('forbidden to delete variant group', function () {
        Sanctum::actingAs(User::factory()->create());

        $group = BomVariantGroup::factory()->create();
        $this->deleteJson("/api/v1/bom-variant-groups/{$group->id}")->assertForbidden();
    });

    it('forbidden to compare variant group BOMs', function () {
        Sanctum::actingAs(User::factory()->create());

        $group = BomVariantGroup::factory()->create();
        $this->getJson("/api/v1/bom-variant-groups/{$group->id}/compare")->assertForbidden();
    });
});

describe('BomVariantGroup authorization - admin bypass', function () {
    it('admin can list variant groups', function () {
        authenticatedAdmin();

        $this->getJson('/api/v1/bom-variant-groups')->assertSuccessful();
    });

    it('admin can view variant group', function () {
        authenticatedAdmin();

        $group = BomVariantGroup::factory()->create();
        $this->getJson("/api/v1/bom-variant-groups/{$group->id}")->assertSuccessful();
    });

    it('admin can delete variant group', function () {
        authenticatedAdmin();

        $group = BomVariantGroup::factory()->create();
        $this->deleteJson("/api/v1/bom-variant-groups/{$group->id}")->assertSuccessful();
    });

    it('admin can compare variant group BOMs', function () {
        authenticatedAdmin();

        $group = BomVariantGroup::factory()->create();
        $this->getJson("/api/v1/bom-variant-groups/{$group->id}/compare")->assertSuccessful();
    });
});
