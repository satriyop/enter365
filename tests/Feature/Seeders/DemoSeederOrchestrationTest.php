<?php

declare(strict_types=1);

/**
 * Full DemoSeeder orchestration per feature-set profile (seed:demo path).
 *
 * Each case applies FEATURE_PRESET-equivalent flags, runs foundation + DemoSeeder,
 * and asserts defining must/must-not data for that product posture.
 */

use App\Models\ElectricalPanel\ComponentStandard;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Projects\Project;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Sales\Invoice;
use App\Models\Solar\IndonesiaSolarData;
use App\Models\Solar\SolarProposal;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array<string, array{0: string, 1: string}>
 */
dataset('feature_set_demo_profiles', [
    'general' => ['general', DemoSeeder::DEMO_GENERAL],
    'services' => ['services', DemoSeeder::DEMO_SERVICES],
    'manufacturing' => ['manufacturing', DemoSeeder::DEMO_MANUFACTURING],
    'enterprise' => ['enterprise', DemoSeeder::DEMO_ENTERPRISE],
    'vahana' => ['vahana', DemoSeeder::DEMO_VAHANA],
    'solar' => ['solar', DemoSeeder::DEMO_NEX],
    'full' => ['full', DemoSeeder::DEMO_ALL],
]);

it('runs DemoSeeder for feature set and asserts defining seed outcomes', function (string $preset, string $profile) {
    applyFeaturePreset($preset);
    seedDemoFoundation($this);
    seedDemoProfile($this, $profile);

    $componentStandards = ComponentStandard::query()->count();
    $solarProposals = SolarProposal::query()->count();
    $irradiance = IndonesiaSolarData::query()->count();
    $genericBom = Bom::where('bom_number', 'BOM-ASM-001')->exists();
    $invoices = Invoice::query()->count();
    $purchaseOrders = PurchaseOrder::query()->count();
    $workOrders = WorkOrder::query()->count();
    $projects = Project::query()->count();

    match ($profile) {
        DemoSeeder::DEMO_GENERAL => expect($componentStandards)->toBe(0)
            ->and($solarProposals)->toBe(0)
            ->and($genericBom)->toBeFalse()
            ->and($invoices)->toBeGreaterThan(0)
            ->and($purchaseOrders)->toBeGreaterThan(0)
            ->and(Product::where('sku', 'GEN-ITEM-A')->exists())->toBeTrue(),

        DemoSeeder::DEMO_SERVICES => expect($componentStandards)->toBe(0)
            ->and($solarProposals)->toBe(0)
            ->and($genericBom)->toBeFalse()
            ->and($projects)->toBeGreaterThan(0)
            ->and($invoices)->toBeGreaterThan(0)
            ->and(Product::where('sku', 'SVC-INSTALL')->exists())->toBeTrue(),

        DemoSeeder::DEMO_MANUFACTURING => expect($componentStandards)->toBe(0)
            ->and($solarProposals)->toBe(0)
            ->and($genericBom)->toBeTrue()
            ->and($workOrders)->toBeGreaterThan(0)
            ->and(Bom::where('status', 'active')->whereHas('items')->count())->toBeGreaterThan(0),

        DemoSeeder::DEMO_ENTERPRISE => expect($componentStandards)->toBe(0)
            ->and($solarProposals)->toBe(0)
            ->and($genericBom)->toBeTrue()
            ->and($workOrders)->toBeGreaterThan(0)
            ->and($projects)->toBeGreaterThan(0)
            ->and($invoices)->toBeGreaterThan(0),

        DemoSeeder::DEMO_VAHANA => expect($componentStandards)->toBeGreaterThan(0)
            ->and($solarProposals)->toBe(0)
            ->and(Bom::query()->count())->toBeGreaterThan(0)
            ->and(Product::query()->count())->toBeGreaterThan(0),

        DemoSeeder::DEMO_NEX => expect($componentStandards)->toBe(0)
            ->and($irradiance)->toBeGreaterThan(0)
            ->and($solarProposals)->toBeGreaterThan(0)
            ->and(Product::query()->count())->toBeGreaterThan(0),

        DemoSeeder::DEMO_ALL => expect($componentStandards)->toBeGreaterThan(0)
            ->and($solarProposals)->toBeGreaterThan(0)
            ->and($genericBom)->toBeTrue()
            ->and($irradiance)->toBeGreaterThan(0),

        default => throw new InvalidArgumentException("Unknown profile {$profile}"),
    };
})->with('feature_set_demo_profiles');
