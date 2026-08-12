<?php

declare(strict_types=1);

/**
 * Defining journeys for FEATURE_PRESET=general and services.
 */

use App\Models\Projects\Project;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use App\Models\Shared\Payment;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('general feature set journeys', function () {
    beforeEach(function () {
        applyFeaturePreset('general');
        seedDemoFoundation($this);
        seedDemoProfile($this, DemoSeeder::DEMO_GENERAL);
        authenticatedAdmin();
    });

    it('serves core trading APIs after general demo seed', function () {
        $this->getJson('/api/v1/products')->assertOk();
        $this->getJson('/api/v1/invoices')->assertOk()
            ->assertJsonPath('meta.total', fn ($t) => $t > 0 || Invoice::query()->count() > 0);

        expect(Invoice::query()->count())->toBeGreaterThan(0)
            ->and(Bill::query()->count())->toBeGreaterThan(0)
            ->and(Payment::query()->count())->toBeGreaterThan(0);
    });

    it('404s manufacturing and industry packs when general is on', function () {
        $this->getJson('/api/v1/boms')->assertNotFound();
        $this->getJson('/api/v1/work-orders')->assertNotFound();
        $this->getJson('/api/v1/projects')->assertNotFound();
        $this->getJson('/api/v1/component-standards')->assertNotFound();
        $this->getJson('/api/v1/solar-proposals')->assertNotFound();
    });

    it('exposes feature flags API with industry and packs off', function () {
        $response = $this->getJson('/api/v1/features')->assertOk();

        $modules = $response->json('data.modules') ?? $response->json('data');
        expect(data_get($modules, 'electrical_panel') ?? data_get($modules, 'modules.electrical_panel'))
            ->toBeFalse()
            ->and(data_get($modules, 'solar_proposals') ?? false)->toBeFalse()
            ->and(data_get($modules, 'bom') ?? false)->toBeFalse()
            ->and(data_get($modules, 'projects') ?? false)->toBeFalse();
    });
});

describe('services feature set journeys', function () {
    beforeEach(function () {
        applyFeaturePreset('services');
        seedDemoFoundation($this);
        seedDemoProfile($this, DemoSeeder::DEMO_SERVICES);
        authenticatedAdmin();
    });

    it('seeds projects and serves projects API while keeping industry off', function () {
        expect(Project::query()->count())->toBeGreaterThan(0);

        $this->getJson('/api/v1/projects')->assertOk();
        $this->getJson('/api/v1/invoices')->assertOk();

        $this->getJson('/api/v1/boms')->assertNotFound();
        $this->getJson('/api/v1/component-standards')->assertNotFound();
        $this->getJson('/api/v1/solar-proposals')->assertNotFound();
    });

    it('can create a project via API after services demo seed', function () {
        $customer = \App\Models\Contacts\Contact::query()
            ->whereIn('type', ['customer', 'both'])
            ->first();

        expect($customer)->not->toBeNull();

        $response = $this->postJson('/api/v1/projects', [
            'name' => 'Journey Services Project',
            'contact_id' => $customer->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'budget_amount' => 10_000_000,
            'priority' => Project::PRIORITY_NORMAL,
        ]);

        $response->assertSuccessful();
        expect(Project::where('name', 'Journey Services Project')->exists())->toBeTrue();
    });
});
