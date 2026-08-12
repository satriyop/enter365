<?php

declare(strict_types=1);

use App\Models\ElectricalPanel\ComponentStandard;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\BomTemplateItem;
use App\Models\Solar\IndonesiaSolarData;
use App\Models\Solar\PlnTariff;
use App\Services\ElectricalPanel\SpecValidationService;
use App\Services\Manufacturing\BomTemplateService;
use Database\Seeders\Demo\MasterDataSeeder;
use Database\Seeders\IndonesiaSolarDataSeeder;
use Database\Seeders\PlnTariffSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Addons\ElectricalPanelHelpers;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
});

describe('SpecValidation soft-skip when electrical_panel off', function () {
    it('returns valid empty validation without querying standards', function () {
        withoutFeatures(['electrical_panel']);

        $bom = Bom::factory()->create();
        BomItem::factory()->for($bom)->create();

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
    it('404s template available-brands when electrical_panel off', function () {
        withFeatures([
            'bom' => true,
            'electrical_panel' => false,
        ]);

        $template = BomTemplate::factory()->create();

        $this->getJson("/api/v1/bom-templates/{$template->id}/available-brands")
            ->assertNotFound();
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
            ->and($bom->items->first()->panelMeta)->toBeNull()
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

        $bom = Bom::factory()->create();
        BomItem::factory()->for($bom)->create();

        $response = $this->getJson("/api/v1/boms/{$bom->id}");

        $response->assertOk();
        $item = $response->json('data.items.0');
        expect($item)->not->toHaveKey('component_standard_id');
    });

    it('includes component_standard_id from panel meta when electrical_panel on', function () {
        withFeatures([
            'bom' => true,
            'electrical_panel' => true,
        ]);

        $standard = ComponentStandard::factory()->create();
        $bom = Bom::factory()->create();
        $item = BomItem::factory()->for($bom)->create();
        ElectricalPanelHelpers::attachBomItemStandard($item, $standard);

        $response = $this->getJson("/api/v1/boms/{$bom->id}");

        $response->assertOk();
        $payload = $response->json('data.items.0');
        expect($payload)->toHaveKey('component_standard_id')
            ->and($payload['component_standard_id'])->toBe($standard->id);
    });
});

describe('HTTP requests prohibit panel fields when electrical_panel off', function () {
    it('rejects component_standard_id on template item create', function () {
        withFeatures([
            'bom' => true,
            'electrical_panel' => false,
        ]);

        $template = BomTemplate::factory()->create();

        $response = $this->postJson("/api/v1/bom-templates/{$template->id}/items", [
            'type' => BomTemplateItem::TYPE_MATERIAL,
            'component_standard_id' => 1,
            'description' => 'Should fail',
            'default_quantity' => 1,
            'unit' => 'pcs',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['component_standard_id']);
    });

    it('rejects target_brand when creating BOM from template', function () {
        withFeatures([
            'bom' => true,
            'electrical_panel' => false,
        ]);

        $template = BomTemplate::factory()->create();
        $product = Product::factory()->create();

        $response = $this->postJson("/api/v1/bom-templates/{$template->id}/create-bom", [
            'product_id' => $product->id,
            'target_brand' => 'schneider',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['target_brand']);
    });

    it('rejects default_rule_set_id on template create', function () {
        withFeatures([
            'bom' => true,
            'electrical_panel' => false,
        ]);

        $response = $this->postJson('/api/v1/bom-templates', [
            'code' => 'TPL-NO-PANEL',
            'name' => 'Core only',
            'default_rule_set_id' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['default_rule_set_id']);
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
    it('exposes demo profiles for all feature presets', function () {
        expect(\Database\Seeders\Demo\DemoSeeder::DEMO_ENTERPRISE)->toBe('enterprise')
            ->and(\Database\Seeders\Demo\DemoSeeder::DEMO_SERVICES)->toBe('services')
            ->and(\Database\Seeders\Demo\DemoSeeder::DEMO_MANUFACTURING)->toBe('manufacturing')
            ->and(\Database\Seeders\Demo\DemoSeeder::profileFromFeaturePreset('services'))->toBe('services')
            ->and(\Database\Seeders\Demo\DemoSeeder::profiles())->toContain('general', 'vahana', 'nex', 'all');
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

    it('refuses to resolve solar services when solar_proposals is off', function () {
        withoutFeatures(['solar_proposals']);

        expect(\App\Providers\Addons\SolarServiceProvider::isEnabled())->toBeFalse();

        expect(fn () => app(\App\Contracts\Solar\SolarProposalServiceInterface::class))
            ->toThrow(\Illuminate\Contracts\Container\BindingResolutionException::class);

        expect(fn () => app(\App\Contracts\Solar\SolarCalculationServiceInterface::class))
            ->toThrow(\Illuminate\Contracts\Container\BindingResolutionException::class);
    });

    it('resolves solar services when solar_proposals is on', function () {
        withFeatures(['solar_proposals' => true]);

        expect(app(\App\Contracts\Solar\SolarProposalServiceInterface::class))
            ->toBeInstanceOf(\App\Services\Solar\SolarProposalService::class)
            ->and(app(\App\Contracts\Solar\SolarCalculationServiceInterface::class))
            ->toBeInstanceOf(\App\Services\Solar\SolarCalculationService::class);
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
            ->and($coreFiles)->not->toContain('NullBomTemplateBrandResolver');
    });

    it('core manufacturing sources have no panel extension columns or ElectricalPanel imports', function () {
        $paths = array_merge(
            glob(app_path('Services/Manufacturing/*.php')) ?: [],
            glob(app_path('Models/Manufacturing/*.php')) ?: [],
            [app_path('Models/Inventory/Product.php')],
        );

        foreach ($paths as $path) {
            $src = file_get_contents($path) ?: '';
            expect($src)->not->toContain('App\\Models\\ElectricalPanel\\')
                ->and($src)->not->toContain('App\\Services\\ElectricalPanel\\')
                ->and($src)->not->toContain('component_standard_id')
                ->and($src)->not->toContain('spec_rule_set_id')
                ->and($src)->not->toContain('default_rule_set_id')
                ->and($src)->not->toContain('BomTemplateBrandResolver');
        }

        expect(Schema::hasColumn('bom_items', 'component_standard_id'))->toBeFalse()
            ->and(Schema::hasColumn('boms', 'spec_rule_set_id'))->toBeFalse()
            ->and(Schema::hasTable('electrical_panel_bom_item_meta'))->toBeTrue();
    });

    it('registers panel controllers under ElectricalPanel namespace', function () {
        expect(class_exists(\App\Http\Controllers\Api\V1\ElectricalPanel\ComponentStandardController::class))->toBeTrue()
            ->and(class_exists(\App\Http\Controllers\Api\V1\Solar\SolarProposalController::class))->toBeTrue()
            ->and(file_exists(base_path('routes/addons/electrical_panel.php')))->toBeTrue()
            ->and(file_exists(base_path('routes/addons/solar.php')))->toBeTrue();
    });

    it('keeps industry form requests under add-on HTTP namespaces', function () {
        expect(class_exists(\App\Http\Requests\Api\V1\ElectricalPanel\SwapBrandRequest::class))->toBeTrue()
            ->and(class_exists(\App\Http\Requests\Api\V1\ElectricalPanel\StoreComponentStandardRequest::class))->toBeTrue()
            ->and(class_exists(\App\Http\Requests\Api\V1\Solar\StoreSolarProposalRequest::class))->toBeTrue()
            ->and(file_exists(app_path('Http/Requests/Api/V1/SwapBrandRequest.php')))->toBeFalse()
            ->and(file_exists(app_path('Http/Requests/Api/V1/StoreComponentStandardRequest.php')))->toBeFalse()
            ->and(file_exists(app_path('Http/Requests/Api/V1/StoreSolarProposalRequest.php')))->toBeFalse();
    });

    it('keeps industry API resources under add-on HTTP namespaces', function () {
        expect(class_exists(\App\Http\Resources\Api\V1\ElectricalPanel\ComponentStandardResource::class))->toBeTrue()
            ->and(class_exists(\App\Http\Resources\Api\V1\Solar\SolarProposalResource::class))->toBeTrue()
            ->and(file_exists(app_path('Http/Resources/Api/V1/ComponentStandardResource.php')))->toBeFalse()
            ->and(file_exists(app_path('Http/Resources/Api/V1/SolarProposalResource.php')))->toBeFalse();
    });

    it('BomTemplateController does not hard-reference ElectricalPanel models', function () {
        $src = file_get_contents(app_path('Http/Controllers/Api/V1/BomTemplateController.php')) ?: '';

        expect($src)->not->toContain('App\\Models\\ElectricalPanel\\')
            ->and($src)->not->toContain('BomTemplateItemPanelMeta');
    });

    it('core BOM HTTP layer has zero industry package mentions', function () {
        $paths = array_merge(
            [
                app_path('Http/Controllers/Api/V1/BomController.php'),
                app_path('Http/Controllers/Api/V1/BomTemplateController.php'),
                app_path('Http/Resources/Api/V1/BomItemResource.php'),
                app_path('Http/Resources/Api/V1/BomTemplateResource.php'),
                app_path('Http/Resources/Api/V1/BomTemplateItemResource.php'),
                app_path('Http/Requests/Api/V1/CreateBomFromTemplateRequest.php'),
                app_path('Http/Requests/Api/V1/StoreBomTemplateRequest.php'),
                app_path('Http/Requests/Api/V1/UpdateBomTemplateRequest.php'),
                app_path('Http/Requests/Api/V1/StoreBomTemplateItemRequest.php'),
                app_path('Http/Requests/Api/V1/UpdateBomTemplateItemRequest.php'),
                app_path('Contracts/Manufacturing/BomTemplateServiceInterface.php'),
                app_path('Services/Manufacturing/BomTemplateService.php'),
                app_path('Support/AddonExtensions.php'),
            ],
        );

        $forbidden = [
            'App\\Models\\ElectricalPanel\\',
            'App\\Services\\ElectricalPanel\\',
            'App\\Models\\Solar\\',
            'App\\Services\\Solar\\',
            'ElectricalPanel\\',
            'electrical_panel',
            'component_standard_id',
            'component_standard',
            'default_rule_set_id',
            'default_rule_set',
            'defaultRuleSet',
            'panelMeta',
            'target_brand',
            'spec_rule_set_id',
            'BomItemPanelMeta',
            'BomTemplatePanelMeta',
            'ComponentStandard',
            'ComponentBrandMapping',
            'getAvailableBrands',
            'availableBrands',
            'BrandSwap',
        ];

        foreach ($paths as $path) {
            $src = file_get_contents($path) ?: '';
            foreach ($forbidden as $needle) {
                expect($src)
                    ->not->toContain($needle)
                    ->and(basename($path)." must not mention {$needle}")->not->toBeEmpty();
            }
        }
    });

    it('keeps industry exports and imports under add-on namespaces', function () {
        expect(class_exists(\App\Exports\ElectricalPanel\ComponentMappingTemplateExport::class))->toBeTrue()
            ->and(class_exists(\App\Imports\ElectricalPanel\ComponentMappingImport::class))->toBeTrue()
            ->and(class_exists(\App\Exports\Solar\SolarProposalExport::class))->toBeTrue()
            ->and(file_exists(app_path('Exports/ComponentMappingTemplateExport.php')))->toBeFalse()
            ->and(file_exists(app_path('Exports/SolarProposalExport.php')))->toBeFalse()
            ->and(file_exists(app_path('Imports/ComponentMappingImport.php')))->toBeFalse();
    });

    it('does not register solar morph alias from AppServiceProvider', function () {
        $src = file_get_contents(app_path('Providers/AppServiceProvider.php')) ?: '';

        expect($src)->not->toContain('solar_proposal')
            ->and($src)->not->toContain('SolarProposal')
            ->and($src)->not->toContain('SolarCalculation');
    });
});
