<?php

declare(strict_types=1);

use App\Models\ElectricalPanel\ComponentStandard;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\BomTemplateItem;
use App\Models\Solar\IndonesiaSolarData;
use App\Models\Solar\PlnTariff;
use App\Services\ElectricalPanel\SpecValidationService;
use App\Services\Manufacturing\BomTemplateService;
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

describe('Add-on package boundaries (code isolation)', function () {
    it('places electrical panel services outside core Manufacturing', function () {
        expect(class_exists(\App\Services\ElectricalPanel\BrandSwapService::class))->toBeTrue()
            ->and(class_exists(\App\Services\ElectricalPanel\SpecValidationService::class))->toBeTrue()
            ->and(class_exists(\App\Models\ElectricalPanel\ComponentStandard::class))->toBeTrue()
            ->and(file_exists(app_path('Services/Manufacturing/BrandSwapService.php')))->toBeFalse()
            ->and(file_exists(app_path('Services/Manufacturing/SpecValidationService.php')))->toBeFalse()
            ->and(file_exists(app_path('Services/Manufacturing/ComponentStandardService.php')))->toBeFalse()
            ->and(file_exists(app_path('Models/Manufacturing/ComponentStandard.php')))->toBeFalse()
            ->and(is_dir(app_path('Services/Manufacturing/BrandSwap')))->toBeFalse();
    });

    it('registers industry add-on service providers', function () {
        $providers = require base_path('bootstrap/providers.php');

        expect($providers)->toContain(\App\Providers\Addons\ElectricalPanelServiceProvider::class)
            ->and($providers)->toContain(\App\Providers\Addons\SolarServiceProvider::class)
            ->and(config('addons.electrical_panel.feature'))->toBe('electrical_panel')
            ->and(config('addons.solar.feature'))->toBe('solar_proposals');
    });

    it('does not bind solar services when solar_proposals is off at provider register', function () {
        config(['features.modules.solar_proposals' => false]);

        $app = app();
        // Simulate fresh registration against current config (flag off)
        $provider = new \App\Providers\Addons\SolarServiceProvider($app);
        // Clear prior binding from boot (phpunit FEATURE_PRESET=full)
        $app->offsetUnset(\App\Contracts\Solar\SolarProposalServiceInterface::class);
        $app->offsetUnset(\App\Contracts\Solar\SolarCalculationServiceInterface::class);

        $provider->register();

        expect($app->bound(\App\Contracts\Solar\SolarProposalServiceInterface::class))->toBeFalse()
            ->and($app->bound(\App\Contracts\Solar\SolarCalculationServiceInterface::class))->toBeFalse()
            ->and(\App\Providers\Addons\SolarServiceProvider::isEnabled())->toBeFalse();
    });

    it('binds solar services when solar_proposals is on at provider register', function () {
        config(['features.modules.solar_proposals' => true]);

        $app = app();
        $app->offsetUnset(\App\Contracts\Solar\SolarProposalServiceInterface::class);
        $app->offsetUnset(\App\Contracts\Solar\SolarCalculationServiceInterface::class);

        (new \App\Providers\Addons\SolarServiceProvider($app))->register();

        expect($app->bound(\App\Contracts\Solar\SolarProposalServiceInterface::class))->toBeTrue()
            ->and($app->make(\App\Contracts\Solar\SolarProposalServiceInterface::class))
            ->toBeInstanceOf(\App\Services\Solar\SolarProposalService::class);
    });

    it('keeps core manufacturing services without industry class names', function () {
        $coreFiles = collect(glob(app_path('Services/Manufacturing/*.php')) ?: [])
            ->map(fn (string $path) => basename($path, '.php'))
            ->values()
            ->all();

        $forbidden = [
            'BrandSwapService',
            'ComponentStandardService',
            'ComponentBrandMappingService',
            'ComponentMappingService',
            'CostOptimizationService',
            'ProductEquivalenceService',
            'SpecValidationService',
            'SpecValidationRuleSetService',
            'BomTemplateBrandResolver',
        ];

        foreach ($forbidden as $name) {
            expect($coreFiles)->not->toContain($name);
        }

        expect($coreFiles)->toContain('BomService')
            ->and($coreFiles)->toContain('WorkOrderService')
            ->and($coreFiles)->toContain('MrpService')
            ->and($coreFiles)->toContain('NullBomTemplateBrandResolver');
    });

    it('core manufacturing sources do not import ElectricalPanel models', function () {
        $paths = array_merge(
            glob(app_path('Services/Manufacturing/*.php')) ?: [],
            glob(app_path('Models/Manufacturing/*.php')) ?: [],
        );

        foreach ($paths as $path) {
            $src = file_get_contents($path) ?: '';
            expect($src)->not->toContain('App\\Models\\ElectricalPanel\\')
                ->and($src)->not->toContain('App\\Services\\ElectricalPanel\\');
        }

        // Contract-only dependency is allowed
        $bomTemplate = file_get_contents(app_path('Services/Manufacturing/BomTemplateService.php')) ?: '';
        expect($bomTemplate)->toContain('BomTemplateBrandResolverInterface');
    });

    it('registers panel controllers under ElectricalPanel namespace', function () {
        expect(class_exists(\App\Http\Controllers\Api\V1\ElectricalPanel\ComponentStandardController::class))->toBeTrue()
            ->and(class_exists(\App\Http\Controllers\Api\V1\Solar\SolarProposalController::class))->toBeTrue()
            ->and(file_exists(base_path('routes/addons/electrical_panel.php')))->toBeTrue()
            ->and(file_exists(base_path('routes/addons/solar.php')))->toBeTrue();
    });
});
