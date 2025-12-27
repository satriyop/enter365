<?php

use App\Contracts\FeatureManager;
use App\Models\User;
use App\Support\ConfigFeatureManager;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
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
        config(['features.modules' => [
            'inventory' => true,
            'mrp' => false,
        ]]);

        $response = $this->getJson('/api/v1/features');

        $response->assertOk()
            ->assertJsonPath('modules.inventory', true)
            ->assertJsonPath('modules.mrp', false)
            ->assertJsonPath('enabled', ['inventory'])
            ->assertJsonPath('disabled', ['mrp']);
    });

    it('returns empty arrays when no modules configured', function () {
        config(['features.modules' => []]);

        $response = $this->getJson('/api/v1/features');

        $response->assertOk()
            ->assertJsonPath('modules', [])
            ->assertJsonPath('enabled', [])
            ->assertJsonPath('disabled', []);
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

});
