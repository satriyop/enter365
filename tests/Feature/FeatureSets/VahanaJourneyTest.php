<?php

declare(strict_types=1);

/**
 * Defining journeys for FEATURE_PRESET=vahana (electrical_panel add-on).
 */

use App\Models\ElectricalPanel\ComponentBrandMapping;
use App\Models\ElectricalPanel\ComponentStandard;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\BomTemplateItem;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Addons\ElectricalPanelHelpers;

uses(RefreshDatabase::class);

describe('vahana feature set journeys', function () {
    beforeEach(function () {
        applyFeaturePreset('vahana');
        seedDemoFoundation($this);
        seedDemoProfile($this, DemoSeeder::DEMO_VAHANA);
        authenticatedAdmin();
    });

    it('seeds component library and serves electrical_panel APIs', function () {
        expect(ComponentStandard::query()->count())->toBeGreaterThan(0)
            ->and(Bom::query()->count())->toBeGreaterThan(0);

        $this->getJson('/api/v1/component-standards')->assertOk();
        $this->getJson('/api/v1/available-brands')->assertOk();
        $this->getJson('/api/v1/spec-rule-sets')->assertOk();
        $this->getJson('/api/v1/boms')->assertOk();

        // Solar stays off for vahana preset
        $this->getJson('/api/v1/solar-proposals')->assertNotFound();
    });

    it('can create a component standard and brand mapping via API', function () {
        $product = Product::factory()->create([
            'sku' => 'VAH-JOURNEY-MCB',
            'brand' => 'Schneider',
            'name' => 'Journey MCB 16A',
        ]);

        $create = $this->postJson('/api/v1/component-standards', [
            'code' => 'JOURNEY-MCB-16A',
            'name' => 'Journey MCB 16A Standard',
            'category' => ComponentStandard::CATEGORY_CIRCUIT_BREAKER,
            'is_active' => true,
            'specifications' => [
                'poles' => 1,
                'current_rating' => 16,
                'breaking_capacity' => 6,
            ],
        ]);

        $create->assertSuccessful();
        $standardId = $create->json('data.id') ?? ComponentStandard::where('code', 'JOURNEY-MCB-16A')->value('id');
        expect($standardId)->not->toBeNull();

        $mapping = $this->postJson("/api/v1/component-standards/{$standardId}/mappings", [
            'product_id' => $product->id,
            'brand' => 'schneider',
            'brand_sku' => $product->sku,
            'is_preferred' => true,
        ]);

        $mapping->assertSuccessful();
        expect(
            ComponentBrandMapping::query()
                ->where('component_standard_id', $standardId)
                ->where('product_id', $product->id)
                ->exists()
        )->toBeTrue();
    });

    it('exposes template available-brands when standards are attached', function () {
        $template = BomTemplate::factory()->create(['code' => 'TPL-VAH-JRN', 'is_active' => true]);
        $standard = ComponentStandard::query()->first() ?? ComponentStandard::factory()->create();
        $item = BomTemplateItem::factory()->create([
            'template_id' => $template->id,
            'product_id' => null,
            'description' => 'Panel line',
        ]);
        ElectricalPanelHelpers::attachTemplateItemStandard($item, $standard);

        $product = Product::factory()->create(['brand' => 'ABB']);
        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'product_id' => $product->id,
            'brand' => 'abb',
        ]);

        $this->getJson("/api/v1/bom-templates/{$template->id}/available-brands")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['code', 'name', 'coverage', 'coverage_percent'],
                ],
            ]);
    });

    it('can preview brand swap on a BOM with mapped standards', function () {
        $standard = ComponentStandard::query()->first() ?? ComponentStandard::factory()->create();
        $productA = Product::factory()->create(['brand' => 'Schneider', 'purchase_price' => 100000]);
        $productB = Product::factory()->create(['brand' => 'ABB', 'purchase_price' => 90000]);

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'product_id' => $productA->id,
            'brand' => 'schneider',
            'is_preferred' => true,
        ]);
        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'product_id' => $productB->id,
            'brand' => 'abb',
        ]);

        $bom = Bom::factory()->create(['status' => 'draft']);
        $item = BomItem::factory()->for($bom)->create([
            'product_id' => $productA->id,
            'quantity' => 2,
        ]);
        ElectricalPanelHelpers::attachBomItemStandard($item, $standard);

        $response = $this->postJson("/api/v1/boms/{$bom->id}/swap-brand-preview", [
            'target_brand' => 'abb',
        ]);

        $response->assertSuccessful();
    });
});
