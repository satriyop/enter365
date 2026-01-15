<?php

namespace Database\Seeders\Demo\Vahana;

use App\Models\Accounting\Account;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductCategory;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use Illuminate\Database\Seeder;

class VahanaProductSeeder extends Seeder
{
    private ?int $inventoryAccountId = null;

    private ?int $cogsAccountId = null;

    private ?int $salesAccountId = null;

    private ?int $purchaseAccountId = null;

    /**
     * Seed products and BOMs for PT Vahana (Electrical Panel Maker).
     */
    public function run(): void
    {
        $this->loadAccounts();
        $this->createRawMaterials();
        $this->createFinishedGoods();
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

    private function createRawMaterials(): void
    {
        // Get categories
        $catElectrical = ProductCategory::where('code', 'RM-EL')->first();
        $catCable = ProductCategory::where('code', 'RM-CB')->first();
        $catBusbar = ProductCategory::where('code', 'RM-BB')->first();
        $catEnclosure = ProductCategory::where('code', 'RM-EN')->first();
        $catAccessories = ProductCategory::where('code', 'RM-AC')->first();

        $products = [
            // Electrical Components - MCB/MCCB
            [
                'sku' => 'EL-MCB-1P16',
                'name' => 'MCB 1 Phase 16A Schneider',
                'category_id' => $catElectrical?->id,
                'unit' => 'pcs',
                'purchase_price' => 85000,
                'selling_price' => 120000,
                'min_stock' => 50,
                'current_stock' => 200,
            ],
            [
                'sku' => 'EL-MCB-3P32',
                'name' => 'MCB 3 Phase 32A Schneider',
                'category_id' => $catElectrical?->id,
                'unit' => 'pcs',
                'purchase_price' => 350000,
                'selling_price' => 475000,
                'min_stock' => 30,
                'current_stock' => 100,
            ],
            [
                'sku' => 'EL-MCCB-100',
                'name' => 'MCCB 100A 3P Schneider NSX100F',
                'category_id' => $catElectrical?->id,
                'unit' => 'pcs',
                'purchase_price' => 2500000,
                'selling_price' => 3250000,
                'min_stock' => 10,
                'current_stock' => 25,
            ],
            [
                'sku' => 'EL-MCCB-250',
                'name' => 'MCCB 250A 3P Schneider NSX250F',
                'category_id' => $catElectrical?->id,
                'unit' => 'pcs',
                'purchase_price' => 4500000,
                'selling_price' => 5850000,
                'min_stock' => 5,
                'current_stock' => 15,
            ],
            [
                'sku' => 'EL-MCCB-400',
                'name' => 'MCCB 400A 3P Schneider NSX400F',
                'category_id' => $catElectrical?->id,
                'unit' => 'pcs',
                'purchase_price' => 8500000,
                'selling_price' => 11050000,
                'min_stock' => 3,
                'current_stock' => 10,
            ],
            // Contactors & Relays
            [
                'sku' => 'EL-CTR-25',
                'name' => 'Kontaktor 25A Schneider LC1D25',
                'category_id' => $catElectrical?->id,
                'unit' => 'pcs',
                'purchase_price' => 750000,
                'selling_price' => 975000,
                'min_stock' => 20,
                'current_stock' => 50,
            ],
            [
                'sku' => 'EL-CTR-50',
                'name' => 'Kontaktor 50A Schneider LC1D50',
                'category_id' => $catElectrical?->id,
                'unit' => 'pcs',
                'purchase_price' => 1250000,
                'selling_price' => 1625000,
                'min_stock' => 15,
                'current_stock' => 30,
            ],
            [
                'sku' => 'EL-TOR-25',
                'name' => 'Thermal Overload Relay 18-25A',
                'category_id' => $catElectrical?->id,
                'unit' => 'pcs',
                'purchase_price' => 450000,
                'selling_price' => 585000,
                'min_stock' => 20,
                'current_stock' => 40,
            ],
            // Cables
            [
                'sku' => 'CB-NYY-4X16',
                'name' => 'Kabel NYY 4x16mm2 Supreme',
                'category_id' => $catCable?->id,
                'unit' => 'm',
                'purchase_price' => 125000,
                'selling_price' => 162500,
                'min_stock' => 500,
                'current_stock' => 2000,
            ],
            [
                'sku' => 'CB-NYY-4X35',
                'name' => 'Kabel NYY 4x35mm2 Supreme',
                'category_id' => $catCable?->id,
                'unit' => 'm',
                'purchase_price' => 275000,
                'selling_price' => 357500,
                'min_stock' => 300,
                'current_stock' => 1000,
            ],
            [
                'sku' => 'CB-NYY-4X70',
                'name' => 'Kabel NYY 4x70mm2 Supreme',
                'category_id' => $catCable?->id,
                'unit' => 'm',
                'purchase_price' => 550000,
                'selling_price' => 715000,
                'min_stock' => 200,
                'current_stock' => 500,
            ],
            [
                'sku' => 'CB-NYAF-15',
                'name' => 'Kabel NYAF 1.5mm2 (Control)',
                'category_id' => $catCable?->id,
                'unit' => 'm',
                'purchase_price' => 5500,
                'selling_price' => 7500,
                'min_stock' => 1000,
                'current_stock' => 5000,
            ],
            [
                'sku' => 'CB-NYAF-25',
                'name' => 'Kabel NYAF 2.5mm2 (Control)',
                'category_id' => $catCable?->id,
                'unit' => 'm',
                'purchase_price' => 8500,
                'selling_price' => 11000,
                'min_stock' => 1000,
                'current_stock' => 4000,
            ],
            // Busbar & Connections
            [
                'sku' => 'BB-CU-30X5',
                'name' => 'Busbar Copper 30x5mm (per meter)',
                'category_id' => $catBusbar?->id,
                'unit' => 'm',
                'purchase_price' => 350000,
                'selling_price' => 455000,
                'min_stock' => 50,
                'current_stock' => 200,
            ],
            [
                'sku' => 'BB-CU-40X5',
                'name' => 'Busbar Copper 40x5mm (per meter)',
                'category_id' => $catBusbar?->id,
                'unit' => 'm',
                'purchase_price' => 475000,
                'selling_price' => 617500,
                'min_stock' => 50,
                'current_stock' => 150,
            ],
            [
                'sku' => 'BB-CU-50X5',
                'name' => 'Busbar Copper 50x5mm (per meter)',
                'category_id' => $catBusbar?->id,
                'unit' => 'm',
                'purchase_price' => 600000,
                'selling_price' => 780000,
                'min_stock' => 30,
                'current_stock' => 100,
            ],
            [
                'sku' => 'BB-LUG-70',
                'name' => 'Cable Lug 70mm2',
                'category_id' => $catBusbar?->id,
                'unit' => 'pcs',
                'purchase_price' => 25000,
                'selling_price' => 35000,
                'min_stock' => 100,
                'current_stock' => 500,
            ],
            [
                'sku' => 'BB-TERM-16',
                'name' => 'Terminal Block 16mm2',
                'category_id' => $catBusbar?->id,
                'unit' => 'pcs',
                'purchase_price' => 15000,
                'selling_price' => 22000,
                'min_stock' => 200,
                'current_stock' => 1000,
            ],
            // Enclosures
            [
                'sku' => 'EN-800X600',
                'name' => 'Panel Enclosure 800x600x250mm',
                'category_id' => $catEnclosure?->id,
                'unit' => 'unit',
                'purchase_price' => 2500000,
                'selling_price' => 3250000,
                'min_stock' => 5,
                'current_stock' => 20,
            ],
            [
                'sku' => 'EN-1000X800',
                'name' => 'Panel Enclosure 1000x800x300mm',
                'category_id' => $catEnclosure?->id,
                'unit' => 'unit',
                'purchase_price' => 3500000,
                'selling_price' => 4550000,
                'min_stock' => 5,
                'current_stock' => 15,
            ],
            [
                'sku' => 'EN-1200X800',
                'name' => 'Panel Enclosure 1200x800x300mm',
                'category_id' => $catEnclosure?->id,
                'unit' => 'unit',
                'purchase_price' => 4500000,
                'selling_price' => 5850000,
                'min_stock' => 3,
                'current_stock' => 10,
            ],
            [
                'sku' => 'EN-2000X800',
                'name' => 'Panel Enclosure 2000x800x600mm (Free Standing)',
                'category_id' => $catEnclosure?->id,
                'unit' => 'unit',
                'purchase_price' => 12000000,
                'selling_price' => 15600000,
                'min_stock' => 2,
                'current_stock' => 5,
            ],
            // Accessories
            [
                'sku' => 'AC-DIN-1M',
                'name' => 'DIN Rail 35mm (1 meter)',
                'category_id' => $catAccessories?->id,
                'unit' => 'pcs',
                'purchase_price' => 25000,
                'selling_price' => 35000,
                'min_stock' => 100,
                'current_stock' => 500,
            ],
            [
                'sku' => 'AC-DUCT-40',
                'name' => 'Cable Duct 40x40mm (2 meter)',
                'category_id' => $catAccessories?->id,
                'unit' => 'pcs',
                'purchase_price' => 45000,
                'selling_price' => 60000,
                'min_stock' => 50,
                'current_stock' => 200,
            ],
            [
                'sku' => 'AC-DUCT-60',
                'name' => 'Cable Duct 60x60mm (2 meter)',
                'category_id' => $catAccessories?->id,
                'unit' => 'pcs',
                'purchase_price' => 65000,
                'selling_price' => 85000,
                'min_stock' => 50,
                'current_stock' => 150,
            ],
            [
                'sku' => 'AC-LABEL-SET',
                'name' => 'Label Set (Marker + Holder)',
                'category_id' => $catAccessories?->id,
                'unit' => 'set',
                'purchase_price' => 75000,
                'selling_price' => 100000,
                'min_stock' => 30,
                'current_stock' => 100,
            ],
            [
                'sku' => 'AC-PILOT-G',
                'name' => 'Pilot Lamp Green 22mm',
                'category_id' => $catAccessories?->id,
                'unit' => 'pcs',
                'purchase_price' => 35000,
                'selling_price' => 50000,
                'min_stock' => 50,
                'current_stock' => 200,
            ],
            [
                'sku' => 'AC-PILOT-R',
                'name' => 'Pilot Lamp Red 22mm',
                'category_id' => $catAccessories?->id,
                'unit' => 'pcs',
                'purchase_price' => 35000,
                'selling_price' => 50000,
                'min_stock' => 50,
                'current_stock' => 200,
            ],
            [
                'sku' => 'AC-PUSH-NO',
                'name' => 'Push Button NO 22mm',
                'category_id' => $catAccessories?->id,
                'unit' => 'pcs',
                'purchase_price' => 45000,
                'selling_price' => 65000,
                'min_stock' => 50,
                'current_stock' => 150,
            ],
            [
                'sku' => 'AC-AMMETER',
                'name' => 'Analog Ammeter 96x96mm',
                'category_id' => $catAccessories?->id,
                'unit' => 'pcs',
                'purchase_price' => 250000,
                'selling_price' => 350000,
                'min_stock' => 10,
                'current_stock' => 30,
            ],
            [
                'sku' => 'AC-VOLTMETER',
                'name' => 'Analog Voltmeter 96x96mm',
                'category_id' => $catAccessories?->id,
                'unit' => 'pcs',
                'purchase_price' => 250000,
                'selling_price' => 350000,
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

        $this->command->info('Created 31 raw material products');
    }

    private function createFinishedGoods(): void
    {
        $catLVMDP = ProductCategory::where('code', 'FG-LV')->first();
        $catMCC = ProductCategory::where('code', 'FG-MCC')->first();
        $catCapacitor = ProductCategory::where('code', 'FG-CAP')->first();
        $catATS = ProductCategory::where('code', 'FG-ATS')->first();
        $catDB = ProductCategory::where('code', 'FG-DB')->first();

        $finishedGoods = [
            // LVMDP Panels
            [
                'sku' => 'FG-LVMDP-100',
                'name' => 'Panel LVMDP 100A',
                'description' => 'Low Voltage Main Distribution Panel 100A, 3 Phase, dengan MCCB main dan 6 outgoing MCB',
                'category_id' => $catLVMDP?->id,
                'unit' => 'unit',
                'purchase_price' => 0,
                'selling_price' => 25000000,
                'min_stock' => 0,
                'current_stock' => 2,
            ],
            [
                'sku' => 'FG-LVMDP-250',
                'name' => 'Panel LVMDP 250A',
                'description' => 'Low Voltage Main Distribution Panel 250A, 3 Phase, dengan MCCB main dan 8 outgoing',
                'category_id' => $catLVMDP?->id,
                'unit' => 'unit',
                'purchase_price' => 0,
                'selling_price' => 45000000,
                'min_stock' => 0,
                'current_stock' => 1,
            ],
            [
                'sku' => 'FG-LVMDP-400',
                'name' => 'Panel LVMDP 400A',
                'description' => 'Low Voltage Main Distribution Panel 400A, 3 Phase, dengan MCCB main dan 10 outgoing',
                'category_id' => $catLVMDP?->id,
                'unit' => 'unit',
                'purchase_price' => 0,
                'selling_price' => 75000000,
                'min_stock' => 0,
                'current_stock' => 0,
            ],
            // MCC Panels
            [
                'sku' => 'FG-MCC-DOL-25',
                'name' => 'Panel MCC DOL 25A',
                'description' => 'Motor Control Center Direct On Line 25A, dengan kontaktor, TOR, dan proteksi',
                'category_id' => $catMCC?->id,
                'unit' => 'unit',
                'purchase_price' => 0,
                'selling_price' => 12000000,
                'min_stock' => 0,
                'current_stock' => 3,
            ],
            [
                'sku' => 'FG-MCC-SD-50',
                'name' => 'Panel MCC Star Delta 50A',
                'description' => 'Motor Control Center Star Delta 50A, dengan starting sequence dan proteksi lengkap',
                'category_id' => $catMCC?->id,
                'unit' => 'unit',
                'purchase_price' => 0,
                'selling_price' => 22000000,
                'min_stock' => 0,
                'current_stock' => 1,
            ],
            // Capacitor Bank
            [
                'sku' => 'FG-CAP-100',
                'name' => 'Panel Kapasitor 100 kVAR',
                'description' => 'Capacitor Bank 100 kVAR dengan automatic power factor controller',
                'category_id' => $catCapacitor?->id,
                'unit' => 'unit',
                'purchase_price' => 0,
                'selling_price' => 35000000,
                'min_stock' => 0,
                'current_stock' => 1,
            ],
            [
                'sku' => 'FG-CAP-150',
                'name' => 'Panel Kapasitor 150 kVAR',
                'description' => 'Capacitor Bank 150 kVAR dengan automatic power factor controller',
                'category_id' => $catCapacitor?->id,
                'unit' => 'unit',
                'purchase_price' => 0,
                'selling_price' => 50000000,
                'min_stock' => 0,
                'current_stock' => 0,
            ],
            // ATS/AMF
            [
                'sku' => 'FG-ATS-100',
                'name' => 'Panel ATS 100A',
                'description' => 'Automatic Transfer Switch 100A, PLN-Genset dengan delay timer',
                'category_id' => $catATS?->id,
                'unit' => 'unit',
                'purchase_price' => 0,
                'selling_price' => 18000000,
                'min_stock' => 0,
                'current_stock' => 2,
            ],
            [
                'sku' => 'FG-ATS-250',
                'name' => 'Panel ATS 250A',
                'description' => 'Automatic Transfer Switch 250A, PLN-Genset dengan AMF controller',
                'category_id' => $catATS?->id,
                'unit' => 'unit',
                'purchase_price' => 0,
                'selling_price' => 35000000,
                'min_stock' => 0,
                'current_stock' => 1,
            ],
            // Distribution Board
            [
                'sku' => 'FG-DB-8W',
                'name' => 'Distribution Board 8 Way',
                'description' => 'DB 8 Way dengan MCB 1P',
                'category_id' => $catDB?->id,
                'unit' => 'unit',
                'purchase_price' => 0,
                'selling_price' => 2500000,
                'min_stock' => 5,
                'current_stock' => 15,
            ],
            [
                'sku' => 'FG-DB-12W',
                'name' => 'Distribution Board 12 Way',
                'description' => 'DB 12 Way dengan MCB 1P/3P mix',
                'category_id' => $catDB?->id,
                'unit' => 'unit',
                'purchase_price' => 0,
                'selling_price' => 3500000,
                'min_stock' => 5,
                'current_stock' => 10,
            ],
        ];

        foreach ($finishedGoods as $product) {
            Product::updateOrCreate(
                ['sku' => $product['sku']],
                array_merge($product, [
                    'type' => Product::TYPE_PRODUCT,
                    'tax_rate' => 11.00,
                    'is_taxable' => true,
                    'track_inventory' => true,
                    'is_active' => true,
                    'is_purchasable' => false,
                    'is_sellable' => true,
                    'inventory_account_id' => $this->inventoryAccountId,
                    'cogs_account_id' => $this->cogsAccountId,
                    'sales_account_id' => $this->salesAccountId,
                ])
            );
        }

        $this->command->info('Created 11 finished goods products');
    }

    private function createServices(): void
    {
        $catInstallation = ProductCategory::where('code', 'SVC-INS')->first();
        $catCommissioning = ProductCategory::where('code', 'SVC-COM')->first();
        $catMaintenance = ProductCategory::where('code', 'SVC-MNT')->first();

        $services = [
            [
                'sku' => 'SVC-INS-PANEL',
                'name' => 'Jasa Instalasi Panel',
                'description' => 'Jasa pemasangan panel listrik di lokasi',
                'category_id' => $catInstallation?->id,
                'unit' => 'unit',
                'purchase_price' => 500000,
                'selling_price' => 1500000,
            ],
            [
                'sku' => 'SVC-INS-WIRING',
                'name' => 'Jasa Wiring & Terminasi',
                'description' => 'Jasa wiring dan terminasi kabel',
                'category_id' => $catInstallation?->id,
                'unit' => 'titik',
                'purchase_price' => 25000,
                'selling_price' => 75000,
            ],
            [
                'sku' => 'SVC-COM-TEST',
                'name' => 'Jasa Testing & Commissioning',
                'description' => 'Testing dan commissioning panel listrik',
                'category_id' => $catCommissioning?->id,
                'unit' => 'unit',
                'purchase_price' => 750000,
                'selling_price' => 2500000,
            ],
            [
                'sku' => 'SVC-MNT-CHECK',
                'name' => 'Jasa Pemeriksaan Rutin',
                'description' => 'Pemeriksaan dan maintenance rutin panel',
                'category_id' => $catMaintenance?->id,
                'unit' => 'visit',
                'purchase_price' => 300000,
                'selling_price' => 1000000,
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

        $this->command->info('Created 4 service products');
    }

    private function createBoms(): void
    {
        // BOM for LVMDP 100A
        $this->createLvmdp100Bom();

        // BOM for MCC DOL 25A
        $this->createMccDol25Bom();

        // BOM for DB 8 Way
        $this->createDb8WayBom();

        $this->command->info('Created 3 BOMs with items');
    }

    private function createLvmdp100Bom(): void
    {
        $product = Product::where('sku', 'FG-LVMDP-100')->first();
        if (! $product) {
            return;
        }

        $bom = Bom::updateOrCreate(
            ['bom_number' => 'BOM-LVMDP-100'],
            [
                'name' => 'BOM Panel LVMDP 100A',
                'description' => 'Bill of Materials untuk Panel LVMDP 100A standar',
                'product_id' => $product->id,
                'output_quantity' => 1,
                'output_unit' => 'unit',
                'status' => Bom::STATUS_ACTIVE,
                'version' => '1.0',
            ]
        );

        // Delete existing items and recreate
        $bom->items()->delete();

        $items = [
            ['sku' => 'EL-MCCB-100', 'qty' => 1, 'desc' => 'MCCB Main Incoming'],
            ['sku' => 'EL-MCB-3P32', 'qty' => 6, 'desc' => 'MCB Outgoing'],
            ['sku' => 'EN-800X600', 'qty' => 1, 'desc' => 'Panel Enclosure'],
            ['sku' => 'BB-CU-30X5', 'qty' => 2, 'desc' => 'Busbar Set'],
            ['sku' => 'BB-LUG-70', 'qty' => 12, 'desc' => 'Cable Lug Incoming/Outgoing'],
            ['sku' => 'BB-TERM-16', 'qty' => 24, 'desc' => 'Terminal Block'],
            ['sku' => 'AC-DIN-1M', 'qty' => 3, 'desc' => 'DIN Rail'],
            ['sku' => 'AC-DUCT-40', 'qty' => 4, 'desc' => 'Cable Duct'],
            ['sku' => 'AC-LABEL-SET', 'qty' => 1, 'desc' => 'Label Set'],
            ['sku' => 'AC-AMMETER', 'qty' => 1, 'desc' => 'Ammeter'],
            ['sku' => 'AC-VOLTMETER', 'qty' => 1, 'desc' => 'Voltmeter'],
            ['sku' => 'AC-PILOT-G', 'qty' => 3, 'desc' => 'Pilot Lamp R/S/T'],
        ];

        $totalCost = 0;
        $sortOrder = 1;
        foreach ($items as $item) {
            $material = Product::where('sku', $item['sku'])->first();
            if ($material) {
                $itemCost = $material->purchase_price * $item['qty'];
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

        // Add labor cost
        BomItem::create([
            'bom_id' => $bom->id,
            'type' => BomItem::TYPE_LABOR,
            'description' => 'Tenaga Kerja Assembling (8 jam)',
            'quantity' => 8,
            'unit' => 'jam',
            'unit_cost' => 75000,
            'total_cost' => 600000,
            'sort_order' => $sortOrder++,
        ]);

        // Update BOM totals
        $laborCost = 600000;
        $overheadCost = (int) (($totalCost + $laborCost) * 0.1);
        $bom->update([
            'total_material_cost' => $totalCost,
            'total_labor_cost' => $laborCost,
            'total_overhead_cost' => $overheadCost,
            'total_cost' => $totalCost + $laborCost + $overheadCost,
            'unit_cost' => $totalCost + $laborCost + $overheadCost,
        ]);
    }

    private function createMccDol25Bom(): void
    {
        $product = Product::where('sku', 'FG-MCC-DOL-25')->first();
        if (! $product) {
            return;
        }

        $bom = Bom::updateOrCreate(
            ['bom_number' => 'BOM-MCC-DOL-25'],
            [
                'name' => 'BOM Panel MCC DOL 25A',
                'description' => 'Bill of Materials untuk Panel MCC Direct On Line 25A',
                'product_id' => $product->id,
                'output_quantity' => 1,
                'output_unit' => 'unit',
                'status' => Bom::STATUS_ACTIVE,
                'version' => '1.0',
            ]
        );

        $bom->items()->delete();

        $items = [
            ['sku' => 'EL-MCCB-100', 'qty' => 1, 'desc' => 'MCCB Main'],
            ['sku' => 'EL-CTR-25', 'qty' => 1, 'desc' => 'Kontaktor Main'],
            ['sku' => 'EL-TOR-25', 'qty' => 1, 'desc' => 'Thermal Overload Relay'],
            ['sku' => 'EN-800X600', 'qty' => 1, 'desc' => 'Panel Enclosure'],
            ['sku' => 'BB-CU-30X5', 'qty' => 1, 'desc' => 'Busbar Set'],
            ['sku' => 'CB-NYAF-15', 'qty' => 20, 'desc' => 'Kabel Control'],
            ['sku' => 'AC-DIN-1M', 'qty' => 2, 'desc' => 'DIN Rail'],
            ['sku' => 'AC-DUCT-40', 'qty' => 2, 'desc' => 'Cable Duct'],
            ['sku' => 'AC-PUSH-NO', 'qty' => 2, 'desc' => 'Push Button Start/Stop'],
            ['sku' => 'AC-PILOT-G', 'qty' => 1, 'desc' => 'Pilot Lamp Run'],
            ['sku' => 'AC-PILOT-R', 'qty' => 1, 'desc' => 'Pilot Lamp Trip'],
        ];

        $totalCost = 0;
        $sortOrder = 1;
        foreach ($items as $item) {
            $material = Product::where('sku', $item['sku'])->first();
            if ($material) {
                $itemCost = $material->purchase_price * $item['qty'];
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

        BomItem::create([
            'bom_id' => $bom->id,
            'type' => BomItem::TYPE_LABOR,
            'description' => 'Tenaga Kerja Assembling (6 jam)',
            'quantity' => 6,
            'unit' => 'jam',
            'unit_cost' => 75000,
            'total_cost' => 450000,
            'sort_order' => $sortOrder++,
        ]);

        $laborCost = 450000;
        $overheadCost = (int) (($totalCost + $laborCost) * 0.1);
        $bom->update([
            'total_material_cost' => $totalCost,
            'total_labor_cost' => $laborCost,
            'total_overhead_cost' => $overheadCost,
            'total_cost' => $totalCost + $laborCost + $overheadCost,
            'unit_cost' => $totalCost + $laborCost + $overheadCost,
        ]);
    }

    private function createDb8WayBom(): void
    {
        $product = Product::where('sku', 'FG-DB-8W')->first();
        if (! $product) {
            return;
        }

        $bom = Bom::updateOrCreate(
            ['bom_number' => 'BOM-DB-8W'],
            [
                'name' => 'BOM Distribution Board 8 Way',
                'description' => 'Bill of Materials untuk DB 8 Way',
                'product_id' => $product->id,
                'output_quantity' => 1,
                'output_unit' => 'unit',
                'status' => Bom::STATUS_ACTIVE,
                'version' => '1.0',
            ]
        );

        $bom->items()->delete();

        $items = [
            ['sku' => 'EL-MCB-3P32', 'qty' => 1, 'desc' => 'MCB Main'],
            ['sku' => 'EL-MCB-1P16', 'qty' => 8, 'desc' => 'MCB Outgoing'],
            ['sku' => 'BB-CU-30X5', 'qty' => 0.5, 'desc' => 'Busbar Set'],
            ['sku' => 'AC-DIN-1M', 'qty' => 1, 'desc' => 'DIN Rail'],
            ['sku' => 'AC-LABEL-SET', 'qty' => 1, 'desc' => 'Label Set'],
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

        // Add enclosure cost directly (not from inventory)
        $enclosureCost = 350000;
        $totalCost += $enclosureCost;
        BomItem::create([
            'bom_id' => $bom->id,
            'type' => BomItem::TYPE_MATERIAL,
            'description' => 'Enclosure DB Box 8 Way',
            'quantity' => 1,
            'unit' => 'unit',
            'unit_cost' => $enclosureCost,
            'total_cost' => $enclosureCost,
            'sort_order' => $sortOrder++,
        ]);

        BomItem::create([
            'bom_id' => $bom->id,
            'type' => BomItem::TYPE_LABOR,
            'description' => 'Tenaga Kerja Assembling (2 jam)',
            'quantity' => 2,
            'unit' => 'jam',
            'unit_cost' => 75000,
            'total_cost' => 150000,
            'sort_order' => $sortOrder++,
        ]);

        $laborCost = 150000;
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
