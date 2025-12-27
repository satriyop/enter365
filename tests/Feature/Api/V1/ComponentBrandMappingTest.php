<?php

use App\Models\Accounting\ComponentBrandMapping;
use App\Models\Accounting\ComponentStandard;
use App\Models\Accounting\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
});

describe('Component Brand Mapping CRUD', function () {

    it('can add a brand mapping to a standard', function () {
        $standard = ComponentStandard::factory()->create();
        $product = Product::factory()->create();

        $response = $this->postJson(
            "/api/v1/component-standards/{$standard->id}/mappings",
            [
                'brand' => 'schneider',
                'product_id' => $product->id,
                'brand_sku' => 'A9F79110',
                'is_preferred' => false,
                'price_factor' => 1.0,
                'notes' => 'Standard Schneider MCB mapping',
            ]
        );

        $response->assertCreated()
            ->assertJsonPath('data.brand', 'schneider')
            ->assertJsonPath('data.product_id', $product->id)
            ->assertJsonPath('data.brand_sku', 'A9F79110')
            ->assertJsonPath('data.is_preferred', false);

        $this->assertDatabaseHas('component_brand_mappings', [
            'component_standard_id' => $standard->id,
            'brand' => 'schneider',
            'product_id' => $product->id,
        ]);
    });

    it('can set mapping as preferred when creating', function () {
        $standard = ComponentStandard::factory()->create();
        $product = Product::factory()->create();

        $response = $this->postJson(
            "/api/v1/component-standards/{$standard->id}/mappings",
            [
                'brand' => 'schneider',
                'product_id' => $product->id,
                'brand_sku' => 'A9F79110',
                'is_preferred' => true,
            ]
        );

        $response->assertCreated()
            ->assertJsonPath('data.is_preferred', true);

        $this->assertDatabaseHas('component_brand_mappings', [
            'component_standard_id' => $standard->id,
            'brand' => 'schneider',
            'is_preferred' => true,
        ]);
    });

    it('validates required fields when creating mapping', function () {
        $standard = ComponentStandard::factory()->create();

        $response = $this->postJson("/api/v1/component-standards/{$standard->id}/mappings", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['brand', 'product_id']);
    });

    it('validates brand_sku is required when creating mapping', function () {
        $standard = ComponentStandard::factory()->create();
        $product = Product::factory()->create();

        $response = $this->postJson(
            "/api/v1/component-standards/{$standard->id}/mappings",
            [
                'brand' => 'schneider',
                'product_id' => $product->id,
            ]
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['brand_sku']);
    });

    it('validates product_id must exist', function () {
        $standard = ComponentStandard::factory()->create();

        $response = $this->postJson(
            "/api/v1/component-standards/{$standard->id}/mappings",
            [
                'brand' => 'schneider',
                'product_id' => 99999,
            ]
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);
    });

    it('can update a brand mapping', function () {
        $standard = ComponentStandard::factory()->create();
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        $mapping = ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'schneider',
            'product_id' => $product1->id,
            'brand_sku' => 'OLD-SKU',
            'is_preferred' => false,
        ]);

        $response = $this->putJson(
            "/api/v1/component-standards/{$standard->id}/mappings/{$mapping->id}",
            [
                'product_id' => $product2->id,
                'brand_sku' => 'NEW-SKU',
                'price_factor' => 1.15,
                'notes' => 'Updated mapping info',
            ]
        );

        $response->assertOk()
            ->assertJsonPath('data.product_id', $product2->id)
            ->assertJsonPath('data.brand_sku', 'NEW-SKU');

        // Price factor is returned as numeric value
        expect((float) $response->json('data.price_factor'))->toBe(1.15);

        $this->assertDatabaseHas('component_brand_mappings', [
            'id' => $mapping->id,
            'brand_sku' => 'NEW-SKU',
        ]);
    });

    it('can set mapping as preferred during update', function () {
        $standard = ComponentStandard::factory()->create();
        $product = Product::factory()->create();

        $mapping = ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'schneider',
            'product_id' => $product->id,
            'is_preferred' => false,
        ]);

        $response = $this->putJson(
            "/api/v1/component-standards/{$standard->id}/mappings/{$mapping->id}",
            [
                'is_preferred' => true,
            ]
        );

        $response->assertOk()
            ->assertJsonPath('data.is_preferred', true);

        $this->assertDatabaseHas('component_brand_mappings', [
            'id' => $mapping->id,
            'is_preferred' => true,
        ]);
    });

    it('returns 404 when updating mapping for wrong standard', function () {
        $standard1 = ComponentStandard::factory()->create();
        $standard2 = ComponentStandard::factory()->create();
        $product = Product::factory()->create();

        $mapping = ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard1->id,
            'product_id' => $product->id,
        ]);

        $response = $this->putJson(
            "/api/v1/component-standards/{$standard2->id}/mappings/{$mapping->id}",
            ['brand_sku' => 'NEW-SKU']
        );

        $response->assertNotFound();
    });

    it('can delete a brand mapping', function () {
        $standard = ComponentStandard::factory()->create();
        $product = Product::factory()->create();

        $mapping = ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'product_id' => $product->id,
        ]);

        $response = $this->deleteJson(
            "/api/v1/component-standards/{$standard->id}/mappings/{$mapping->id}"
        );

        $response->assertOk()
            ->assertJsonPath('message', 'Brand mapping berhasil dihapus.');

        $this->assertDatabaseMissing('component_brand_mappings', ['id' => $mapping->id]);
    });

    it('returns 404 when deleting mapping for wrong standard', function () {
        $standard1 = ComponentStandard::factory()->create();
        $standard2 = ComponentStandard::factory()->create();
        $product = Product::factory()->create();

        $mapping = ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard1->id,
            'product_id' => $product->id,
        ]);

        $response = $this->deleteJson(
            "/api/v1/component-standards/{$standard2->id}/mappings/{$mapping->id}"
        );

        $response->assertNotFound();
    });

});

describe('Brand Mapping Verification', function () {

    it('can verify a brand mapping', function () {
        $standard = ComponentStandard::factory()->create();
        $product = Product::factory()->create();

        $mapping = ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'product_id' => $product->id,
            'is_verified' => false,
            'verified_by' => null,
            'verified_at' => null,
        ]);

        $user = auth()->user();

        $response = $this->postJson(
            "/api/v1/component-standards/{$standard->id}/mappings/{$mapping->id}/verify"
        );

        $response->assertOk()
            ->assertJsonPath('data.is_verified', true)
            ->assertJsonPath('data.verified_by', $user->id);

        $mapping->refresh();
        expect($mapping->is_verified)->toBeTrue();
        expect($mapping->verified_by)->toBe($user->id);
        expect($mapping->verified_at)->not->toBeNull();
    });

    it('returns 404 when verifying mapping for wrong standard', function () {
        $standard1 = ComponentStandard::factory()->create();
        $standard2 = ComponentStandard::factory()->create();
        $product = Product::factory()->create();

        $mapping = ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard1->id,
            'product_id' => $product->id,
        ]);

        $response = $this->postJson(
            "/api/v1/component-standards/{$standard2->id}/mappings/{$mapping->id}/verify"
        );

        $response->assertNotFound();
    });

});

describe('Setting Preferred Mapping', function () {

    it('can set a mapping as preferred', function () {
        $standard = ComponentStandard::factory()->create();
        $product = Product::factory()->create();

        $mapping = ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'schneider',
            'product_id' => $product->id,
            'is_preferred' => false,
        ]);

        $response = $this->postJson(
            "/api/v1/component-standards/{$standard->id}/mappings/{$mapping->id}/set-preferred"
        );

        $response->assertOk()
            ->assertJsonPath('data.is_preferred', true);

        $mapping->refresh();
        expect($mapping->is_preferred)->toBeTrue();
    });

    it('unsets other preferred mappings for same brand when setting new preferred', function () {
        $standard = ComponentStandard::factory()->create();
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        $mapping1 = ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'schneider',
            'product_id' => $product1->id,
            'is_preferred' => true,
        ]);

        $mapping2 = ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'schneider',
            'product_id' => $product2->id,
            'is_preferred' => false,
        ]);

        $response = $this->postJson(
            "/api/v1/component-standards/{$standard->id}/mappings/{$mapping2->id}/set-preferred"
        );

        $response->assertOk();

        $mapping1->refresh();
        $mapping2->refresh();

        expect($mapping2->is_preferred)->toBeTrue();
        expect($mapping1->is_preferred)->toBeFalse();
    });

    it('keeps other brand mappings preferred when setting different brand as preferred', function () {
        $standard = ComponentStandard::factory()->create();
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        $mapping1 = ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'schneider',
            'product_id' => $product1->id,
            'is_preferred' => true,
        ]);

        $mapping2 = ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'abb',
            'product_id' => $product2->id,
            'is_preferred' => false,
        ]);

        $response = $this->postJson(
            "/api/v1/component-standards/{$standard->id}/mappings/{$mapping2->id}/set-preferred"
        );

        $response->assertOk();

        $mapping1->refresh();
        $mapping2->refresh();

        expect($mapping1->is_preferred)->toBeTrue();
        expect($mapping2->is_preferred)->toBeTrue();
    });

    it('returns 404 when setting preferred for mapping of wrong standard', function () {
        $standard1 = ComponentStandard::factory()->create();
        $standard2 = ComponentStandard::factory()->create();
        $product = Product::factory()->create();

        $mapping = ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard1->id,
            'product_id' => $product->id,
        ]);

        $response = $this->postJson(
            "/api/v1/component-standards/{$standard2->id}/mappings/{$mapping->id}/set-preferred"
        );

        $response->assertNotFound();
    });

});

describe('Preferred and Verified Scopes', function () {

    it('can scope mappings by preferred status', function () {
        $standard = ComponentStandard::factory()->create();
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'schneider',
            'product_id' => $product1->id,
            'is_preferred' => true,
        ]);

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'abb',
            'product_id' => $product2->id,
            'is_preferred' => false,
        ]);

        $preferred = ComponentBrandMapping::preferred()->count();
        expect($preferred)->toBe(1);
    });

    it('can scope mappings by verified status', function () {
        $standard = ComponentStandard::factory()->create();
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'product_id' => $product1->id,
            'is_verified' => true,
        ]);

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'product_id' => $product2->id,
            'is_verified' => false,
        ]);

        $verified = ComponentBrandMapping::verified()->count();
        expect($verified)->toBe(1);
    });

    it('can scope mappings by brand', function () {
        $standard = ComponentStandard::factory()->create();
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'schneider',
            'product_id' => $product1->id,
        ]);

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'abb',
            'product_id' => $product2->id,
        ]);

        $schneider = ComponentBrandMapping::forBrand('schneider')->count();
        expect($schneider)->toBe(1);
    });

});

describe('Brand Mapping with Variant Specs', function () {

    it('can store variant specifications in mapping', function () {
        $standard = ComponentStandard::factory()->create();
        $product = Product::factory()->create();

        $response = $this->postJson(
            "/api/v1/component-standards/{$standard->id}/mappings",
            [
                'brand' => 'schneider',
                'product_id' => $product->id,
                'brand_sku' => 'A9F79110',
                'variant_specs' => [
                    'color' => 'white',
                    'mounting_type' => 'DIN',
                ],
            ]
        );

        $response->assertCreated();

        $variantSpecs = $response->json('data.variant_specs');
        expect($variantSpecs['color'])->toBe('white');
        expect($variantSpecs['mounting_type'])->toBe('DIN');
    });

    it('can include price factor in mapping', function () {
        $standard = ComponentStandard::factory()->create();
        $product = Product::factory()->create();

        $response = $this->postJson(
            "/api/v1/component-standards/{$standard->id}/mappings",
            [
                'brand' => 'schneider',
                'product_id' => $product->id,
                'brand_sku' => 'SKU-999',
                'price_factor' => 1.25,
            ]
        );

        $response->assertCreated();
        expect((float) $response->json('data.price_factor'))->toBe(1.25);
    });

});
