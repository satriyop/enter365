<?php

use App\Models\Accounting\Bom;
use App\Models\Accounting\BomItem;
use App\Models\Accounting\BomVariantGroup;
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

describe('BOM Brand Swap - Core Feature', function () {

    it('can swap BOM to different brand with complete mappings', function () {
        // Setup: Create a BOM with products mapped to component standards
        $bom = Bom::factory()->create();

        // Create component standards
        $standard1 = ComponentStandard::factory()->create(['code' => 'MCB-10A']);
        $standard2 = ComponentStandard::factory()->create(['code' => 'MCCB-20A']);

        // Create products for original brand (Schneider)
        $schneiderProduct1 = Product::factory()->create(['name' => 'Schneider MCB 10A', 'brand' => 'Schneider']);
        $schneiderProduct2 = Product::factory()->create(['name' => 'Schneider MCCB 20A', 'brand' => 'Schneider']);

        // Create products for target brand (ABB)
        $abbProduct1 = Product::factory()->create(['name' => 'ABB MCB 10A', 'brand' => 'ABB', 'purchase_price' => 150000]);
        $abbProduct2 = Product::factory()->create(['name' => 'ABB MCCB 20A', 'brand' => 'ABB', 'purchase_price' => 250000]);

        // Map Schneider products to standards
        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard1->id,
            'brand' => 'schneider',
            'product_id' => $schneiderProduct1->id,
        ]);

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard2->id,
            'brand' => 'schneider',
            'product_id' => $schneiderProduct2->id,
        ]);

        // Map ABB products to standards (preferred)
        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard1->id,
            'brand' => 'abb',
            'product_id' => $abbProduct1->id,
            'is_preferred' => true,
        ]);

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard2->id,
            'brand' => 'abb',
            'product_id' => $abbProduct2->id,
            'is_preferred' => true,
        ]);

        // Create BOM items
        BomItem::factory()->create([
            'bom_id' => $bom->id,
            'product_id' => $schneiderProduct1->id,
            'component_standard_id' => $standard1->id,
            'description' => 'Schneider MCB 10A',
            'quantity' => 2,
            'unit_cost' => 100000,
        ]);

        BomItem::factory()->create([
            'bom_id' => $bom->id,
            'product_id' => $schneiderProduct2->id,
            'component_standard_id' => $standard2->id,
            'description' => 'Schneider MCCB 20A',
            'quantity' => 1,
            'unit_cost' => 200000,
        ]);

        // Perform swap
        $response = $this->postJson("/api/v1/boms/{$bom->id}/swap-brand", [
            'target_brand' => 'abb',
            'create_variant' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Brand swap berhasil.')
            ->assertJsonPath('data.swap_report.total_items', 2)
            ->assertJsonPath('data.swap_report.swapped', 2);

        // Verify new BOM was created
        $newBomId = $response->json('data.bom.id');
        expect($newBomId)->not->toBe($bom->id);

        // Verify new BOM has correct items
        $newBom = Bom::find($newBomId);
        expect($newBom->materialItems()->count())->toBe(2);

        // Verify products were swapped
        $newItem1 = $newBom->materialItems()->first();
        expect($newItem1->product_id)->toBe($abbProduct1->id);
        expect($newItem1->unit_cost)->toBe($abbProduct1->purchase_price);
    });

    it('can swap BOM without creating variant', function () {
        $bom = Bom::factory()->create();

        $standard = ComponentStandard::factory()->create();
        $schneiderProduct = Product::factory()->create(['brand' => 'Schneider']);
        $abbProduct = Product::factory()->create(['brand' => 'ABB', 'purchase_price' => 150000]);

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'schneider',
            'product_id' => $schneiderProduct->id,
        ]);

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'abb',
            'product_id' => $abbProduct->id,
            'is_preferred' => true,
        ]);

        BomItem::factory()->create([
            'bom_id' => $bom->id,
            'product_id' => $schneiderProduct->id,
            'component_standard_id' => $standard->id,
        ]);

        $response = $this->postJson("/api/v1/boms/{$bom->id}/swap-brand", [
            'target_brand' => 'abb',
            'create_variant' => false,
        ]);

        $response->assertCreated();

        // Verify original BOM was modified in-place
        $returnedBomId = $response->json('data.bom.id');
        expect($returnedBomId)->toBe($bom->id);

        $bom->refresh();
        $item = $bom->materialItems()->first();
        expect($item->product_id)->toBe($abbProduct->id);
    });

    it('returns partial swap report when some items lack mappings', function () {
        $bom = Bom::factory()->create();

        $standard = ComponentStandard::factory()->create();
        $schneiderProduct = Product::factory()->create(['brand' => 'Schneider']);
        $unmappedProduct = Product::factory()->create(['brand' => 'Generic']);

        // Only map Schneider product to standard
        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'schneider',
            'product_id' => $schneiderProduct->id,
        ]);

        $abbProduct = Product::factory()->create(['brand' => 'ABB', 'purchase_price' => 150000]);
        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'abb',
            'product_id' => $abbProduct->id,
            'is_preferred' => true,
        ]);

        // Create items - one mappable, one not
        BomItem::factory()->create([
            'bom_id' => $bom->id,
            'product_id' => $schneiderProduct->id,
            'component_standard_id' => $standard->id,
        ]);

        BomItem::factory()->create([
            'bom_id' => $bom->id,
            'product_id' => $unmappedProduct->id,
            'component_standard_id' => null,
        ]);

        $response = $this->postJson("/api/v1/boms/{$bom->id}/swap-brand", [
            'target_brand' => 'abb',
            'create_variant' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.swap_report.total_items', 2)
            ->assertJsonPath('data.swap_report.swapped', 1)
            ->assertJsonPath('data.swap_report.no_mapping', 1);
    });

    it('keeps original items when no mappings exist for target brand', function () {
        $bom = Bom::factory()->create();

        $standard = ComponentStandard::factory()->create();
        $schneiderProduct = Product::factory()->create(['brand' => 'Schneider']);

        // Only map to Schneider, no ABB mapping
        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'schneider',
            'product_id' => $schneiderProduct->id,
        ]);

        BomItem::factory()->create([
            'bom_id' => $bom->id,
            'product_id' => $schneiderProduct->id,
            'component_standard_id' => $standard->id,
        ]);

        $response = $this->postJson("/api/v1/boms/{$bom->id}/swap-brand", [
            'target_brand' => 'abb',
            'create_variant' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.swap_report.no_mapping', 1);

        // Verify item was kept as original
        $newBomId = $response->json('data.bom.id');
        $newBom = Bom::find($newBomId);
        $item = $newBom->materialItems()->first();

        expect($item->product_id)->toBe($schneiderProduct->id);
    });

    it('prefers marked mappings over other options', function () {
        $bom = Bom::factory()->create();

        $standard = ComponentStandard::factory()->create();
        $schneiderProduct = Product::factory()->create(['brand' => 'Schneider']);

        // Map Schneider
        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'schneider',
            'product_id' => $schneiderProduct->id,
        ]);

        // Create multiple ABB options
        $abbProduct1 = Product::factory()->create(['name' => 'ABB Option 1', 'brand' => 'ABB', 'purchase_price' => 100000]);
        $abbProduct2 = Product::factory()->create(['name' => 'ABB Option 2 (Preferred)', 'brand' => 'ABB', 'purchase_price' => 150000]);

        // Map both, but mark one as preferred
        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'abb',
            'product_id' => $abbProduct1->id,
            'is_preferred' => false,
        ]);

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'abb',
            'product_id' => $abbProduct2->id,
            'is_preferred' => true,
        ]);

        BomItem::factory()->create([
            'bom_id' => $bom->id,
            'product_id' => $schneiderProduct->id,
            'component_standard_id' => $standard->id,
        ]);

        $response = $this->postJson("/api/v1/boms/{$bom->id}/swap-brand", [
            'target_brand' => 'abb',
            'create_variant' => true,
        ]);

        // Verify preferred option was selected
        $newBomId = $response->json('data.bom.id');
        $newBom = Bom::find($newBomId);
        $item = $newBom->materialItems()->first();

        expect($item->product_id)->toBe($abbProduct2->id);
    });

    it('preserves labor and overhead items during swap', function () {
        $bom = Bom::factory()->create();

        $standard = ComponentStandard::factory()->create();
        $schneiderProduct = Product::factory()->create(['brand' => 'Schneider']);
        $abbProduct = Product::factory()->create(['brand' => 'ABB', 'purchase_price' => 150000]);

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'schneider',
            'product_id' => $schneiderProduct->id,
        ]);

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'abb',
            'product_id' => $abbProduct->id,
            'is_preferred' => true,
        ]);

        // Material item
        BomItem::factory()->material()->create([
            'bom_id' => $bom->id,
            'product_id' => $schneiderProduct->id,
            'component_standard_id' => $standard->id,
        ]);

        // Labor item
        BomItem::factory()->labor()->create([
            'bom_id' => $bom->id,
            'description' => 'Assembly labor',
            'quantity' => 4,
            'unit_cost' => 50000,
        ]);

        // Overhead item
        BomItem::factory()->overhead()->create([
            'bom_id' => $bom->id,
            'description' => 'Factory overhead',
            'quantity' => 1,
            'unit_cost' => 100000,
        ]);

        $response = $this->postJson("/api/v1/boms/{$bom->id}/swap-brand", [
            'target_brand' => 'abb',
            'create_variant' => true,
        ]);

        $newBomId = $response->json('data.bom.id');
        $newBom = Bom::find($newBomId);

        // Verify all item types were preserved
        expect($newBom->materialItems()->count())->toBe(1);
        expect($newBom->laborItems()->count())->toBe(1);
        expect($newBom->overheadItems()->count())->toBe(1);

        $laborItem = $newBom->laborItems()->first();
        expect($laborItem->description)->toBe('Assembly labor');
    });

    it('validates target_brand is required', function () {
        $bom = Bom::factory()->create();

        $response = $this->postJson("/api/v1/boms/{$bom->id}/swap-brand", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['target_brand']);
    });

});

describe('BOM Brand Variants Generation', function () {

    it('can generate brand variants for a BOM', function () {
        $bom = Bom::factory()->create();

        $standard = ComponentStandard::factory()->create();
        $schneiderProduct = Product::factory()->create(['brand' => 'Schneider', 'purchase_price' => 100000]);

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'schneider',
            'product_id' => $schneiderProduct->id,
        ]);

        // Create ABB mapping
        $abbProduct = Product::factory()->create(['brand' => 'ABB', 'purchase_price' => 150000]);
        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'abb',
            'product_id' => $abbProduct->id,
            'is_preferred' => true,
        ]);

        // Create Siemens mapping
        $siemensProduct = Product::factory()->create(['brand' => 'Siemens', 'purchase_price' => 120000]);
        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'siemens',
            'product_id' => $siemensProduct->id,
            'is_preferred' => true,
        ]);

        BomItem::factory()->create([
            'bom_id' => $bom->id,
            'product_id' => $schneiderProduct->id,
            'component_standard_id' => $standard->id,
        ]);

        $response = $this->postJson("/api/v1/boms/{$bom->id}/generate-brand-variants", [
            'brands' => ['abb', 'siemens'],
            'group_name' => 'MCB Supplier Variants',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Brand variants berhasil dibuat.')
            ->assertJsonPath('data.variant_group.name', 'MCB Supplier Variants');

        // Verify variant group exists
        $groupId = $response->json('data.variant_group.id');
        $group = BomVariantGroup::find($groupId);
        expect($group)->not->toBeNull();
        expect($group->name)->toBe('MCB Supplier Variants');

        // Verify original BOM is in group as primary
        $boms = $response->json('data.boms');
        expect($boms)->toHaveCount(2); // 2 new variants generated
    });

    it('generates default group name from product when not provided', function () {
        $product = Product::factory()->create(['name' => 'Power Supply Unit']);
        $bom = Bom::factory()->forProduct($product)->create();

        $standard = ComponentStandard::factory()->create();
        $sourceProduct = Product::factory()->create(['brand' => 'Schneider']);

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'schneider',
            'product_id' => $sourceProduct->id,
        ]);

        $abbProduct = Product::factory()->create(['brand' => 'ABB']);
        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'abb',
            'product_id' => $abbProduct->id,
            'is_preferred' => true,
        ]);

        BomItem::factory()->create([
            'bom_id' => $bom->id,
            'product_id' => $sourceProduct->id,
            'component_standard_id' => $standard->id,
        ]);

        $response = $this->postJson("/api/v1/boms/{$bom->id}/generate-brand-variants", [
            'brands' => ['abb'],
        ]);

        $response->assertCreated();

        $groupName = $response->json('data.variant_group.name');
        expect($groupName)->toContain('Power Supply Unit');
    });

    it('validates brands array is required and not empty', function () {
        $bom = Bom::factory()->create();

        $response = $this->postJson("/api/v1/boms/{$bom->id}/generate-brand-variants", [
            'brands' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['brands']);
    });

    it('can optionally add brand variants to existing variant group', function () {
        $bom = Bom::factory()->create();
        $variantGroup = BomVariantGroup::factory()->create();

        $standard = ComponentStandard::factory()->create();
        $sourceProduct = Product::factory()->create(['brand' => 'Schneider']);

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'schneider',
            'product_id' => $sourceProduct->id,
        ]);

        $abbProduct = Product::factory()->create(['brand' => 'ABB']);
        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'abb',
            'product_id' => $abbProduct->id,
            'is_preferred' => true,
        ]);

        BomItem::factory()->create([
            'bom_id' => $bom->id,
            'product_id' => $sourceProduct->id,
            'component_standard_id' => $standard->id,
        ]);

        $response = $this->postJson("/api/v1/boms/{$bom->id}/generate-brand-variants", [
            'brands' => ['abb'],
            'variant_group_id' => $variantGroup->id,
        ]);

        $response->assertCreated();
    });

    it('includes detailed swap report for each brand variant', function () {
        $bom = Bom::factory()->create();

        $standard = ComponentStandard::factory()->create();
        $sourceProduct = Product::factory()->create(['brand' => 'Schneider']);

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'schneider',
            'product_id' => $sourceProduct->id,
        ]);

        // Create mappings for two brands
        $abbProduct = Product::factory()->create(['brand' => 'ABB']);
        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'abb',
            'product_id' => $abbProduct->id,
            'is_preferred' => true,
        ]);

        $siemensProduct = Product::factory()->create(['brand' => 'Siemens']);
        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'siemens',
            'product_id' => $siemensProduct->id,
            'is_preferred' => true,
        ]);

        BomItem::factory()->create([
            'bom_id' => $bom->id,
            'product_id' => $sourceProduct->id,
            'component_standard_id' => $standard->id,
        ]);

        $response = $this->postJson("/api/v1/boms/{$bom->id}/generate-brand-variants", [
            'brands' => ['abb', 'siemens'],
        ]);

        $response->assertCreated();

        $report = $response->json('data.report');
        expect($report)->toHaveKey('abb');
        expect($report)->toHaveKey('siemens');
        expect($report['abb']['swapped'])->toBe(1);
        expect($report['siemens']['swapped'])->toBe(1);
    });

});

describe('Cross-Reference Query Endpoints', function () {

    it('can find equivalent products for a product', function () {
        $standard = ComponentStandard::factory()->create();

        $schneiderProduct = Product::factory()->create(['name' => 'Schneider MCB', 'brand' => 'Schneider']);
        $abbProduct = Product::factory()->create(['name' => 'ABB MCB', 'brand' => 'ABB']);
        $siemensProduct = Product::factory()->create(['name' => 'Siemens MCB', 'brand' => 'Siemens']);

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'schneider',
            'product_id' => $schneiderProduct->id,
        ]);

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'abb',
            'product_id' => $abbProduct->id,
        ]);

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'siemens',
            'product_id' => $siemensProduct->id,
        ]);

        $response = $this->getJson("/api/v1/products/{$schneiderProduct->id}/equivalents");

        $response->assertOk()
            ->assertJsonPath('source_product.id', $schneiderProduct->id);

        $equivalents = $response->json('data');
        expect($equivalents)->toHaveCount(2);
    });

    it('can filter equivalents by target brand', function () {
        $standard = ComponentStandard::factory()->create();

        $schneiderProduct = Product::factory()->create(['brand' => 'Schneider']);
        $abbProduct = Product::factory()->create(['brand' => 'ABB']);
        $siemensProduct = Product::factory()->create(['brand' => 'Siemens']);

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'schneider',
            'product_id' => $schneiderProduct->id,
        ]);

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'abb',
            'product_id' => $abbProduct->id,
        ]);

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'siemens',
            'product_id' => $siemensProduct->id,
        ]);

        $response = $this->getJson("/api/v1/products/{$schneiderProduct->id}/equivalents?brand=abb");

        $response->assertOk();

        $equivalents = $response->json('data');
        expect($equivalents)->toHaveCount(1);
        expect($equivalents[0]['brand'])->toBe('abb');
    });

    it('returns empty when product has no mappings', function () {
        $product = Product::factory()->create();

        $response = $this->getJson("/api/v1/products/{$product->id}/equivalents");

        $response->assertOk()
            ->assertJsonPath('data', []);
    });

});

describe('Component Search by Specifications', function () {

    it('can search components by category and specifications', function () {
        ComponentStandard::factory()->create([
            'category' => ComponentStandard::CATEGORY_CIRCUIT_BREAKER,
            'specifications' => ['rating_amps' => '10', 'poles' => '1'],
        ]);

        ComponentStandard::factory()->create([
            'category' => ComponentStandard::CATEGORY_CIRCUIT_BREAKER,
            'specifications' => ['rating_amps' => '20', 'poles' => '1'],
        ]);

        $response = $this->getJson(
            '/api/v1/component-search?category='.ComponentStandard::CATEGORY_CIRCUIT_BREAKER.'&specs[rating_amps]=10'
        );

        $response->assertOk();

        $results = $response->json('data');
        expect($results)->not->toBeEmpty();
    });

    it('can search by brand within category', function () {
        $standard = ComponentStandard::factory()->create([
            'category' => ComponentStandard::CATEGORY_CIRCUIT_BREAKER,
        ]);

        $product = Product::factory()->create();

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'schneider',
            'product_id' => $product->id,
        ]);

        $response = $this->getJson(
            '/api/v1/component-search?category='.ComponentStandard::CATEGORY_CIRCUIT_BREAKER.'&brand=schneider'
        );

        $response->assertOk();

        $results = $response->json('data');
        expect($results)->not->toBeEmpty();
    });

});

describe('Available Brands Endpoint', function () {

    it('can list all available brands from mappings', function () {
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

        $response = $this->getJson('/api/v1/available-brands');

        $response->assertOk();

        $brands = $response->json('data');
        $codes = collect($brands)->pluck('code');

        expect($codes)->toContain('schneider', 'abb');
    });

});
