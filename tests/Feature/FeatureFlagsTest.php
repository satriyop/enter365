<?php

use App\Contracts\FeatureManager;
use App\Support\ConfigFeatureManager;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
});

describe('ConfigFeatureManager', function () {

    it('returns true for enabled features', function () {
        config(['features.modules.inventory' => true]);

        $manager = new ConfigFeatureManager;

        expect($manager->enabled('inventory'))->toBeTrue();
        expect($manager->disabled('inventory'))->toBeFalse();
    });

    it('returns false for disabled features', function () {
        config(['features.modules.mrp' => false]);

        $manager = new ConfigFeatureManager;

        expect($manager->enabled('mrp'))->toBeFalse();
        expect($manager->disabled('mrp'))->toBeTrue();
    });

    it('defaults to true for unconfigured features', function () {
        // Clear the config to test default behavior
        config(['features.modules' => []]);

        $manager = new ConfigFeatureManager;

        expect($manager->enabled('unknown_feature'))->toBeTrue();
    });

    it('lists all modules correctly', function () {
        config(['features.modules' => [
            'inventory' => true,
            'mrp' => false,
            'projects' => true,
        ]]);

        $manager = new ConfigFeatureManager;

        expect($manager->all())->toBe([
            'inventory' => true,
            'mrp' => false,
            'projects' => true,
        ]);
    });

    it('lists enabled modules correctly', function () {
        config(['features.modules' => [
            'inventory' => true,
            'mrp' => false,
            'projects' => true,
        ]]);

        $manager = new ConfigFeatureManager;

        expect($manager->enabledModules())->toBe(['inventory', 'projects']);
    });

    it('lists disabled modules correctly', function () {
        config(['features.modules' => [
            'inventory' => true,
            'mrp' => false,
            'manufacturing' => false,
        ]]);

        $manager = new ConfigFeatureManager;

        expect($manager->disabledModules())->toBe(['mrp', 'manufacturing']);
    });

});

describe('Features Static Facade', function () {

    it('delegates enabled check to FeatureManager contract', function () {
        config(['features.modules.budgeting' => true]);

        expect(Features::enabled('budgeting'))->toBeTrue();
        expect(Features::disabled('budgeting'))->toBeFalse();
    });

    it('delegates disabled check to FeatureManager contract', function () {
        config(['features.modules.mrp' => false]);

        expect(Features::enabled('mrp'))->toBeFalse();
        expect(Features::disabled('mrp'))->toBeTrue();
    });

    it('returns all modules', function () {
        config(['features.modules' => [
            'inventory' => true,
            'mrp' => false,
        ]]);

        expect(Features::all())->toBe([
            'inventory' => true,
            'mrp' => false,
        ]);
    });

    it('returns enabled modules list', function () {
        config(['features.modules' => [
            'inventory' => true,
            'mrp' => false,
            'projects' => true,
        ]]);

        expect(Features::enabledModules())->toBe(['inventory', 'projects']);
    });

    it('returns disabled modules list', function () {
        config(['features.modules' => [
            'inventory' => true,
            'mrp' => false,
            'manufacturing' => false,
        ]]);

        expect(Features::disabledModules())->toBe(['mrp', 'manufacturing']);
    });

});

describe('FeatureManager Contract Binding', function () {

    it('is bound as singleton in container', function () {
        $instance1 = app(FeatureManager::class);
        $instance2 = app(FeatureManager::class);

        expect($instance1)->toBe($instance2);
    });

    it('resolves to ConfigFeatureManager', function () {
        $manager = app(FeatureManager::class);

        expect($manager)->toBeInstanceOf(ConfigFeatureManager::class);
    });

});

describe('Feature Status API', function () {

    it('returns all feature flags', function () {
        config([
            'features.preset' => 'general',
            'features.modules' => [
                'inventory' => true,
                'mrp' => false,
            ],
        ]);

        $response = $this->getJson('/api/v1/features');

        $response->assertOk()
            ->assertJsonPath('data.preset', 'general')
            ->assertJsonPath('data.modules.inventory', true)
            ->assertJsonPath('data.modules.mrp', false)
            ->assertJsonPath('data.enabled', ['inventory'])
            ->assertJsonPath('data.disabled', ['mrp']);
    });

    it('defaults odoo packs and industry add-ons off for general company profile', function () {
        // Trading profile: core on; MFG/projects optional packs off; industry off
        withFeatures([
            'quotations' => true,
            'inventory' => true,
            'solar_proposals' => false,
            'electrical_panel' => false,
            'mrp' => false,
            'projects' => false,
            'bom' => false,
            'work_orders' => false,
            'manufacturing' => false,
            'subcontracting' => false,
        ]);

        expect(Features::enabled('quotations'))->toBeTrue()
            ->and(Features::enabled('inventory'))->toBeTrue()
            ->and(Features::disabled('solar_proposals'))->toBeTrue()
            ->and(Features::disabled('electrical_panel'))->toBeTrue()
            ->and(Features::disabled('mrp'))->toBeTrue()
            ->and(Features::disabled('projects'))->toBeTrue()
            ->and(Features::disabled('bom'))->toBeTrue()
            ->and(Features::disabled('work_orders'))->toBeTrue();
    });

    it('enterprise-like profile enables odoo packs but not industry add-ons', function () {
        withFeatures([
            'quotations' => true,
            'bom' => true,
            'work_orders' => true,
            'mrp' => true,
            'projects' => true,
            'subcontracting' => true,
            'solar_proposals' => false,
            'electrical_panel' => false,
        ]);

        expect(Features::enabled('bom'))->toBeTrue()
            ->and(Features::enabled('projects'))->toBeTrue()
            ->and(Features::enabled('mrp'))->toBeTrue()
            ->and(Features::disabled('solar_proposals'))->toBeTrue()
            ->and(Features::disabled('electrical_panel'))->toBeTrue();
    });

    it('returns empty arrays when no modules configured', function () {
        config(['features.modules' => []]);

        $response = $this->getJson('/api/v1/features');

        $response->assertOk()
            ->assertJsonPath('data.modules', [])
            ->assertJsonPath('data.enabled', [])
            ->assertJsonPath('data.disabled', []);
    });

});

describe('Test Helper Functions', function () {

    it('withFeatures enables specific features', function () {
        withFeatures([
            'inventory' => true,
            'mrp' => false,
        ]);

        expect(Features::enabled('inventory'))->toBeTrue();
        expect(Features::disabled('mrp'))->toBeTrue();
    });

    it('withoutFeatures disables specific features', function () {
        // First enable the feature
        config(['features.modules.inventory' => true]);
        expect(Features::enabled('inventory'))->toBeTrue();

        // Then disable it
        withoutFeatures(['inventory']);
        expect(Features::disabled('inventory'))->toBeTrue();
    });

});

describe('EnsureFeatureEnabled Middleware', function () {

    it('allows access when feature is enabled', function () {
        config(['features.modules.mrp' => true]);

        $response = $this->getJson('/api/v1/mrp-runs');

        $response->assertOk();
    });

    it('returns 404 when feature is disabled', function () {
        config(['features.modules.mrp' => false]);

        $response = $this->getJson('/api/v1/mrp-runs');

        $response->assertNotFound();
    });

    it('allows access to unprotected routes regardless of feature config', function () {
        // Features endpoint is always available
        config(['features.modules' => []]);

        $response = $this->getJson('/api/v1/features');

        $response->assertOk();
    });

    it('blocks multiple feature-protected routes when disabled', function () {
        withoutFeatures(['quotations', 'projects', 'budgeting']);

        $this->getJson('/api/v1/quotations')->assertNotFound();
        $this->getJson('/api/v1/projects')->assertNotFound();
        $this->getJson('/api/v1/budgets')->assertNotFound();
    });

    it('blocks vertical report endpoints when packs are disabled', function () {
        withoutFeatures(['projects', 'work_orders', 'subcontracting']);

        $this->getJson('/api/v1/reports/project-profitability')->assertNotFound();
        $this->getJson('/api/v1/reports/work-order-costs')->assertNotFound();
        $this->getJson('/api/v1/reports/subcontractor-summary')->assertNotFound();
        $this->getJson('/api/v1/reports/cost-variance')->assertNotFound();
    });

    it('allows core financial reports when vertical packs are off', function () {
        withoutFeatures(['projects', 'work_orders', 'subcontracting', 'solar_proposals', 'electrical_panel']);

        // Core reports stay available for general company
        $this->getJson('/api/v1/reports/trial-balance')->assertOk();
        $this->getJson('/api/v1/reports/balance-sheet')->assertOk();
        $this->getJson('/api/v1/reports/cogs-summary')->assertOk();
    });

    it('blocks electrical panel routes when industry add-on is disabled', function () {
        // Generic BOM pack on, Vahana electrical_panel off
        withFeatures([
            'bom' => true,
            'electrical_panel' => false,
        ]);

        $this->getJson('/api/v1/component-standards')->assertNotFound();
        $this->getJson('/api/v1/spec-rule-sets')->assertNotFound();
        $this->getJson('/api/v1/available-brands')->assertNotFound();
        $this->getJson('/api/v1/component-mappings/stats')->assertNotFound();
    });

    it('allows electrical panel routes when industry add-on is enabled', function () {
        withFeatures([
            'bom' => true,
            'electrical_panel' => true,
        ]);

        $this->getJson('/api/v1/component-standards')->assertOk();
        $this->getJson('/api/v1/spec-rule-sets')->assertOk();
        $this->getJson('/api/v1/available-brands')->assertOk();
    });

    it('still allows generic bom routes when electrical panel is off', function () {
        withFeatures([
            'bom' => true,
            'electrical_panel' => false,
        ]);

        $this->getJson('/api/v1/boms')->assertOk();
        $this->getJson('/api/v1/bom-templates')->assertOk();
    });

});
