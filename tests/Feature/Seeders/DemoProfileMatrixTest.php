<?php

declare(strict_types=1);

use App\Models\ElectricalPanel\ComponentStandard;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Projects\Project;
use App\Models\Solar\IndonesiaSolarData;
use App\Models\Solar\SolarProposal;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\Demo\DemoSeeder;
use Database\Seeders\Demo\EnterpriseManufacturingDemoSeeder;
use Database\Seeders\Demo\GeneralTradingDemoSeeder;
use Database\Seeders\Demo\MasterDataSeeder;
use Database\Seeders\Demo\Nex\NexSolarProposalSeeder;
use Database\Seeders\Demo\ServicesProjectsDemoSeeder;
use Database\Seeders\FiscalPeriodSeeder;
use Database\Seeders\IndonesiaSolarDataSeeder;
use Database\Seeders\PlnTariffSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
    $this->seed(FiscalPeriodSeeder::class);
});

describe('DemoSeeder profile mapping', function () {
    it('maps FEATURE_PRESET keys to demo profiles', function () {
        expect(DemoSeeder::profileFromFeaturePreset('general'))->toBe(DemoSeeder::DEMO_GENERAL)
            ->and(DemoSeeder::profileFromFeaturePreset('services'))->toBe(DemoSeeder::DEMO_SERVICES)
            ->and(DemoSeeder::profileFromFeaturePreset('manufacturing'))->toBe(DemoSeeder::DEMO_MANUFACTURING)
            ->and(DemoSeeder::profileFromFeaturePreset('enterprise'))->toBe(DemoSeeder::DEMO_ENTERPRISE)
            ->and(DemoSeeder::profileFromFeaturePreset('vahana'))->toBe(DemoSeeder::DEMO_VAHANA)
            ->and(DemoSeeder::profileFromFeaturePreset('solar'))->toBe(DemoSeeder::DEMO_NEX)
            ->and(DemoSeeder::profileFromFeaturePreset('nex'))->toBe(DemoSeeder::DEMO_NEX)
            ->and(DemoSeeder::profileFromFeaturePreset('full'))->toBe(DemoSeeder::DEMO_ALL)
            ->and(DemoSeeder::profileFromFeaturePreset('pos'))->toBe(DemoSeeder::DEMO_POS);
    });
});

describe('general profile seed', function () {
    it('seeds trading without industry masters or generic BOMs', function () {
        withFeatures([
            'bom' => false,
            'manufacturing' => false,
            'work_orders' => false,
            'projects' => false,
            'electrical_panel' => false,
            'solar_proposals' => false,
            'mrp' => false,
        ]);

        $this->seed(MasterDataSeeder::class);
        $this->seed(GeneralTradingDemoSeeder::class);
        $this->seed(EnterpriseManufacturingDemoSeeder::class); // should no-op

        expect(ComponentStandard::query()->count())->toBe(0)
            ->and(Bom::where('bom_number', 'BOM-ASM-001')->exists())->toBeFalse()
            ->and(Product::where('sku', 'GEN-ITEM-A')->exists())->toBeTrue();
    });
});

describe('enterprise / manufacturing generic BOM seed', function () {
    it('seeds active BOMs when manufacturing packs are on and skips panel standards', function () {
        withFeatures([
            'bom' => true,
            'manufacturing' => true,
            'work_orders' => true,
            'projects' => true,
            'electrical_panel' => false,
            'solar_proposals' => false,
            'mrp' => true,
        ]);

        $this->seed(MasterDataSeeder::class);
        $this->seed(EnterpriseManufacturingDemoSeeder::class);

        expect(ComponentStandard::query()->count())->toBe(0)
            ->and(Bom::where('bom_number', 'BOM-ASM-001')->where('status', 'active')->exists())->toBeTrue()
            ->and(Bom::where('bom_number', 'BOM-ASM-002')->exists())->toBeTrue()
            ->and(Product::where('sku', 'FG-ASM-001')->exists())->toBeTrue()
            ->and(Bom::where('bom_number', 'BOM-ASM-001')->first()->items()->count())->toBeGreaterThan(0);
    });
});

describe('services project seed', function () {
    it('creates a services project when projects pack is on', function () {
        withFeatures([
            'projects' => true,
            'electrical_panel' => false,
            'solar_proposals' => false,
            'bom' => false,
        ]);

        $this->seed(MasterDataSeeder::class);
        $this->seed(GeneralTradingDemoSeeder::class);
        $this->seed(ServicesProjectsDemoSeeder::class);

        expect(Project::query()->where('name', 'Instalasi & Commissioning Demo')->exists())->toBeTrue()
            ->and(Product::where('sku', 'SVC-INSTALL')->exists())->toBeTrue()
            ->and(ComponentStandard::query()->count())->toBe(0);
    });
});

describe('solar masters and proposals', function () {
    it('skips solar irradiance when solar_proposals off', function () {
        withoutFeatures(['solar_proposals']);
        $this->seed(IndonesiaSolarDataSeeder::class);
        $this->seed(PlnTariffSeeder::class);

        expect(IndonesiaSolarData::query()->count())->toBe(0);
    });

    it('seeds solar irradiance when solar_proposals on', function () {
        withFeatures(['solar_proposals' => true]);
        $this->seed(IndonesiaSolarDataSeeder::class);

        expect(IndonesiaSolarData::query()->count())->toBeGreaterThan(0);
    });

    it('seeds solar proposals when solar_proposals on and contacts exist', function () {
        withFeatures([
            'solar_proposals' => true,
            'bom' => true,
            'projects' => true,
        ]);

        $this->seed(MasterDataSeeder::class);
        $this->seed(GeneralTradingDemoSeeder::class);
        $this->seed(IndonesiaSolarDataSeeder::class);
        $this->seed(PlnTariffSeeder::class);
        $this->seed(NexSolarProposalSeeder::class);

        expect(SolarProposal::query()->count())->toBeGreaterThan(0);
    });
});

describe('enterprise extended path uses active BOMs for work orders', function () {
    it('can create work orders from generic seeded BOMs', function () {
        withFeatures([
            'bom' => true,
            'manufacturing' => true,
            'work_orders' => true,
            'material_requisitions' => true,
            'projects' => false,
            'down_payments' => true,
            'stock_opname' => true,
            'electrical_panel' => false,
            'solar_proposals' => false,
        ]);

        $this->seed(MasterDataSeeder::class);
        $this->seed(EnterpriseManufacturingDemoSeeder::class);
        $this->seed(\Database\Seeders\Demo\DemoExtendedTransactionSeeder::class);

        expect(WorkOrder::query()->count())->toBeGreaterThan(0);
    });
});
