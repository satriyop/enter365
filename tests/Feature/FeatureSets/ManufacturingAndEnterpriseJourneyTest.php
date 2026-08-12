<?php

declare(strict_types=1);

/**
 * Defining journeys for FEATURE_PRESET=manufacturing and enterprise.
 */

use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Projects\Project;
use App\Services\Manufacturing\WorkOrderService;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('manufacturing feature set journeys', function () {
    beforeEach(function () {
        applyFeaturePreset('manufacturing');
        seedDemoFoundation($this);
        seedDemoProfile($this, DemoSeeder::DEMO_MANUFACTURING);
        authenticatedAdmin();
    });

    it('exposes BOM and work-order APIs with seeded generic shop-floor data', function () {
        expect(Bom::where('bom_number', 'BOM-ASM-001')->exists())->toBeTrue()
            ->and(WorkOrder::query()->count())->toBeGreaterThan(0);

        $this->getJson('/api/v1/boms')->assertOk();
        $this->getJson('/api/v1/work-orders')->assertOk();
        $this->getJson('/api/v1/bom-templates')->assertOk();

        // Industry verticals stay off
        $this->getJson('/api/v1/component-standards')->assertNotFound();
        $this->getJson('/api/v1/solar-proposals')->assertNotFound();
        $this->getJson('/api/v1/projects')->assertNotFound();
    });

    it('can create a work order from the generic seeded BOM via service', function () {
        $bom = Bom::where('bom_number', 'BOM-ASM-001')->with('items')->first();
        expect($bom)->not->toBeNull()
            ->and($bom->items)->not->toBeEmpty();

        $warehouse = \App\Models\Inventory\Warehouse::query()->where('is_default', true)->first()
            ?? \App\Models\Inventory\Warehouse::query()->first();

        $wo = app(WorkOrderService::class)->createFromBom($bom, [
            'name' => 'Journey MFG WO',
            'quantity' => 1,
            'warehouse_id' => $warehouse?->id,
            'priority' => 'normal',
        ]);

        expect($wo)->toBeInstanceOf(WorkOrder::class)
            ->and($wo->bom_id)->toBe($bom->id)
            ->and(WorkOrder::where('name', 'Journey MFG WO')->exists())->toBeTrue();

        $this->getJson("/api/v1/work-orders/{$wo->id}")->assertOk();
    });
});

describe('enterprise feature set journeys', function () {
    beforeEach(function () {
        applyFeaturePreset('enterprise');
        seedDemoFoundation($this);
        seedDemoProfile($this, DemoSeeder::DEMO_ENTERPRISE);
        authenticatedAdmin();
    });

    it('enables odoo packs without industry masters or add-on routes', function () {
        expect(Bom::where('bom_number', 'BOM-ASM-001')->exists())->toBeTrue()
            ->and(WorkOrder::query()->count())->toBeGreaterThan(0)
            ->and(Project::query()->count())->toBeGreaterThan(0)
            ->and(\App\Models\ElectricalPanel\ComponentStandard::query()->count())->toBe(0)
            ->and(\App\Models\Solar\SolarProposal::query()->count())->toBe(0);

        $this->getJson('/api/v1/boms')->assertOk();
        $this->getJson('/api/v1/work-orders')->assertOk();
        $this->getJson('/api/v1/projects')->assertOk();
        $this->getJson('/api/v1/mrp-runs')->assertOk();

        $this->getJson('/api/v1/component-standards')->assertNotFound();
        $this->getJson('/api/v1/solar-proposals')->assertNotFound();
        $this->getJson('/api/v1/spec-rule-sets')->assertNotFound();
    });

    it('lists features API with packs on and industry off', function () {
        $response = $this->getJson('/api/v1/features')->assertOk();
        $modules = $response->json('data.modules');

        expect($modules['bom'] ?? false)->toBeTrue()
            ->and($modules['projects'] ?? false)->toBeTrue()
            ->and($modules['electrical_panel'] ?? true)->toBeFalse()
            ->and($modules['solar_proposals'] ?? true)->toBeFalse();
    });
});
