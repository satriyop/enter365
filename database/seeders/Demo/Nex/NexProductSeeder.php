<?php

namespace Database\Seeders\Demo\Nex;

use App\Models\Accounting\Account;
use App\Models\Accounting\Bom;
use App\Models\Accounting\BomItem;
use App\Models\Accounting\BomVariantGroup;
use App\Models\Accounting\Product;
use App\Models\Accounting\ProductCategory;
use Illuminate\Database\Seeder;

class NexProductSeeder extends Seeder
{
    private ?int $inventoryAccountId = null;

    private ?int $cogsAccountId = null;

    private ?int $salesAccountId = null;

    private ?int $purchaseAccountId = null;

    /**
     * Seed products and BOMs for PT NEX (Solar EPC Contractor).
     */
    public function run(): void
    {
        $this->loadAccounts();
        $this->createPvModules();
        $this->createInverters();
        $this->createMountingStructures();
        $this->createDcComponents();
        $this->createMonitoringEquipment();
        $this->createFinishedSystems();
        $this->createServices();
        $this->createBoms();
    }

    private function loadAccounts(): void
    {
        $this->inventoryAccountId = Account::where('code', '1-1400')->value('id');
        $this->cogsAccountId = Account::where('code', '5-1001')->value('id');
        $this->salesAccountId = Account::where('code', '4-1001')->value('id');
        $this->purchaseAccountId = Account::where('code', '5-1002')->value('id');
    }

    private function createPvModules(): void
    {
        $catPV = ProductCategory::where('code', 'RM-PV')->first();

        $products = [
            // NUSA Brand (Local - NEX's preferred)
            [
                'sku' => 'PV-NUSA-550',
                'name' => 'Panel Surya NUSA 550Wp Mono PERC',
                'description' => 'Modul PV NUSA 550Wp Monocrystalline PERC, produksi PT IDN Solar Tech',
                'category_id' => $catPV?->id,
                'unit' => 'pcs',
                'purchase_price' => 1650000,
                'selling_price' => 2200000,
                'min_stock' => 100,
                'current_stock' => 500,
            ],
            [
                'sku' => 'PV-NUSA-450',
                'name' => 'Panel Surya NUSA 450Wp Mono PERC',
                'description' => 'Modul PV NUSA 450Wp Monocrystalline PERC, half-cut cells',
                'category_id' => $catPV?->id,
                'unit' => 'pcs',
                'purchase_price' => 1350000,
                'selling_price' => 1800000,
                'min_stock' => 100,
                'current_stock' => 300,
            ],
            // Tier-1 Import Options
            [
                'sku' => 'PV-JA-545',
                'name' => 'Panel Surya JA Solar 545Wp JAM72S30',
                'description' => 'JA Solar Deep Blue 3.0 Mono PERC Half-cell',
                'category_id' => $catPV?->id,
                'unit' => 'pcs',
                'purchase_price' => 1800000,
                'selling_price' => 2400000,
                'min_stock' => 50,
                'current_stock' => 200,
            ],
            [
                'sku' => 'PV-LONGI-555',
                'name' => 'Panel Surya LONGi 555Wp Hi-MO 5',
                'description' => 'LONGi Hi-MO 5 Bifacial Mono PERC',
                'category_id' => $catPV?->id,
                'unit' => 'pcs',
                'purchase_price' => 2000000,
                'selling_price' => 2650000,
                'min_stock' => 50,
                'current_stock' => 150,
            ],
            [
                'sku' => 'PV-LONGI-430',
                'name' => 'Panel Surya LONGi 430Wp Hi-MO 4',
                'description' => 'LONGi Hi-MO 4 Mono PERC - Residential',
                'category_id' => $catPV?->id,
                'unit' => 'pcs',
                'purchase_price' => 1450000,
                'selling_price' => 1950000,
                'min_stock' => 50,
                'current_stock' => 100,
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                ['sku' => $product['sku']],
                array_merge($product, [
                    'type' => Product::TYPE_PRODUCT,
                    'tax_rate' => 11.00,
                    'is_taxable' => true,
                    'track_inventory' => true,
                    'is_active' => true,
                    'is_purchasable' => true,
                    'is_sellable' => true,
                    'inventory_account_id' => $this->inventoryAccountId,
                    'cogs_account_id' => $this->cogsAccountId,
                    'sales_account_id' => $this->salesAccountId,
                    'purchase_account_id' => $this->purchaseAccountId,
                ])
            );
        }

        $this->command->info('    Created 5 PV module products');
    }

    private function createInverters(): void
    {
        $catInv = ProductCategory::where('code', 'RM-INV')->first();

        $products = [
            // Huawei String Inverters
            [
                'sku' => 'INV-HW-5K',
                'name' => 'Inverter Huawei SUN2000-5KTL-L1',
                'description' => 'Huawei 5kW String Inverter, WiFi, 2 MPPT',
                'category_id' => $catInv?->id,
                'unit' => 'unit',
                'purchase_price' => 12000000,
                'selling_price' => 16000000,
                'min_stock' => 10,
                'current_stock' => 30,
            ],
            [
                'sku' => 'INV-HW-10K',
                'name' => 'Inverter Huawei SUN2000-10KTL-M1',
                'description' => 'Huawei 10kW String Inverter, 4G/WiFi, 2 MPPT',
                'category_id' => $catInv?->id,
                'unit' => 'unit',
                'purchase_price' => 18000000,
                'selling_price' => 24000000,
                'min_stock' => 10,
                'current_stock' => 25,
            ],
            [
                'sku' => 'INV-HW-20K',
                'name' => 'Inverter Huawei SUN2000-20KTL-M2',
                'description' => 'Huawei 20kW String Inverter, 4G/WiFi, 4 MPPT',
                'category_id' => $catInv?->id,
                'unit' => 'unit',
                'purchase_price' => 28000000,
                'selling_price' => 37000000,
                'min_stock' => 5,
                'current_stock' => 15,
            ],
            [
                'sku' => 'INV-HW-50K',
                'name' => 'Inverter Huawei SUN2000-50KTL-M3',
                'description' => 'Huawei 50kW String Inverter, Commercial',
                'category_id' => $catInv?->id,
                'unit' => 'unit',
                'purchase_price' => 55000000,
                'selling_price' => 72000000,
                'min_stock' => 3,
                'current_stock' => 10,
            ],
            // SMA (Premium)
            [
                'sku' => 'INV-SMA-10K',
                'name' => 'Inverter SMA Sunny Tripower 10.0',
                'description' => 'SMA 10kW String Inverter, 2 MPPT, German quality',
                'category_id' => $catInv?->id,
                'unit' => 'unit',
                'purchase_price' => 25000000,
                'selling_price' => 33000000,
                'min_stock' => 5,
                'current_stock' => 10,
            ],
            // Growatt (Budget)
            [
                'sku' => 'INV-GRW-5K',
                'name' => 'Inverter Growatt MIN 5000TL-X',
                'description' => 'Growatt 5kW String Inverter, Budget option',
                'category_id' => $catInv?->id,
                'unit' => 'unit',
                'purchase_price' => 7500000,
                'selling_price' => 10000000,
                'min_stock' => 20,
                'current_stock' => 50,
            ],
            [
                'sku' => 'INV-GRW-10K',
                'name' => 'Inverter Growatt MOD 10KTL3-X',
                'description' => 'Growatt 10kW String Inverter, 3 Phase',
                'category_id' => $catInv?->id,
                'unit' => 'unit',
                'purchase_price' => 12000000,
                'selling_price' => 16000000,
                'min_stock' => 15,
                'current_stock' => 40,
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                ['sku' => $product['sku']],
                array_merge($product, [
                    'type' => Product::TYPE_PRODUCT,
                    'tax_rate' => 11.00,
                    'is_taxable' => true,
                    'track_inventory' => true,
                    'is_active' => true,
                    'is_purchasable' => true,
                    'is_sellable' => true,
                    'inventory_account_id' => $this->inventoryAccountId,
                    'cogs_account_id' => $this->cogsAccountId,
                    'sales_account_id' => $this->salesAccountId,
                    'purchase_account_id' => $this->purchaseAccountId,
                ])
            );
        }

        $this->command->info('    Created 7 inverter products');
    }

    private function createMountingStructures(): void
    {
        $catMnt = ProductCategory::where('code', 'RM-MNT')->first();

        $products = [
            [
                'sku' => 'MNT-RAIL-4M',
                'name' => 'Rail Aluminium 4000mm',
                'description' => 'Mounting rail aluminium profile 40x40mm, 4 meter',
                'category_id' => $catMnt?->id,
                'unit' => 'pcs',
                'purchase_price' => 180000,
                'selling_price' => 250000,
                'min_stock' => 200,
                'current_stock' => 1000,
            ],
            [
                'sku' => 'MNT-CLMP-MID',
                'name' => 'Mid Clamp Universal',
                'description' => 'Mid clamp untuk panel 30-40mm frame',
                'category_id' => $catMnt?->id,
                'unit' => 'pcs',
                'purchase_price' => 8000,
                'selling_price' => 12000,
                'min_stock' => 1000,
                'current_stock' => 5000,
            ],
            [
                'sku' => 'MNT-CLMP-END',
                'name' => 'End Clamp Universal',
                'description' => 'End clamp untuk panel 30-40mm frame',
                'category_id' => $catMnt?->id,
                'unit' => 'pcs',
                'purchase_price' => 10000,
                'selling_price' => 15000,
                'min_stock' => 500,
                'current_stock' => 2500,
            ],
            [
                'sku' => 'MNT-L-FOOT',
                'name' => 'L-Foot Bracket Stainless',
                'description' => 'L-Foot untuk pemasangan di atap metal',
                'category_id' => $catMnt?->id,
                'unit' => 'pcs',
                'purchase_price' => 25000,
                'selling_price' => 35000,
                'min_stock' => 500,
                'current_stock' => 2000,
            ],
            [
                'sku' => 'MNT-TILT-SET',
                'name' => 'Tilt Leg Set (Pair)',
                'description' => 'Adjustable tilt bracket untuk flat roof, 10-30 degree',
                'category_id' => $catMnt?->id,
                'unit' => 'set',
                'purchase_price' => 150000,
                'selling_price' => 220000,
                'min_stock' => 100,
                'current_stock' => 500,
            ],
            [
                'sku' => 'MNT-GND-SCREW',
                'name' => 'Ground Screw 1500mm',
                'description' => 'Ground screw foundation untuk ground mount',
                'category_id' => $catMnt?->id,
                'unit' => 'pcs',
                'purchase_price' => 350000,
                'selling_price' => 480000,
                'min_stock' => 100,
                'current_stock' => 300,
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                ['sku' => $product['sku']],
                array_merge($product, [
                    'type' => Product::TYPE_PRODUCT,
                    'tax_rate' => 11.00,
                    'is_taxable' => true,
                    'track_inventory' => true,
                    'is_active' => true,
                    'is_purchasable' => true,
                    'is_sellable' => true,
                    'inventory_account_id' => $this->inventoryAccountId,
                    'cogs_account_id' => $this->cogsAccountId,
                    'sales_account_id' => $this->salesAccountId,
                    'purchase_account_id' => $this->purchaseAccountId,
                ])
            );
        }

        $this->command->info('    Created 6 mounting structure products');
    }

    private function createDcComponents(): void
    {
        $catDC = ProductCategory::where('code', 'RM-DC')->first();

        $products = [
            [
                'sku' => 'DC-CBL-6MM',
                'name' => 'Kabel DC Solar 6mm² (per meter)',
                'description' => 'Kabel DC solar single core 6mm², TUV certified',
                'category_id' => $catDC?->id,
                'unit' => 'm',
                'purchase_price' => 18000,
                'selling_price' => 25000,
                'min_stock' => 1000,
                'current_stock' => 5000,
            ],
            [
                'sku' => 'DC-CBL-4MM',
                'name' => 'Kabel DC Solar 4mm² (per meter)',
                'description' => 'Kabel DC solar single core 4mm², TUV certified',
                'category_id' => $catDC?->id,
                'unit' => 'm',
                'purchase_price' => 12000,
                'selling_price' => 17000,
                'min_stock' => 1000,
                'current_stock' => 3000,
            ],
            [
                'sku' => 'DC-MC4-PAIR',
                'name' => 'MC4 Connector Pair (Male + Female)',
                'description' => 'MC4 connector original Staubli/Multi-Contact',
                'category_id' => $catDC?->id,
                'unit' => 'pair',
                'purchase_price' => 25000,
                'selling_price' => 38000,
                'min_stock' => 500,
                'current_stock' => 2000,
            ],
            [
                'sku' => 'DC-MC4-Y',
                'name' => 'MC4 Y-Branch Connector',
                'description' => 'MC4 Y splitter untuk parallel string',
                'category_id' => $catDC?->id,
                'unit' => 'pair',
                'purchase_price' => 45000,
                'selling_price' => 65000,
                'min_stock' => 200,
                'current_stock' => 800,
            ],
            [
                'sku' => 'DC-FUSE-15A',
                'name' => 'DC Fuse 1000V 15A',
                'description' => 'PV fuse 10x38mm, 1000VDC 15A',
                'category_id' => $catDC?->id,
                'unit' => 'pcs',
                'purchase_price' => 35000,
                'selling_price' => 50000,
                'min_stock' => 100,
                'current_stock' => 500,
            ],
            [
                'sku' => 'DC-SPD-1000V',
                'name' => 'DC SPD Type II 1000V',
                'description' => 'Surge protection device DC side',
                'category_id' => $catDC?->id,
                'unit' => 'pcs',
                'purchase_price' => 450000,
                'selling_price' => 600000,
                'min_stock' => 50,
                'current_stock' => 200,
            ],
            [
                'sku' => 'DC-DISC-1000V',
                'name' => 'DC Disconnect Switch 1000V 32A',
                'description' => 'DC isolator switch for PV array',
                'category_id' => $catDC?->id,
                'unit' => 'pcs',
                'purchase_price' => 280000,
                'selling_price' => 380000,
                'min_stock' => 50,
                'current_stock' => 150,
            ],
            [
                'sku' => 'DC-CBOX-4STR',
                'name' => 'DC Combiner Box 4 String',
                'description' => 'Combiner box 4 string input dengan fuse & SPD',
                'category_id' => $catDC?->id,
                'unit' => 'unit',
                'purchase_price' => 1500000,
                'selling_price' => 2000000,
                'min_stock' => 10,
                'current_stock' => 40,
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                ['sku' => $product['sku']],
                array_merge($product, [
                    'type' => Product::TYPE_PRODUCT,
                    'tax_rate' => 11.00,
                    'is_taxable' => true,
                    'track_inventory' => true,
                    'is_active' => true,
                    'is_purchasable' => true,
                    'is_sellable' => true,
                    'inventory_account_id' => $this->inventoryAccountId,
                    'cogs_account_id' => $this->cogsAccountId,
                    'sales_account_id' => $this->salesAccountId,
                    'purchase_account_id' => $this->purchaseAccountId,
                ])
            );
        }

        $this->command->info('    Created 8 DC component products');
    }

    private function createMonitoringEquipment(): void
    {
        $catMon = ProductCategory::where('code', 'RM-MON')->first();

        $products = [
            [
                'sku' => 'MON-SMART-3P',
                'name' => 'Smart Meter 3 Phase Huawei',
                'description' => 'Huawei Smart Power Sensor DTSU666-H',
                'category_id' => $catMon?->id,
                'unit' => 'unit',
                'purchase_price' => 2500000,
                'selling_price' => 3300000,
                'min_stock' => 20,
                'current_stock' => 80,
            ],
            [
                'sku' => 'MON-DONGLE',
                'name' => 'Smart Dongle WiFi/4G',
                'description' => 'Huawei Smart Dongle untuk monitoring',
                'category_id' => $catMon?->id,
                'unit' => 'pcs',
                'purchase_price' => 850000,
                'selling_price' => 1100000,
                'min_stock' => 30,
                'current_stock' => 100,
            ],
            [
                'sku' => 'MON-CT-200A',
                'name' => 'Current Transformer 200A/5A',
                'description' => 'CT untuk smart meter, ratio 200/5',
                'category_id' => $catMon?->id,
                'unit' => 'set',
                'purchase_price' => 350000,
                'selling_price' => 480000,
                'min_stock' => 20,
                'current_stock' => 60,
            ],
            [
                'sku' => 'MON-DISPLAY',
                'name' => 'Energy Display Monitor',
                'description' => 'Local display untuk production monitoring',
                'category_id' => $catMon?->id,
                'unit' => 'unit',
                'purchase_price' => 1800000,
                'selling_price' => 2400000,
                'min_stock' => 10,
                'current_stock' => 30,
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                ['sku' => $product['sku']],
                array_merge($product, [
                    'type' => Product::TYPE_PRODUCT,
                    'tax_rate' => 11.00,
                    'is_taxable' => true,
                    'track_inventory' => true,
                    'is_active' => true,
                    'is_purchasable' => true,
                    'is_sellable' => true,
                    'inventory_account_id' => $this->inventoryAccountId,
                    'cogs_account_id' => $this->cogsAccountId,
                    'sales_account_id' => $this->salesAccountId,
                    'purchase_account_id' => $this->purchaseAccountId,
                ])
            );
        }

        $this->command->info('    Created 4 monitoring equipment products');
    }

    private function createFinishedSystems(): void
    {
        $catPLTS = ProductCategory::where('code', 'FG-PLTS')->first();

        $finishedGoods = [
            [
                'sku' => 'FG-PLTS-5KWP',
                'name' => 'Sistem PLTS On-Grid 5 kWp',
                'description' => 'Complete rooftop solar system 5 kWp, includes: panels, inverter, mounting, installation',
                'category_id' => $catPLTS?->id,
                'unit' => 'system',
                'purchase_price' => 0,
                'selling_price' => 65000000,
                'min_stock' => 0,
                'current_stock' => 0,
            ],
            [
                'sku' => 'FG-PLTS-10KWP',
                'name' => 'Sistem PLTS On-Grid 10 kWp',
                'description' => 'Complete rooftop solar system 10 kWp, commercial grade',
                'category_id' => $catPLTS?->id,
                'unit' => 'system',
                'purchase_price' => 0,
                'selling_price' => 120000000,
                'min_stock' => 0,
                'current_stock' => 0,
            ],
            [
                'sku' => 'FG-PLTS-20KWP',
                'name' => 'Sistem PLTS On-Grid 20 kWp',
                'description' => 'Complete rooftop solar system 20 kWp, industrial grade',
                'category_id' => $catPLTS?->id,
                'unit' => 'system',
                'purchase_price' => 0,
                'selling_price' => 220000000,
                'min_stock' => 0,
                'current_stock' => 0,
            ],
            [
                'sku' => 'FG-PLTS-50KWP',
                'name' => 'Sistem PLTS On-Grid 50 kWp',
                'description' => 'Complete rooftop/ground solar system 50 kWp, factory scale',
                'category_id' => $catPLTS?->id,
                'unit' => 'system',
                'purchase_price' => 0,
                'selling_price' => 500000000,
                'min_stock' => 0,
                'current_stock' => 0,
            ],
            [
                'sku' => 'FG-PLTS-100KWP',
                'name' => 'Sistem PLTS On-Grid 100 kWp',
                'description' => 'Complete rooftop/ground solar system 100 kWp, large industrial',
                'category_id' => $catPLTS?->id,
                'unit' => 'system',
                'purchase_price' => 0,
                'selling_price' => 950000000,
                'min_stock' => 0,
                'current_stock' => 0,
            ],
        ];

        foreach ($finishedGoods as $product) {
            Product::updateOrCreate(
                ['sku' => $product['sku']],
                array_merge($product, [
                    'type' => Product::TYPE_PRODUCT,
                    'tax_rate' => 11.00,
                    'is_taxable' => true,
                    'track_inventory' => false,
                    'is_active' => true,
                    'is_purchasable' => false,
                    'is_sellable' => true,
                    'inventory_account_id' => $this->inventoryAccountId,
                    'cogs_account_id' => $this->cogsAccountId,
                    'sales_account_id' => $this->salesAccountId,
                ])
            );
        }

        $this->command->info('    Created 5 finished PLTS system products');
    }

    private function createServices(): void
    {
        $catSurvey = ProductCategory::where('code', 'SVC-SRV')->first();
        $catOM = ProductCategory::where('code', 'SVC-OM')->first();
        $catCleaning = ProductCategory::where('code', 'SVC-CLN')->first();

        $services = [
            [
                'sku' => 'SVC-SURVEY',
                'name' => 'Jasa Survey & Assessment',
                'description' => 'Site survey, roof assessment, shading analysis',
                'category_id' => $catSurvey?->id,
                'unit' => 'visit',
                'purchase_price' => 500000,
                'selling_price' => 1500000,
            ],
            [
                'sku' => 'SVC-DESIGN',
                'name' => 'Jasa Desain Sistem PV',
                'description' => 'PV system design, 3D layout, electrical diagram',
                'category_id' => $catSurvey?->id,
                'unit' => 'project',
                'purchase_price' => 1000000,
                'selling_price' => 3500000,
            ],
            [
                'sku' => 'SVC-PROPOSAL',
                'name' => 'Jasa Proposal & ROI Analysis',
                'description' => 'Financial proposal dengan ROI, payback period, ESG impact',
                'category_id' => $catSurvey?->id,
                'unit' => 'project',
                'purchase_price' => 500000,
                'selling_price' => 2000000,
            ],
            [
                'sku' => 'SVC-OM-MTH',
                'name' => 'Jasa O&M Bulanan',
                'description' => 'Monthly O&M service, monitoring, preventive maintenance',
                'category_id' => $catOM?->id,
                'unit' => 'bulan',
                'purchase_price' => 300000,
                'selling_price' => 750000,
            ],
            [
                'sku' => 'SVC-OM-YR',
                'name' => 'Jasa O&M Tahunan',
                'description' => 'Annual O&M contract, includes 4x site visits',
                'category_id' => $catOM?->id,
                'unit' => 'tahun',
                'purchase_price' => 3000000,
                'selling_price' => 7500000,
            ],
            [
                'sku' => 'SVC-CLEAN-PKG',
                'name' => 'Jasa Cleaning Panel per kWp',
                'description' => 'Solar panel cleaning service, per kWp installed',
                'category_id' => $catCleaning?->id,
                'unit' => 'kWp',
                'purchase_price' => 25000,
                'selling_price' => 75000,
            ],
        ];

        foreach ($services as $service) {
            Product::updateOrCreate(
                ['sku' => $service['sku']],
                array_merge($service, [
                    'type' => Product::TYPE_SERVICE,
                    'tax_rate' => 11.00,
                    'is_taxable' => true,
                    'track_inventory' => false,
                    'min_stock' => 0,
                    'current_stock' => 0,
                    'is_active' => true,
                    'is_purchasable' => true,
                    'is_sellable' => true,
                    'sales_account_id' => Account::where('code', '4-1002')->value('id'),
                    'purchase_account_id' => $this->purchaseAccountId,
                ])
            );
        }

        $this->command->info('    Created 6 solar service products');
    }

    private function createBoms(): void
    {
        $this->createPlts10KwpBom();
        $this->createPlts50KwpBom();
        $this->createPlts50KwpVariants();

        $this->command->info('    Created 2 PLTS BOMs with items');
        $this->command->info('    Created 1 BOM Variant Group with 3 comparison options');
    }

    /**
     * BOM for 10 kWp Rooftop System
     * ~18 panels x 555Wp = 9.99 kWp
     */
    private function createPlts10KwpBom(): void
    {
        $product = Product::where('sku', 'FG-PLTS-10KWP')->first();
        if (! $product) {
            return;
        }

        $bom = Bom::updateOrCreate(
            ['bom_number' => 'BOM-PLTS-10KWP-NUSA'],
            [
                'name' => 'BOM PLTS 10 kWp (NUSA)',
                'description' => 'Bill of Materials untuk PLTS Rooftop 10 kWp menggunakan panel NUSA',
                'product_id' => $product->id,
                'output_quantity' => 1,
                'output_unit' => 'system',
                'status' => Bom::STATUS_ACTIVE,
                'version' => '1.0',
            ]
        );

        $bom->items()->delete();

        // 10 kWp = ~18 panels @ 550Wp
        $items = [
            ['sku' => 'PV-NUSA-550', 'qty' => 18, 'desc' => 'Panel Surya NUSA 550Wp'],
            ['sku' => 'INV-HW-10K', 'qty' => 1, 'desc' => 'Inverter Huawei 10kW'],
            ['sku' => 'MNT-RAIL-4M', 'qty' => 18, 'desc' => 'Mounting Rail 4m'],
            ['sku' => 'MNT-CLMP-MID', 'qty' => 34, 'desc' => 'Mid Clamp'],
            ['sku' => 'MNT-CLMP-END', 'qty' => 4, 'desc' => 'End Clamp'],
            ['sku' => 'MNT-L-FOOT', 'qty' => 36, 'desc' => 'L-Foot Bracket'],
            ['sku' => 'DC-CBL-6MM', 'qty' => 100, 'desc' => 'Kabel DC 6mm²'],
            ['sku' => 'DC-MC4-PAIR', 'qty' => 20, 'desc' => 'MC4 Connector'],
            ['sku' => 'DC-SPD-1000V', 'qty' => 1, 'desc' => 'DC Surge Protection'],
            ['sku' => 'DC-DISC-1000V', 'qty' => 1, 'desc' => 'DC Disconnect Switch'],
            ['sku' => 'MON-SMART-3P', 'qty' => 1, 'desc' => 'Smart Meter 3 Phase'],
            ['sku' => 'MON-DONGLE', 'qty' => 1, 'desc' => 'WiFi Monitoring Dongle'],
        ];

        $totalCost = 0;
        $sortOrder = 1;
        foreach ($items as $item) {
            $material = Product::where('sku', $item['sku'])->first();
            if ($material) {
                $itemCost = (int) ($material->purchase_price * $item['qty']);
                $totalCost += $itemCost;

                BomItem::create([
                    'bom_id' => $bom->id,
                    'type' => BomItem::TYPE_MATERIAL,
                    'product_id' => $material->id,
                    'description' => $item['desc'],
                    'quantity' => $item['qty'],
                    'unit' => $material->unit,
                    'unit_cost' => $material->purchase_price,
                    'total_cost' => $itemCost,
                    'waste_percentage' => 5,
                    'sort_order' => $sortOrder++,
                ]);
            }
        }

        // AC Components (direct cost, not from inventory)
        $acCost = 2500000; // AC cable, MCB, SPD AC side
        $totalCost += $acCost;
        BomItem::create([
            'bom_id' => $bom->id,
            'type' => BomItem::TYPE_MATERIAL,
            'description' => 'AC Components (Cable, MCB, SPD AC)',
            'quantity' => 1,
            'unit' => 'set',
            'unit_cost' => $acCost,
            'total_cost' => $acCost,
            'sort_order' => $sortOrder++,
        ]);

        // Labor cost
        $laborCost = 8000000; // Installation labor for 10 kWp
        BomItem::create([
            'bom_id' => $bom->id,
            'type' => BomItem::TYPE_LABOR,
            'description' => 'Tenaga Kerja Instalasi (3 hari x 4 orang)',
            'quantity' => 12,
            'unit' => 'man-day',
            'unit_cost' => 666667,
            'total_cost' => $laborCost,
            'sort_order' => $sortOrder++,
        ]);

        // Update BOM totals
        $overheadCost = (int) (($totalCost + $laborCost) * 0.1);
        $bom->update([
            'total_material_cost' => $totalCost,
            'total_labor_cost' => $laborCost,
            'total_overhead_cost' => $overheadCost,
            'total_cost' => $totalCost + $laborCost + $overheadCost,
            'unit_cost' => $totalCost + $laborCost + $overheadCost,
        ]);
    }

    /**
     * BOM for 50 kWp Commercial Rooftop System
     * ~90 panels x 555Wp = 49.95 kWp
     */
    private function createPlts50KwpBom(): void
    {
        $product = Product::where('sku', 'FG-PLTS-50KWP')->first();
        if (! $product) {
            return;
        }

        $bom = Bom::updateOrCreate(
            ['bom_number' => 'BOM-PLTS-50KWP-NUSA'],
            [
                'name' => 'BOM PLTS 50 kWp Commercial (NUSA)',
                'description' => 'Bill of Materials untuk PLTS Commercial 50 kWp',
                'product_id' => $product->id,
                'output_quantity' => 1,
                'output_unit' => 'system',
                'status' => Bom::STATUS_ACTIVE,
                'version' => '1.0',
            ]
        );

        $bom->items()->delete();

        // 50 kWp = ~90 panels @ 555Wp
        $items = [
            ['sku' => 'PV-NUSA-550', 'qty' => 90, 'desc' => 'Panel Surya NUSA 550Wp'],
            ['sku' => 'INV-HW-50K', 'qty' => 1, 'desc' => 'Inverter Huawei 50kW'],
            ['sku' => 'MNT-RAIL-4M', 'qty' => 90, 'desc' => 'Mounting Rail 4m'],
            ['sku' => 'MNT-CLMP-MID', 'qty' => 178, 'desc' => 'Mid Clamp'],
            ['sku' => 'MNT-CLMP-END', 'qty' => 4, 'desc' => 'End Clamp'],
            ['sku' => 'MNT-L-FOOT', 'qty' => 180, 'desc' => 'L-Foot Bracket'],
            ['sku' => 'DC-CBL-6MM', 'qty' => 500, 'desc' => 'Kabel DC 6mm²'],
            ['sku' => 'DC-MC4-PAIR', 'qty' => 100, 'desc' => 'MC4 Connector'],
            ['sku' => 'DC-MC4-Y', 'qty' => 20, 'desc' => 'Y-Branch Connector'],
            ['sku' => 'DC-CBOX-4STR', 'qty' => 3, 'desc' => 'DC Combiner Box 4 String'],
            ['sku' => 'DC-SPD-1000V', 'qty' => 2, 'desc' => 'DC Surge Protection'],
            ['sku' => 'DC-DISC-1000V', 'qty' => 2, 'desc' => 'DC Disconnect Switch'],
            ['sku' => 'MON-SMART-3P', 'qty' => 1, 'desc' => 'Smart Meter 3 Phase'],
            ['sku' => 'MON-CT-200A', 'qty' => 1, 'desc' => 'CT Set 200A'],
            ['sku' => 'MON-DONGLE', 'qty' => 1, 'desc' => 'WiFi Monitoring Dongle'],
            ['sku' => 'MON-DISPLAY', 'qty' => 1, 'desc' => 'Energy Display Monitor'],
        ];

        $totalCost = 0;
        $sortOrder = 1;
        foreach ($items as $item) {
            $material = Product::where('sku', $item['sku'])->first();
            if ($material) {
                $itemCost = (int) ($material->purchase_price * $item['qty']);
                $totalCost += $itemCost;

                BomItem::create([
                    'bom_id' => $bom->id,
                    'type' => BomItem::TYPE_MATERIAL,
                    'product_id' => $material->id,
                    'description' => $item['desc'],
                    'quantity' => $item['qty'],
                    'unit' => $material->unit,
                    'unit_cost' => $material->purchase_price,
                    'total_cost' => $itemCost,
                    'waste_percentage' => 5,
                    'sort_order' => $sortOrder++,
                ]);
            }
        }

        // AC Components
        $acCost = 12000000;
        $totalCost += $acCost;
        BomItem::create([
            'bom_id' => $bom->id,
            'type' => BomItem::TYPE_MATERIAL,
            'description' => 'AC Components (Cable NYY 4x70, Panel AC, MCCB, SPD)',
            'quantity' => 1,
            'unit' => 'set',
            'unit_cost' => $acCost,
            'total_cost' => $acCost,
            'sort_order' => $sortOrder++,
        ]);

        // Labor cost
        $laborCost = 35000000; // Installation labor for 50 kWp
        BomItem::create([
            'bom_id' => $bom->id,
            'type' => BomItem::TYPE_LABOR,
            'description' => 'Tenaga Kerja Instalasi (10 hari x 6 orang)',
            'quantity' => 60,
            'unit' => 'man-day',
            'unit_cost' => 583333,
            'total_cost' => $laborCost,
            'sort_order' => $sortOrder++,
        ]);

        // Update BOM totals
        $overheadCost = (int) (($totalCost + $laborCost) * 0.1);
        $bom->update([
            'total_material_cost' => $totalCost,
            'total_labor_cost' => $laborCost,
            'total_overhead_cost' => $overheadCost,
            'total_cost' => $totalCost + $laborCost + $overheadCost,
            'unit_cost' => $totalCost + $laborCost + $overheadCost,
        ]);
    }

    /**
     * Create BOM variants for 50 kWp system for comparison.
     * Demonstrates the Multi-BOM Comparison feature.
     *
     * Budget: Growatt inverter + NUSA panels (most affordable)
     * Standard: Huawei inverter + NUSA panels (balanced)
     * Premium: SMA inverter + LONGi panels (highest performance)
     */
    private function createPlts50KwpVariants(): void
    {
        $product = Product::where('sku', 'FG-PLTS-50KWP')->first();
        if (! $product) {
            return;
        }

        // Get the existing NUSA BOM (this is our "Standard" option)
        $standardBom = Bom::where('bom_number', 'BOM-PLTS-50KWP-NUSA')->first();
        if (! $standardBom) {
            return;
        }

        // Create variant group
        $variantGroup = BomVariantGroup::updateOrCreate(
            ['name' => 'PLTS 50 kWp Material Options'],
            [
                'product_id' => $product->id,
                'description' => 'Perbandingan konfigurasi material untuk sistem PLTS 50 kWp',
                'comparison_notes' => 'Budget: Growatt + NUSA (hemat biaya), Standard: Huawei + NUSA (balanced), Premium: SMA + LONGi (performa maksimal)',
                'status' => BomVariantGroup::STATUS_ACTIVE,
            ]
        );

        // Update Standard BOM as part of the group
        $standardBom->update([
            'variant_group_id' => $variantGroup->id,
            'variant_name' => 'Standard',
            'variant_label' => 'Huawei + NUSA',
            'is_primary_variant' => true,
            'variant_sort_order' => 1,
        ]);

        // Create Budget variant (Growatt + NUSA)
        $this->createBudgetVariant($product, $variantGroup);

        // Create Premium variant (SMA + LONGi)
        $this->createPremiumVariant($product, $variantGroup);
    }

    /**
     * Budget variant: Growatt inverter with NUSA panels.
     */
    private function createBudgetVariant(Product $product, BomVariantGroup $variantGroup): void
    {
        $bom = Bom::updateOrCreate(
            ['bom_number' => 'BOM-PLTS-50KWP-BUDGET'],
            [
                'name' => 'BOM PLTS 50 kWp Budget (Growatt + NUSA)',
                'description' => 'Opsi hemat: Growatt inverter dengan panel NUSA',
                'product_id' => $product->id,
                'output_quantity' => 1,
                'output_unit' => 'system',
                'status' => Bom::STATUS_ACTIVE,
                'version' => '1.0',
                'variant_group_id' => $variantGroup->id,
                'variant_name' => 'Budget',
                'variant_label' => 'Growatt + NUSA',
                'is_primary_variant' => false,
                'variant_sort_order' => 0,
            ]
        );

        $bom->items()->delete();

        // Use Growatt inverter (10kW x 5 = 50kW) and NUSA panels
        $items = [
            ['sku' => 'PV-NUSA-550', 'qty' => 90, 'desc' => 'Panel Surya NUSA 550Wp'],
            ['sku' => 'INV-GRW-10K', 'qty' => 5, 'desc' => 'Inverter Growatt 10kW (5 unit)'],
            ['sku' => 'MNT-RAIL-4M', 'qty' => 90, 'desc' => 'Mounting Rail 4m'],
            ['sku' => 'MNT-CLMP-MID', 'qty' => 178, 'desc' => 'Mid Clamp'],
            ['sku' => 'MNT-CLMP-END', 'qty' => 4, 'desc' => 'End Clamp'],
            ['sku' => 'MNT-L-FOOT', 'qty' => 180, 'desc' => 'L-Foot Bracket'],
            ['sku' => 'DC-CBL-6MM', 'qty' => 500, 'desc' => 'Kabel DC 6mm²'],
            ['sku' => 'DC-MC4-PAIR', 'qty' => 100, 'desc' => 'MC4 Connector'],
            ['sku' => 'DC-MC4-Y', 'qty' => 20, 'desc' => 'Y-Branch Connector'],
            ['sku' => 'DC-CBOX-4STR', 'qty' => 3, 'desc' => 'DC Combiner Box 4 String'],
            ['sku' => 'DC-SPD-1000V', 'qty' => 5, 'desc' => 'DC Surge Protection (per inverter)'],
            ['sku' => 'DC-DISC-1000V', 'qty' => 5, 'desc' => 'DC Disconnect Switch (per inverter)'],
            ['sku' => 'MON-SMART-3P', 'qty' => 1, 'desc' => 'Smart Meter 3 Phase'],
            ['sku' => 'MON-CT-200A', 'qty' => 1, 'desc' => 'CT Set 200A'],
            ['sku' => 'MON-DONGLE', 'qty' => 5, 'desc' => 'WiFi Monitoring Dongle (per inverter)'],
        ];

        $this->createBomItems($bom, $items, 10000000, 30000000);
    }

    /**
     * Premium variant: SMA inverter with LONGi panels.
     */
    private function createPremiumVariant(Product $product, BomVariantGroup $variantGroup): void
    {
        $bom = Bom::updateOrCreate(
            ['bom_number' => 'BOM-PLTS-50KWP-PREMIUM'],
            [
                'name' => 'BOM PLTS 50 kWp Premium (SMA + LONGi)',
                'description' => 'Opsi premium: SMA inverter dengan panel LONGi bifacial',
                'product_id' => $product->id,
                'output_quantity' => 1,
                'output_unit' => 'system',
                'status' => Bom::STATUS_ACTIVE,
                'version' => '1.0',
                'variant_group_id' => $variantGroup->id,
                'variant_name' => 'Premium',
                'variant_label' => 'SMA + LONGi',
                'is_primary_variant' => false,
                'variant_sort_order' => 2,
            ]
        );

        $bom->items()->delete();

        // Use SMA inverter (10kW x 5 = 50kW) and LONGi bifacial panels
        $items = [
            ['sku' => 'PV-LONGI-555', 'qty' => 90, 'desc' => 'Panel Surya LONGi 555Wp Hi-MO 5 Bifacial'],
            ['sku' => 'INV-SMA-10K', 'qty' => 5, 'desc' => 'Inverter SMA Sunny Tripower 10kW (5 unit)'],
            ['sku' => 'MNT-RAIL-4M', 'qty' => 90, 'desc' => 'Mounting Rail 4m'],
            ['sku' => 'MNT-CLMP-MID', 'qty' => 178, 'desc' => 'Mid Clamp'],
            ['sku' => 'MNT-CLMP-END', 'qty' => 4, 'desc' => 'End Clamp'],
            ['sku' => 'MNT-L-FOOT', 'qty' => 180, 'desc' => 'L-Foot Bracket'],
            ['sku' => 'DC-CBL-6MM', 'qty' => 500, 'desc' => 'Kabel DC 6mm²'],
            ['sku' => 'DC-MC4-PAIR', 'qty' => 100, 'desc' => 'MC4 Connector'],
            ['sku' => 'DC-MC4-Y', 'qty' => 20, 'desc' => 'Y-Branch Connector'],
            ['sku' => 'DC-CBOX-4STR', 'qty' => 3, 'desc' => 'DC Combiner Box 4 String'],
            ['sku' => 'DC-SPD-1000V', 'qty' => 5, 'desc' => 'DC Surge Protection (per inverter)'],
            ['sku' => 'DC-DISC-1000V', 'qty' => 5, 'desc' => 'DC Disconnect Switch (per inverter)'],
            ['sku' => 'MON-SMART-3P', 'qty' => 1, 'desc' => 'Smart Meter 3 Phase'],
            ['sku' => 'MON-CT-200A', 'qty' => 1, 'desc' => 'CT Set 200A'],
            ['sku' => 'MON-DONGLE', 'qty' => 5, 'desc' => 'WiFi Monitoring Dongle (per inverter)'],
            ['sku' => 'MON-DISPLAY', 'qty' => 1, 'desc' => 'Energy Display Monitor'],
        ];

        $this->createBomItems($bom, $items, 15000000, 40000000);
    }

    /**
     * Helper to create BOM items and update totals.
     *
     * @param  array<int, array{sku: string, qty: int, desc: string}>  $items
     */
    private function createBomItems(Bom $bom, array $items, int $acCost, int $laborCost): void
    {
        $totalCost = 0;
        $sortOrder = 1;

        foreach ($items as $item) {
            $material = Product::where('sku', $item['sku'])->first();
            if ($material) {
                $itemCost = (int) ($material->purchase_price * $item['qty']);
                $totalCost += $itemCost;

                BomItem::create([
                    'bom_id' => $bom->id,
                    'type' => BomItem::TYPE_MATERIAL,
                    'product_id' => $material->id,
                    'description' => $item['desc'],
                    'quantity' => $item['qty'],
                    'unit' => $material->unit,
                    'unit_cost' => $material->purchase_price,
                    'total_cost' => $itemCost,
                    'waste_percentage' => 5,
                    'sort_order' => $sortOrder++,
                ]);
            }
        }

        // AC Components
        $totalCost += $acCost;
        BomItem::create([
            'bom_id' => $bom->id,
            'type' => BomItem::TYPE_MATERIAL,
            'description' => 'AC Components (Cable NYY 4x70, Panel AC, MCCB, SPD)',
            'quantity' => 1,
            'unit' => 'set',
            'unit_cost' => $acCost,
            'total_cost' => $acCost,
            'sort_order' => $sortOrder++,
        ]);

        // Labor cost
        BomItem::create([
            'bom_id' => $bom->id,
            'type' => BomItem::TYPE_LABOR,
            'description' => 'Tenaga Kerja Instalasi',
            'quantity' => 1,
            'unit' => 'lot',
            'unit_cost' => $laborCost,
            'total_cost' => $laborCost,
            'sort_order' => $sortOrder++,
        ]);

        // Update BOM totals
        $overheadCost = (int) (($totalCost + $laborCost) * 0.1);
        $bom->update([
            'total_material_cost' => $totalCost,
            'total_labor_cost' => $laborCost,
            'total_overhead_cost' => $overheadCost,
            'total_cost' => $totalCost + $laborCost + $overheadCost,
            'unit_cost' => $totalCost + $laborCost + $overheadCost,
        ]);
    }
}
