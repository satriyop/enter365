<?php

declare(strict_types=1);

/**
 * Defining journeys for FEATURE_PRESET=solar/nex and full.
 */

use App\Models\Contacts\Contact;
use App\Models\ElectricalPanel\ComponentStandard;
use App\Models\Solar\IndonesiaSolarData;
use App\Models\Solar\SolarProposal;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('nex/solar feature set journeys', function () {
    beforeEach(function () {
        applyFeaturePreset('solar');
        seedDemoFoundation($this);
        seedDemoProfile($this, DemoSeeder::DEMO_NEX);
        authenticatedAdmin();
    });

    it('seeds irradiance and solar proposals and serves solar APIs', function () {
        expect(IndonesiaSolarData::query()->count())->toBeGreaterThan(0)
            ->and(SolarProposal::query()->count())->toBeGreaterThan(0);

        $this->getJson('/api/v1/solar-proposals')->assertOk();
        $this->getJson('/api/v1/solar-data/provinces')->assertOk();

        // Panel tools stay off for solar preset
        $this->getJson('/api/v1/component-standards')->assertNotFound();
        $this->getJson('/api/v1/work-orders')->assertNotFound();
    });

    it('can create a solar proposal via API after nex demo seed', function () {
        $customer = Contact::query()
            ->whereIn('type', [Contact::TYPE_CUSTOMER, Contact::TYPE_BOTH])
            ->first();

        expect($customer)->not->toBeNull();

        $response = $this->postJson('/api/v1/solar-proposals', [
            'contact_id' => $customer->id,
            'site_name' => 'Journey PLTS Site',
            'site_address' => 'Jl. Demo Journey No. 1',
            'province' => 'DKI Jakarta',
            'city' => 'Jakarta Selatan',
            'latitude' => -6.26,
            'longitude' => 106.81,
            'roof_area_m2' => 200,
            'roof_type' => 'flat',
            'roof_orientation' => 'north',
            'roof_tilt_degrees' => 10,
            'shading_percentage' => 5,
            'monthly_consumption_kwh' => 3000,
            'pln_tariff_category' => 'R-1/TR',
            'electricity_rate' => 1444,
            'tariff_escalation_percent' => 3,
        ]);

        $response->assertSuccessful();
        expect(SolarProposal::where('site_name', 'Journey PLTS Site')->exists())->toBeTrue();
    });

    it('can calculate a seeded solar proposal', function () {
        $proposal = SolarProposal::query()->first();
        expect($proposal)->not->toBeNull();

        $response = $this->postJson("/api/v1/solar-proposals/{$proposal->id}/calculate");
        $response->assertSuccessful();
    });
});

describe('full feature set coexistence journeys', function () {
    beforeEach(function () {
        applyFeaturePreset('full');
        seedDemoFoundation($this);
        seedDemoProfile($this, DemoSeeder::DEMO_ALL);
        authenticatedAdmin();
    });

    it('enables both industry verticals and core packs after full demo seed', function () {
        expect(ComponentStandard::query()->count())->toBeGreaterThan(0)
            ->and(SolarProposal::query()->count())->toBeGreaterThan(0)
            ->and(\App\Models\Manufacturing\Bom::where('bom_number', 'BOM-ASM-001')->exists())->toBeTrue();

        $this->getJson('/api/v1/component-standards')->assertOk();
        $this->getJson('/api/v1/solar-proposals')->assertOk();
        $this->getJson('/api/v1/boms')->assertOk();
        $this->getJson('/api/v1/work-orders')->assertOk();
        $this->getJson('/api/v1/projects')->assertOk();
    });

    it('exposes feature flags with industry modules on', function () {
        $modules = $this->getJson('/api/v1/features')->assertOk()->json('data.modules');

        expect($modules['electrical_panel'] ?? false)->toBeTrue()
            ->and($modules['solar_proposals'] ?? false)->toBeTrue()
            ->and($modules['bom'] ?? false)->toBeTrue()
            ->and($modules['projects'] ?? false)->toBeTrue();
    });
});
