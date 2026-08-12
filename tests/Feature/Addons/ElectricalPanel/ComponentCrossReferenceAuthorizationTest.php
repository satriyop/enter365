<?php

declare(strict_types=1);

use App\Models\Inventory\Product;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
});

describe('ComponentCrossReference authorization - unauthenticated', function () {
    it('unauthenticated user cannot search components', function () {
        $this->getJson('/api/v1/component-search?query=test')->assertUnauthorized();
    });

    it('unauthenticated user cannot list available brands', function () {
        $this->getJson('/api/v1/available-brands')->assertUnauthorized();
    });

    it('unauthenticated user cannot view product equivalents', function () {
        $product = Product::factory()->create();
        $this->getJson("/api/v1/products/{$product->id}/equivalents")->assertUnauthorized();
    });

    it('unauthenticated user cannot view BOM brand comparison', function () {
        $bom = Bom::factory()->create();
        $this->getJson("/api/v1/boms/{$bom->id}/brand-comparison")->assertUnauthorized();
    });

    it('unauthenticated user cannot view cost optimization', function () {
        $bom = Bom::factory()->create();
        $this->getJson("/api/v1/boms/{$bom->id}/cost-optimization")->assertUnauthorized();
    });
});

describe('ComponentCrossReference authorization - user without boms permission', function () {
    it('forbidden to search components', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/component-search?category=test')->assertForbidden();
    });

    it('forbidden to view available brands', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/available-brands')->assertForbidden();
    });

    it('forbidden to view product equivalents', function () {
        Sanctum::actingAs(User::factory()->create());

        $product = Product::factory()->create();
        $this->getJson("/api/v1/products/{$product->id}/equivalents")->assertForbidden();
    });

    it('forbidden to view BOM brand comparison', function () {
        Sanctum::actingAs(User::factory()->create());

        $bom = Bom::factory()->create();
        $this->getJson("/api/v1/boms/{$bom->id}/brand-comparison")->assertForbidden();
    });

    it('forbidden to view cost optimization', function () {
        Sanctum::actingAs(User::factory()->create());

        $bom = Bom::factory()->create();
        $this->getJson("/api/v1/boms/{$bom->id}/cost-optimization")->assertForbidden();
    });

    it('forbidden to apply cost optimization', function () {
        Sanctum::actingAs(User::factory()->create());

        $bom = Bom::factory()->create();
        $this->postJson("/api/v1/boms/{$bom->id}/apply-cost-optimization")->assertForbidden();
    });

    it('forbidden to swap brand on BOM', function () {
        Sanctum::actingAs(User::factory()->create());

        $bom = Bom::factory()->create();
        $this->postJson("/api/v1/boms/{$bom->id}/swap-brand", [
            'target_brand' => 'Test Brand',
        ])->assertForbidden();
    });

    it('forbidden to view item alternatives', function () {
        Sanctum::actingAs(User::factory()->create());

        $bom = Bom::factory()->create();
        $item = BomItem::factory()->create(['bom_id' => $bom->id]);
        $this->getJson("/api/v1/boms/{$bom->id}/items/{$item->id}/alternatives")->assertForbidden();
    });

    it('forbidden to view unmapped products', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/auto-mapping/unmapped-products')->assertForbidden();
    });
});

describe('ComponentCrossReference authorization - admin bypass', function () {
    it('admin can search components', function () {
        authenticatedAdmin();

        $this->getJson('/api/v1/component-search?category=solar_panel&query=test')->assertSuccessful();
    });

    it('admin can view available brands', function () {
        authenticatedAdmin();

        $this->getJson('/api/v1/available-brands')->assertSuccessful();
    });

    it('admin can view product equivalents', function () {
        authenticatedAdmin();

        $product = Product::factory()->create();
        $this->getJson("/api/v1/products/{$product->id}/equivalents")->assertSuccessful();
    });

    it('admin can view BOM brand comparison', function () {
        authenticatedAdmin();

        $bom = Bom::factory()->create();
        $this->getJson("/api/v1/boms/{$bom->id}/brand-comparison")->assertSuccessful();
    });

    it('admin can view cost optimization', function () {
        authenticatedAdmin();

        $bom = Bom::factory()->create();
        $this->getJson("/api/v1/boms/{$bom->id}/cost-optimization")->assertSuccessful();
    });

    it('admin can view unmapped products', function () {
        authenticatedAdmin();

        $this->getJson('/api/v1/auto-mapping/unmapped-products')->assertSuccessful();
    });
});
