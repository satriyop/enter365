<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Enums\DocumentStatus;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductCategory;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use App\Models\User;
use App\Support\Features;
use Illuminate\Database\Seeder;

/**
 * Generic shop-floor demo (Odoo manufacturing pack) — no electrical_panel / solar masters.
 *
 * Seeds finished goods + materials + active BOMs so Work Order / MR / MRP demos
 * have data when FEATURE packs bom/work_orders are on (enterprise, manufacturing, full).
 */
class EnterpriseManufacturingDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Features::disabled('bom') && Features::disabled('manufacturing')) {
            $this->command?->warn('  ⏭  Skipping enterprise manufacturing demo (bom/manufacturing packs off)');

            return;
        }

        $this->command?->info('🏭 Seeding generic manufacturing masters (products + BOMs)...');

        $category = ProductCategory::query()->first()
            ?? ProductCategory::create([
                'code' => 'GEN-MFG',
                'name' => 'Manufacturing General',
                'is_active' => true,
            ]);

        $materials = [
            [
                'sku' => 'RM-STEEL-PLATE',
                'name' => 'Steel Plate 2mm',
                'unit' => 'kg',
                'purchase_price' => 25_000,
                'selling_price' => 0,
            ],
            [
                'sku' => 'RM-BOLT-M8',
                'name' => 'Bolt M8 Set',
                'unit' => 'pcs',
                'purchase_price' => 1_500,
                'selling_price' => 0,
            ],
            [
                'sku' => 'RM-PAINT-GREY',
                'name' => 'Industrial Paint Grey',
                'unit' => 'ltr',
                'purchase_price' => 85_000,
                'selling_price' => 0,
            ],
        ];

        foreach ($materials as $row) {
            Product::updateOrCreate(
                ['sku' => $row['sku']],
                [
                    'name' => $row['name'],
                    'category_id' => $category->id,
                    'type' => Product::TYPE_PRODUCT,
                    'unit' => $row['unit'],
                    'purchase_price' => $row['purchase_price'],
                    'selling_price' => $row['selling_price'],
                    'procurement_type' => Product::PROCUREMENT_BUY,
                    'is_active' => true,
                    'track_inventory' => true,
                    'min_stock' => 10,
                ]
            );
        }

        $finished = Product::updateOrCreate(
            ['sku' => 'FG-ASM-001'],
            [
                'name' => 'Assembled Frame Unit',
                'category_id' => $category->id,
                'type' => Product::TYPE_PRODUCT,
                'unit' => 'pcs',
                'purchase_price' => 0,
                'selling_price' => 750_000,
                'procurement_type' => Product::PROCUREMENT_MAKE,
                'is_active' => true,
                'track_inventory' => true,
                'min_stock' => 2,
            ]
        );

        $warehouse = Warehouse::query()->where('is_default', true)->first()
            ?? Warehouse::query()->first();

        if ($warehouse) {
            foreach (['RM-STEEL-PLATE', 'RM-BOLT-M8', 'RM-PAINT-GREY'] as $sku) {
                $product = Product::where('sku', $sku)->first();
                if (! $product) {
                    continue;
                }

                ProductStock::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'warehouse_id' => $warehouse->id,
                    ],
                    [
                        'quantity' => 500,
                        'reserved_quantity' => 0,
                        'average_cost' => $product->purchase_price,
                        'total_value' => 500 * (int) $product->purchase_price,
                    ]
                );
            }
        }

        $admin = User::where('email', 'admin@demo.com')->first()
            ?? User::where('email', 'admin@example.com')->first();

        $bom = Bom::updateOrCreate(
            ['bom_number' => 'BOM-ASM-001'],
            [
                'name' => 'BOM Assembled Frame Unit',
                'description' => 'Generic manufacturing BOM for enterprise/manufacturing demo (no industry add-ons)',
                'product_id' => $finished->id,
                'output_quantity' => 1,
                'output_unit' => 'pcs',
                'status' => DocumentStatus::Active,
                'version' => '1.0',
                'created_by' => $admin?->id,
            ]
        );

        $bom->items()->delete();

        $lines = [
            ['sku' => 'RM-STEEL-PLATE', 'qty' => 12, 'desc' => 'Frame steel'],
            ['sku' => 'RM-BOLT-M8', 'qty' => 24, 'desc' => 'Fasteners'],
            ['sku' => 'RM-PAINT-GREY', 'qty' => 1, 'desc' => 'Finish paint'],
        ];

        $sort = 1;
        $totalCost = 0;
        foreach ($lines as $line) {
            $material = Product::where('sku', $line['sku'])->first();
            if (! $material) {
                continue;
            }

            $lineCost = (int) ($material->purchase_price * $line['qty']);
            $totalCost += $lineCost;

            BomItem::create([
                'bom_id' => $bom->id,
                'type' => BomItem::TYPE_MATERIAL,
                'product_id' => $material->id,
                'description' => $line['desc'],
                'quantity' => $line['qty'],
                'unit' => $material->unit ?? 'pcs',
                'unit_cost' => $material->purchase_price,
                'total_cost' => $lineCost,
                'waste_percentage' => 2,
                'sort_order' => $sort++,
            ]);
        }

        BomItem::create([
            'bom_id' => $bom->id,
            'type' => BomItem::TYPE_LABOR,
            'description' => 'Assembly labor (4 hours)',
            'quantity' => 4,
            'unit' => 'hour',
            'unit_cost' => 50_000,
            'total_cost' => 200_000,
            'waste_percentage' => 0,
            'sort_order' => $sort++,
        ]);

        if (method_exists($bom, 'calculateTotals')) {
            $bom->calculateTotals();
            $bom->save();
        } else {
            $bom->update([
                'total_material_cost' => $totalCost,
                'total_labor_cost' => 200_000,
                'total_cost' => $totalCost + 200_000,
            ]);
        }

        // Second BOM for multi-WO demos
        $finished2 = Product::updateOrCreate(
            ['sku' => 'FG-ASM-002'],
            [
                'name' => 'Assembled Bracket Kit',
                'category_id' => $category->id,
                'type' => Product::TYPE_PRODUCT,
                'unit' => 'set',
                'purchase_price' => 0,
                'selling_price' => 320_000,
                'procurement_type' => Product::PROCUREMENT_MAKE,
                'is_active' => true,
                'track_inventory' => true,
                'min_stock' => 5,
            ]
        );

        $bom2 = Bom::updateOrCreate(
            ['bom_number' => 'BOM-ASM-002'],
            [
                'name' => 'BOM Assembled Bracket Kit',
                'description' => 'Secondary generic BOM for MRP/WO demos',
                'product_id' => $finished2->id,
                'output_quantity' => 1,
                'output_unit' => 'set',
                'status' => DocumentStatus::Active,
                'version' => '1.0',
                'created_by' => $admin?->id,
            ]
        );

        $bom2->items()->delete();
        $mBolt = Product::where('sku', 'RM-BOLT-M8')->first();
        $mSteel = Product::where('sku', 'RM-STEEL-PLATE')->first();
        if ($mSteel && $mBolt) {
            BomItem::create([
                'bom_id' => $bom2->id,
                'type' => BomItem::TYPE_MATERIAL,
                'product_id' => $mSteel->id,
                'description' => 'Bracket steel',
                'quantity' => 3,
                'unit' => $mSteel->unit,
                'unit_cost' => $mSteel->purchase_price,
                'total_cost' => $mSteel->purchase_price * 3,
                'waste_percentage' => 1,
                'sort_order' => 1,
            ]);
            BomItem::create([
                'bom_id' => $bom2->id,
                'type' => BomItem::TYPE_MATERIAL,
                'product_id' => $mBolt->id,
                'description' => 'Kit fasteners',
                'quantity' => 8,
                'unit' => $mBolt->unit,
                'unit_cost' => $mBolt->purchase_price,
                'total_cost' => $mBolt->purchase_price * 8,
                'waste_percentage' => 0,
                'sort_order' => 2,
            ]);
        }

        $this->command?->info('    Created generic FG products + 2 active BOMs (BOM-ASM-001, BOM-ASM-002)');
    }
}
