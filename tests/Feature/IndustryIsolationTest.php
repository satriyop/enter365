<?php

declare(strict_types=1);

use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\BomTemplateItem;
use App\Models\Manufacturing\ComponentStandard;
use App\Models\Solar\IndonesiaSolarData;
use App\Models\Solar\PlnTariff;
use App\Services\Manufacturing\BomTemplateService;
use App\Services\Manufacturing\SpecValidationService;
use App\Support\Features;
use Database\Seeders\Demo\MasterDataSeeder;
use Database\Seeders\IndonesiaSolarDataSeeder;
use Database\Seeders\PlnTariffSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
});

describe('SpecValidation soft-skip when electrical_panel off', function () {
    it('returns valid empty validation without querying standards', function () {
        withoutFeatures(['electrical_panel']);

        $bom = Bom::factory()->create();
        BomItem::factory()->for($bom)->create([
            'component_standard_id' => null,
        ]);

        $service = app(SpecValidationService::class);
        $result = $service->validateBomBrandSwap($bom, 'schneider');

        expect($result['valid'])->toBeTrue()
            ->and($result['items'])->toBe([])
            ->and($result['summary']['total_items'])->toBe(0)
            ->and($result['summary']['total_errors'])->toBe(0);
    });

    it('returns pass-through compliance when electrical_panel off', function () {
        withoutFeatures(['electrical_panel']);

        $bom = Bom::factory()->create();
        $result = app(SpecValidationService::class)->validateBomCompliance($bom);

        expect($result['valid'])->toBeTrue()
            ->and($result['items'])->toBe([]);
    });
});

describe('BomTemplateService brand resolution when electrical_panel off', function () {
    it('returns empty available brands', function () {
        withoutFeatures(['electrical_panel']);

        $template = BomTemplate::factory()->create();
        $standard = ComponentStandard::factory()->create();
        BomTemplateItem::factory()->for($template, 'template')->create([
            'component_standard_id' => $standard->id,
            'product_id' => null,
        ]);

        $brands = app(BomTemplateService::class)->getAvailableBrandsForTemplate($template);

        expect($brands)->toBe([]);
    });

    it('creates bom from template without requiring brand mappings', function () {
        withFeatures([
            'bom' => true,
            'electrical_panel' => false,
        ]);

        $template = BomTemplate::factory()->create();
        $standard = ComponentStandard::factory()->create();
        BomTemplateItem::factory()->for($template, 'template')->create([
            'type' => 'material',
            'component_standard_id' => $standard->id,
            'product_id' => null,
            'description' => 'Generic component line',
            'default_quantity' => 2,
            'unit' => 'pcs',
        ]);

        $product = \App\Models\Inventory\Product::factory()->create();

        $result = app(BomTemplateService::class)->createBomFromTemplate($template, [
            'product_id' => $product->id,
            'name' => 'Enterprise BOM from template',
            'output_quantity' => 1,
            'target_brand' => 'schneider', // ignored when flag off
        ]);

        $bom = $result['bom']->load('items');

        expect($bom)->toBeInstanceOf(Bom::class)
            ->and($bom->items)->toHaveCount(1)
            ->and($bom->items->first()->component_standard_id)->toBeNull()
            ->and($bom->items->first()->description)->toBe('Generic component line')
            ->and($result['report']['using_product'])->toBe(1);
    });
});

describe('API resources omit industry fields when electrical_panel off', function () {
    it('omits component_standard_id on bom item payload', function () {
        withFeatures([
            'bom' => true,
            'electrical_panel' => false,
        ]);

        $standard = ComponentStandard::factory()->create();
        $bom = Bom::factory()->create();
        BomItem::factory()->for($bom)->create([
            'component_standard_id' => $standard->id,
        ]);

        $response = $this->getJson("/api/v1/boms/{$bom->id}");

        $response->assertOk();
        $item = $response->json('data.items.0');
        expect($item)->not->toHaveKey('component_standard_id');
    });

    it('includes component_standard_id when electrical_panel on', function () {
        withFeatures([
            'bom' => true,
            'electrical_panel' => true,
        ]);

        $standard = ComponentStandard::factory()->create();
        $bom = Bom::factory()->create();
        BomItem::factory()->for($bom)->create([
            'component_standard_id' => $standard->id,
        ]);

        $response = $this->getJson("/api/v1/boms/{$bom->id}");

        $response->assertOk();
        $item = $response->json('data.items.0');
        expect($item)->toHaveKey('component_standard_id')
            ->and($item['component_standard_id'])->toBe($standard->id);
    });
});

describe('Profile-aware foundation seeders', function () {
    it('does not seed component standards when electrical_panel is off', function () {
        withoutFeatures(['electrical_panel']);
        $this->seed(RolesAndPermissionsSeeder::class);

        $before = ComponentStandard::query()->count();
        $this->seed(MasterDataSeeder::class);

        expect(ComponentStandard::query()->count())->toBe($before);
    });

    it('does not seed solar irradiance or PLN tariffs when solar_proposals is off', function () {
        withoutFeatures(['solar_proposals']);

        $this->seed(IndonesiaSolarDataSeeder::class);
        $this->seed(PlnTariffSeeder::class);

        expect(IndonesiaSolarData::query()->count())->toBe(0)
            ->and(PlnTariff::query()->count())->toBe(0);
    });

    it('seeds solar irradiance when solar_proposals is on', function () {
        withFeatures(['solar_proposals' => true]);

        $this->seed(IndonesiaSolarDataSeeder::class);

        expect(IndonesiaSolarData::query()->count())->toBeGreaterThan(0);
    });
});

describe('Demo profile constants', function () {
    it('exposes enterprise demo profile constant', function () {
        expect(\Database\Seeders\Demo\DemoSeeder::DEMO_ENTERPRISE)->toBe('enterprise')
            ->and(Features::disabled('electrical_panel') || Features::enabled('electrical_panel'))->toBeTrue();
    });
});
