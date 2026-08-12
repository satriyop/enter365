<?php

declare(strict_types=1);

use App\Models\ElectricalPanel\ComponentBrandMapping;
use App\Models\ElectricalPanel\ComponentStandard;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\BomTemplateItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Addons\ElectricalPanelHelpers;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    withFeatures([
        'bom' => true,
        'electrical_panel' => true,
    ]);
});

describe('BOM Template electrical_panel add-on', function () {

    it('can add item with component standard', function () {
        $template = BomTemplate::factory()->create();
        $standard = ComponentStandard::factory()->create();

        $response = $this->postJson("/api/v1/bom-templates/{$template->id}/items", [
            'type' => BomTemplateItem::TYPE_MATERIAL,
            'component_standard_id' => $standard->id,
            'description' => 'MCB 16A',
            'default_quantity' => 1,
            'unit' => 'pcs',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.component_standard_id', $standard->id)
            ->assertJsonPath('data.has_component_standard', true);
    });

    it('duplicates panel meta component standards on template items', function () {
        $template = BomTemplate::factory()->create();
        $standard = ComponentStandard::factory()->create();
        $item = BomTemplateItem::factory()->create([
            'template_id' => $template->id,
            'description' => 'MCB 16A line',
        ]);
        ElectricalPanelHelpers::attachTemplateItemStandard($item, $standard);

        $response = $this->postJson("/api/v1/bom-templates/{$template->id}/duplicate", [
            'code' => 'TPL-COPY-META',
        ]);

        $response->assertCreated()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.component_standard_id', $standard->id)
            ->assertJsonPath('data.items.0.has_component_standard', true);

        $copyId = $response->json('data.id');
        $this->assertDatabaseHas('electrical_panel_bom_template_item_meta', [
            'component_standard_id' => $standard->id,
        ]);
        expect(
            \App\Models\ElectricalPanel\BomTemplateItemPanelMeta::query()
                ->where('component_standard_id', $standard->id)
                ->count()
        )->toBe(2);
        expect(BomTemplate::find($copyId))->not->toBeNull();
    });

    it('can get available brands for a template', function () {
        $template = BomTemplate::factory()->create();
        $standard1 = ComponentStandard::factory()->create();
        $standard2 = ComponentStandard::factory()->create();

        $__item = BomTemplateItem::factory()->create([
            'template_id' => $template->id,
        ]);
        ElectricalPanelHelpers::attachTemplateItemStandard($__item, $standard1);
        $__item = BomTemplateItem::factory()->create([
            'template_id' => $template->id,
        ]);
        ElectricalPanelHelpers::attachTemplateItemStandard($__item, $standard2);

        $product1 = Product::factory()->create(['brand' => 'Schneider']);
        $product2 = Product::factory()->create(['brand' => 'ABB']);
        $product3 = Product::factory()->create(['brand' => 'ABB']);

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard1->id,
            'brand' => 'schneider',
            'product_id' => $product1->id,
        ]);
        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard1->id,
            'brand' => 'abb',
            'product_id' => $product2->id,
        ]);
        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard2->id,
            'brand' => 'abb',
            'product_id' => $product3->id,
        ]);

        $response = $this->getJson("/api/v1/bom-templates/{$template->id}/available-brands");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['code', 'name', 'coverage', 'coverage_percent'],
                ],
                'meta' => ['template_id', 'template_code', 'items_with_standard'],
            ]);

        $brands = $response->json('data');
        $abbBrand = collect($brands)->firstWhere('code', 'abb');
        $schneiderBrand = collect($brands)->firstWhere('code', 'schneider');

        expect($abbBrand['coverage'])->toBe(2);
        expect($schneiderBrand['coverage'])->toBe(1);
    });

    it('can preview creating a BOM from template with target brand', function () {
        $template = BomTemplate::factory()->create();
        $standard = ComponentStandard::factory()->create();
        $product = Product::factory()->create(['brand' => 'Schneider', 'purchase_price' => 100000]);

        $__item = BomTemplateItem::factory()->create([
            'template_id' => $template->id,
            'description' => 'MCB 16A',
            'default_quantity' => 5,
        ]);
        ElectricalPanelHelpers::attachTemplateItemStandard($__item, $standard);

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'schneider',
            'product_id' => $product->id,
        ]);

        $response = $this->postJson("/api/v1/bom-templates/{$template->id}/preview-bom", [
            'target_brand' => 'schneider',
        ]);

        $response->assertOk();
        expect($response->json('report.resolved'))->toBe(1);
        expect($response->json('data.0.product.id'))->toBe($product->id);
    });

    it('can create a BOM from template with target brand', function () {
        $template = BomTemplate::factory()->create();
        $standard = ComponentStandard::factory()->create();
        $outputProduct = Product::factory()->create();
        $componentProduct = Product::factory()->create([
            'brand' => 'Schneider',
            'purchase_price' => 100000,
        ]);

        $__item = BomTemplateItem::factory()->create([
            'template_id' => $template->id,
            'description' => 'MCB 16A',
            'default_quantity' => 5,
            'unit' => 'pcs',
        ]);
        ElectricalPanelHelpers::attachTemplateItemStandard($__item, $standard);

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'schneider',
            'product_id' => $componentProduct->id,
        ]);

        $response = $this->postJson("/api/v1/bom-templates/{$template->id}/create-bom", [
            'product_id' => $outputProduct->id,
            'target_brand' => 'schneider',
            'name' => 'Test BOM',
            'output_quantity' => 1,
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'BOM berhasil dibuat dari template.');

        $bomId = $response->json('data.id');
        $this->assertDatabaseHas('boms', ['id' => $bomId]);
        $this->assertDatabaseHas('bom_items', [
            'bom_id' => $bomId,
            'product_id' => $componentProduct->id,
        ]);
        expect($template->fresh()->usage_count)->toBe(1);
    });

    it('creates BOM with items that have no brand mapping', function () {
        $template = BomTemplate::factory()->create();
        $standard = ComponentStandard::factory()->create();
        $outputProduct = Product::factory()->create();

        $__item = BomTemplateItem::factory()->create([
            'template_id' => $template->id,
            'description' => 'Custom Item',
            'default_quantity' => 1,
        ]);
        ElectricalPanelHelpers::attachTemplateItemStandard($__item, $standard);

        $response = $this->postJson("/api/v1/bom-templates/{$template->id}/create-bom", [
            'product_id' => $outputProduct->id,
            'target_brand' => 'schneider',
        ]);

        $response->assertCreated()
            ->assertJsonPath('report.no_mapping', 1);

        $bomId = $response->json('data.id');
        $this->assertDatabaseHas('bom_items', [
            'bom_id' => $bomId,
            'product_id' => null,
        ]);
    });

    it('uses preferred brand mapping when no target brand specified', function () {
        $template = BomTemplate::factory()->create();
        $standard = ComponentStandard::factory()->create();
        $outputProduct = Product::factory()->create();
        $preferredProduct = Product::factory()->create(['purchase_price' => 150000]);
        $otherProduct = Product::factory()->create(['purchase_price' => 100000]);

        $__item = BomTemplateItem::factory()->create([
            'template_id' => $template->id,
            'default_quantity' => 1,
        ]);
        ElectricalPanelHelpers::attachTemplateItemStandard($__item, $standard);

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'abb',
            'product_id' => $otherProduct->id,
            'is_preferred' => false,
        ]);
        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'schneider',
            'product_id' => $preferredProduct->id,
            'is_preferred' => true,
        ]);

        $response = $this->postJson("/api/v1/bom-templates/{$template->id}/create-bom", [
            'product_id' => $outputProduct->id,
        ]);

        $response->assertCreated();
        $bomId = $response->json('data.id');
        $this->assertDatabaseHas('bom_items', [
            'bom_id' => $bomId,
            'product_id' => $preferredProduct->id,
        ]);
    });
});
