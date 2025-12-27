<?php

use App\Models\Accounting\Bom;
use App\Models\Accounting\BomItem;
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

describe('Component Standards CRUD', function () {

    it('can list all component standards', function () {
        ComponentStandard::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/component-standards');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    });

    it('can paginate component standards', function () {
        ComponentStandard::factory()->count(30)->create();

        $response = $this->getJson('/api/v1/component-standards?per_page=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 30)
            ->assertJsonPath('meta.per_page', 10);
    });

    it('can filter standards by category', function () {
        ComponentStandard::factory()->count(3)->create([
            'category' => ComponentStandard::CATEGORY_CIRCUIT_BREAKER,
        ]);
        ComponentStandard::factory()->count(2)->create([
            'category' => ComponentStandard::CATEGORY_RELAY,
        ]);

        $response = $this->getJson(
            '/api/v1/component-standards?category='.ComponentStandard::CATEGORY_CIRCUIT_BREAKER
        );

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter standards by subcategory', function () {
        ComponentStandard::factory()->count(2)->create([
            'category' => ComponentStandard::CATEGORY_CIRCUIT_BREAKER,
            'subcategory' => ComponentStandard::SUBCATEGORY_MCB,
        ]);
        ComponentStandard::factory()->count(2)->create([
            'category' => ComponentStandard::CATEGORY_CIRCUIT_BREAKER,
            'subcategory' => ComponentStandard::SUBCATEGORY_MCCB,
        ]);

        $response = $this->getJson(
            '/api/v1/component-standards?subcategory='.ComponentStandard::SUBCATEGORY_MCB
        );

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can filter standards by active status', function () {
        ComponentStandard::factory()->count(3)->create(['is_active' => true]);
        ComponentStandard::factory()->count(2)->create(['is_active' => false]);

        $response = $this->getJson('/api/v1/component-standards?is_active=true');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can search standards by code (case-insensitive)', function () {
        ComponentStandard::factory()->create(['code' => 'MCB-10A']);
        ComponentStandard::factory()->create(['code' => 'MCCB-20A']);

        $response = $this->getJson('/api/v1/component-standards?search=mcb');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'MCB-10A');
    });

    it('can search standards by name (case-insensitive)', function () {
        ComponentStandard::factory()->create(['name' => 'Miniature Circuit Breaker']);
        ComponentStandard::factory()->create(['name' => 'Molded Case Breaker']);

        $response = $this->getJson('/api/v1/component-standards?search=miniature');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Miniature Circuit Breaker');
    });

    it('can filter by specifications', function () {
        ComponentStandard::factory()->create([
            'code' => 'MCB-10A',
            'specifications' => ['rating_amps' => '10', 'poles' => '1'],
        ]);
        ComponentStandard::factory()->create([
            'code' => 'MCB-20A',
            'specifications' => ['rating_amps' => '20', 'poles' => '1'],
        ]);

        $response = $this->getJson('/api/v1/component-standards?specs[rating_amps]=10');

        $response->assertOk();

        // Verify at least one result matches
        $items = $response->json('data');
        expect($items)->not->toBeEmpty();
    });

    it('can filter by brand availability', function () {
        $standard1 = ComponentStandard::factory()->create();
        $standard2 = ComponentStandard::factory()->create();

        $product1 = Product::factory()->create(['brand' => 'Schneider']);
        $product2 = Product::factory()->create(['brand' => 'ABB']);
        $product3 = Product::factory()->create(['brand' => 'ABB']);

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard1->id,
            'brand' => 'schneider',
            'product_id' => $product1->id,
        ]);

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard2->id,
            'brand' => 'abb',
            'product_id' => $product3->id,
        ]);

        $response = $this->getJson('/api/v1/component-standards?brand=schneider');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can create a component standard', function () {
        $response = $this->postJson('/api/v1/component-standards', [
            'code' => 'MCB-10A-2P',
            'name' => 'Miniature Circuit Breaker 10A 2 Pole',
            'category' => ComponentStandard::CATEGORY_CIRCUIT_BREAKER,
            'subcategory' => ComponentStandard::SUBCATEGORY_MCB,
            'standard' => 'IEC 60898',
            'description' => 'Standard MCB for residential applications',
            'unit' => 'pcs',
            'is_active' => true,
            'specifications' => [
                'rating_amps' => 10,
                'poles' => 2,
                'breaking_capacity_ka' => 6,
                'curve' => 'C',
                'voltage' => '230/400V',
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'MCB-10A-2P')
            ->assertJsonPath('data.name', 'Miniature Circuit Breaker 10A 2 Pole')
            ->assertJsonPath('data.category', ComponentStandard::CATEGORY_CIRCUIT_BREAKER)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('component_standards', [
            'code' => 'MCB-10A-2P',
        ]);
    });

    it('validates required fields when creating standard', function () {
        $response = $this->postJson('/api/v1/component-standards', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'name', 'category', 'specifications']);
    });

    it('prevents duplicate component standard code', function () {
        ComponentStandard::factory()->create(['code' => 'MCB-10A']);

        $response = $this->postJson('/api/v1/component-standards', [
            'code' => 'MCB-10A',
            'name' => 'Duplicate Code',
            'category' => ComponentStandard::CATEGORY_CIRCUIT_BREAKER,
            'standard' => 'IEC 60898',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    });

    it('can show a component standard with mappings', function () {
        $standard = ComponentStandard::factory()->create();
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();
        $product3 = Product::factory()->create();

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
        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'siemens',
            'product_id' => $product3->id,
        ]);

        $response = $this->getJson("/api/v1/component-standards/{$standard->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $standard->id)
            ->assertJsonPath('data.code', $standard->code)
            ->assertJsonCount(3, 'data.brand_mappings');
    });

    it('returns 404 when showing non-existent standard', function () {
        $response = $this->getJson('/api/v1/component-standards/99999');

        $response->assertNotFound();
    });

    it('can update a component standard', function () {
        $standard = ComponentStandard::factory()->create();

        $response = $this->putJson("/api/v1/component-standards/{$standard->id}", [
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'is_active' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.description', 'Updated description')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('component_standards', [
            'id' => $standard->id,
            'name' => 'Updated Name',
            'is_active' => false,
        ]);
    });

    it('can update specifications array on component standard', function () {
        $standard = ComponentStandard::factory()->create([
            'specifications' => ['rating_amps' => 10, 'poles' => 1],
        ]);

        $response = $this->putJson("/api/v1/component-standards/{$standard->id}", [
            'specifications' => [
                'rating_amps' => 16,
                'poles' => 2,
                'breaking_capacity_ka' => 10,
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.specifications.rating_amps', 16)
            ->assertJsonPath('data.specifications.poles', 2)
            ->assertJsonPath('data.specifications.breaking_capacity_ka', 10);
    });

    it('can delete a component standard without BOM items', function () {
        $standard = ComponentStandard::factory()->create();

        $response = $this->deleteJson("/api/v1/component-standards/{$standard->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Component standard berhasil dihapus.');

        // Model uses SoftDeletes, so verify it's soft deleted
        expect(ComponentStandard::find($standard->id))->toBeNull();
        expect(ComponentStandard::withTrashed()->find($standard->id)->trashed())->toBeTrue();
    });

    it('prevents deletion of standard with BOM items', function () {
        $standard = ComponentStandard::factory()->create();
        $bom = Bom::factory()->create();

        BomItem::factory()->create([
            'bom_id' => $bom->id,
            'component_standard_id' => $standard->id,
        ]);

        $response = $this->deleteJson("/api/v1/component-standards/{$standard->id}");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Tidak dapat dihapus: 1 item BOM menggunakan komponen ini.');

        $this->assertDatabaseHas('component_standards', ['id' => $standard->id]);
    });

    it('prevents deletion of standard with multiple BOM items', function () {
        $standard = ComponentStandard::factory()->create();
        $bom1 = Bom::factory()->create();
        $bom2 = Bom::factory()->create();

        BomItem::factory()->create([
            'bom_id' => $bom1->id,
            'component_standard_id' => $standard->id,
        ]);
        BomItem::factory()->create([
            'bom_id' => $bom2->id,
            'component_standard_id' => $standard->id,
        ]);

        $response = $this->deleteJson("/api/v1/component-standards/{$standard->id}");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Tidak dapat dihapus: 2 item BOM menggunakan komponen ini.');
    });

});

describe('Component Standard Categories', function () {

    it('can get categories with counts', function () {
        ComponentStandard::factory()->count(3)->create(['category' => ComponentStandard::CATEGORY_CIRCUIT_BREAKER]);
        ComponentStandard::factory()->count(2)->create(['category' => ComponentStandard::CATEGORY_RELAY]);
        ComponentStandard::factory()->count(1)->create(['category' => ComponentStandard::CATEGORY_CABLE, 'is_active' => false]);

        $response = $this->getJson('/api/v1/component-standards/categories');

        $response->assertOk();

        $categories = $response->json('data');
        $cbCategory = collect($categories)->firstWhere('category', ComponentStandard::CATEGORY_CIRCUIT_BREAKER);
        $relayCategory = collect($categories)->firstWhere('category', ComponentStandard::CATEGORY_RELAY);

        expect($cbCategory['count'])->toBe(3);
        expect($relayCategory['count'])->toBe(2);
    });

    it('only returns active standards in category counts', function () {
        ComponentStandard::factory()->count(2)->create([
            'category' => ComponentStandard::CATEGORY_CIRCUIT_BREAKER,
            'is_active' => true,
        ]);
        ComponentStandard::factory()->create([
            'category' => ComponentStandard::CATEGORY_CIRCUIT_BREAKER,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/component-standards/categories');

        $categories = $response->json('data');
        $cbCategory = collect($categories)->firstWhere('category', ComponentStandard::CATEGORY_CIRCUIT_BREAKER);

        expect($cbCategory['count'])->toBe(2);
    });

    it('includes category labels in response', function () {
        ComponentStandard::factory()->create(['category' => ComponentStandard::CATEGORY_CIRCUIT_BREAKER]);

        $response = $this->getJson('/api/v1/component-standards/categories');

        $categories = $response->json('data');
        $cbCategory = collect($categories)->firstWhere('category', ComponentStandard::CATEGORY_CIRCUIT_BREAKER);

        expect($cbCategory['label'])->toBe('Circuit Breaker');
    });

});

describe('Component Standard Brands', function () {

    it('can get available brands for a standard', function () {
        $standard = ComponentStandard::factory()->create();
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();
        $product3 = Product::factory()->create();

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
        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'siemens',
            'product_id' => $product3->id,
        ]);

        $response = $this->getJson("/api/v1/component-standards/{$standard->id}/brands");

        $response->assertOk();

        $brands = $response->json('data');
        expect($brands)->toHaveCount(3);
        expect(collect($brands)->pluck('code'))->toContain('schneider', 'abb', 'siemens');
    });

    it('includes brand names in response', function () {
        $standard = ComponentStandard::factory()->create();
        $product = Product::factory()->create();

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'schneider',
            'product_id' => $product->id,
        ]);

        $response = $this->getJson("/api/v1/component-standards/{$standard->id}/brands");

        $brands = $response->json('data');
        $schneiderBrand = collect($brands)->firstWhere('code', 'schneider');

        expect($schneiderBrand['name'])->toBe('Schneider Electric');
    });

    it('only returns distinct brands', function () {
        $standard = ComponentStandard::factory()->create();
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        // Create multiple mappings with same brand
        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'schneider',
            'product_id' => $product1->id,
        ]);
        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'schneider',
            'product_id' => $product2->id,
        ]);

        $response = $this->getJson("/api/v1/component-standards/{$standard->id}/brands");

        $brands = $response->json('data');
        expect($brands)->toHaveCount(1);
    });

});
