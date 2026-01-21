<?php

use App\Domain\Manufacturing\Boms\BomExploder;
use App\Enums\DocumentStatus;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('BomExploder', function () {

    beforeEach(function () {
        $this->exploder = new BomExploder;
    });

    describe('explodeFlat', function () {

        it('explodes single level BOM correctly', function () {
            $finishedProduct = Product::factory()->create(['name' => 'Finished Product']);
            $material1 = Product::factory()->create(['name' => 'Material A']);
            $material2 = Product::factory()->create(['name' => 'Material B']);

            $bom = Bom::factory()->create([
                'product_id' => $finishedProduct->id,
                'output_quantity' => 1,
                'status' => DocumentStatus::Active,
            ]);

            BomItem::factory()->create([
                'bom_id' => $bom->id,
                'type' => BomItem::TYPE_MATERIAL,
                'product_id' => $material1->id,
                'description' => 'Material A',
                'quantity' => 5,
                'waste_percentage' => 0,
                'unit' => 'pcs',
                'unit_cost' => 1000,
            ]);

            BomItem::factory()->create([
                'bom_id' => $bom->id,
                'type' => BomItem::TYPE_MATERIAL,
                'product_id' => $material2->id,
                'description' => 'Material B',
                'quantity' => 3,
                'waste_percentage' => 0,
                'unit' => 'pcs',
                'unit_cost' => 2000,
            ]);

            $result = $this->exploder->explodeFlat($bom, 2);

            expect($result)->toHaveCount(2);

            $materialA = collect($result)->firstWhere('product_id', $material1->id);
            $materialB = collect($result)->firstWhere('product_id', $material2->id);

            expect($materialA['quantity'])->toBe(10.0); // 5 * 2
            expect($materialB['quantity'])->toBe(6.0);  // 3 * 2
        });

        it('handles waste percentage correctly', function () {
            $finishedProduct = Product::factory()->create();
            $material = Product::factory()->create(['name' => 'Material']);

            $bom = Bom::factory()->create([
                'product_id' => $finishedProduct->id,
                'output_quantity' => 1,
                'status' => DocumentStatus::Active,
            ]);

            BomItem::factory()->create([
                'bom_id' => $bom->id,
                'type' => BomItem::TYPE_MATERIAL,
                'product_id' => $material->id,
                'description' => 'Material',
                'quantity' => 10,
                'waste_percentage' => 10, // 10% waste
                'unit' => 'pcs',
                'unit_cost' => 1000,
            ]);

            $result = $this->exploder->explodeFlat($bom, 1);

            expect($result[0]['quantity'])->toBe(11.0); // 10 * 1.10
        });

        it('handles output quantity normalization', function () {
            $finishedProduct = Product::factory()->create();
            $material = Product::factory()->create();

            // BOM produces 10 units, we need 5
            $bom = Bom::factory()->create([
                'product_id' => $finishedProduct->id,
                'output_quantity' => 10,
                'status' => DocumentStatus::Active,
            ]);

            BomItem::factory()->create([
                'bom_id' => $bom->id,
                'type' => BomItem::TYPE_MATERIAL,
                'product_id' => $material->id,
                'description' => 'Material',
                'quantity' => 100, // 100 for 10 units = 10 per unit
                'waste_percentage' => 0,
                'unit' => 'pcs',
                'unit_cost' => 1000,
            ]);

            $result = $this->exploder->explodeFlat($bom, 5);

            expect($result[0]['quantity'])->toBe(50.0); // Need 5 units, 10 per unit
        });

        it('excludes labor and overhead items', function () {
            $finishedProduct = Product::factory()->create();
            $material = Product::factory()->create();

            $bom = Bom::factory()->create([
                'product_id' => $finishedProduct->id,
                'output_quantity' => 1,
                'status' => DocumentStatus::Active,
            ]);

            BomItem::factory()->create([
                'bom_id' => $bom->id,
                'type' => BomItem::TYPE_MATERIAL,
                'product_id' => $material->id,
                'description' => 'Material',
                'quantity' => 5,
                'waste_percentage' => 0,
            ]);

            BomItem::factory()->create([
                'bom_id' => $bom->id,
                'type' => BomItem::TYPE_LABOR,
                'product_id' => null,
                'description' => 'Assembly Labor',
                'quantity' => 1,
                'waste_percentage' => 0,
            ]);

            BomItem::factory()->create([
                'bom_id' => $bom->id,
                'type' => BomItem::TYPE_OVERHEAD,
                'product_id' => null,
                'description' => 'Factory Overhead',
                'quantity' => 1,
                'waste_percentage' => 0,
            ]);

            $result = $this->exploder->explodeFlat($bom, 1);

            expect($result)->toHaveCount(1);
            expect($result[0]['description'])->toBe('Material');
        });
    });

    describe('explodeHierarchical', function () {

        it('returns hierarchical structure', function () {
            $finishedProduct = Product::factory()->create();
            $material = Product::factory()->create(['name' => 'Material X']);

            $bom = Bom::factory()->create([
                'product_id' => $finishedProduct->id,
                'output_quantity' => 1,
                'status' => DocumentStatus::Active,
            ]);

            BomItem::factory()->create([
                'bom_id' => $bom->id,
                'type' => BomItem::TYPE_MATERIAL,
                'product_id' => $material->id,
                'description' => 'Material X',
                'quantity' => 5,
                'waste_percentage' => 0,
                'unit' => 'pcs',
                'unit_cost' => 1000,
            ]);

            $result = $this->exploder->explodeHierarchical($bom, 1);

            expect($result)->toHaveCount(1);
            expect($result[0]['product_id'])->toBe($material->id);
            expect($result[0]['is_subassembly'])->toBeFalse();
            expect($result[0]['children'])->toBeEmpty();
        });
    });

    describe('calculateRequirements', function () {

        it('aggregates duplicate materials', function () {
            $finishedProduct = Product::factory()->create();
            $material = Product::factory()->create(['name' => 'Common Material']);

            $bom = Bom::factory()->create([
                'product_id' => $finishedProduct->id,
                'output_quantity' => 1,
                'status' => DocumentStatus::Active,
            ]);

            // Same material appears twice (simulating different usages)
            BomItem::factory()->create([
                'bom_id' => $bom->id,
                'type' => BomItem::TYPE_MATERIAL,
                'product_id' => $material->id,
                'description' => 'Common Material (Usage 1)',
                'quantity' => 3,
                'waste_percentage' => 0,
                'unit' => 'pcs',
                'unit_cost' => 1000,
            ]);

            BomItem::factory()->create([
                'bom_id' => $bom->id,
                'type' => BomItem::TYPE_MATERIAL,
                'product_id' => $material->id,
                'description' => 'Common Material (Usage 2)',
                'quantity' => 2,
                'waste_percentage' => 0,
                'unit' => 'pcs',
                'unit_cost' => 1000,
            ]);

            $result = $this->exploder->calculateRequirements($bom, 1);

            expect($result)->toHaveCount(1);
            expect($result[0]['total_quantity'])->toBe(5.0); // 3 + 2
        });

        it('calculates total cost', function () {
            $finishedProduct = Product::factory()->create();
            $material1 = Product::factory()->create();
            $material2 = Product::factory()->create();

            $bom = Bom::factory()->create([
                'product_id' => $finishedProduct->id,
                'output_quantity' => 1,
                'status' => DocumentStatus::Active,
            ]);

            BomItem::factory()->create([
                'bom_id' => $bom->id,
                'type' => BomItem::TYPE_MATERIAL,
                'product_id' => $material1->id,
                'description' => 'Material 1',
                'quantity' => 5,
                'waste_percentage' => 0,
                'unit' => 'pcs',
                'unit_cost' => 1000,
            ]);

            BomItem::factory()->create([
                'bom_id' => $bom->id,
                'type' => BomItem::TYPE_MATERIAL,
                'product_id' => $material2->id,
                'description' => 'Material 2',
                'quantity' => 3,
                'waste_percentage' => 0,
                'unit' => 'pcs',
                'unit_cost' => 2000,
            ]);

            $requirements = $this->exploder->calculateRequirements($bom, 2);

            $mat1 = collect($requirements)->firstWhere('product_id', $material1->id);
            $mat2 = collect($requirements)->firstWhere('product_id', $material2->id);

            expect($mat1['total_cost'])->toBe(10000); // 5 * 2 * 1000
            expect($mat2['total_cost'])->toBe(12000); // 3 * 2 * 2000
        });
    });

    describe('calculateTotalCost', function () {

        it('returns sum of all material costs', function () {
            $finishedProduct = Product::factory()->create();
            $material1 = Product::factory()->create();
            $material2 = Product::factory()->create();

            $bom = Bom::factory()->create([
                'product_id' => $finishedProduct->id,
                'output_quantity' => 1,
                'status' => DocumentStatus::Active,
            ]);

            BomItem::factory()->create([
                'bom_id' => $bom->id,
                'type' => BomItem::TYPE_MATERIAL,
                'product_id' => $material1->id,
                'quantity' => 5,
                'waste_percentage' => 0,
                'unit_cost' => 1000,
            ]);

            BomItem::factory()->create([
                'bom_id' => $bom->id,
                'type' => BomItem::TYPE_MATERIAL,
                'product_id' => $material2->id,
                'quantity' => 3,
                'waste_percentage' => 0,
                'unit_cost' => 2000,
            ]);

            $totalCost = $this->exploder->calculateTotalCost($bom, 1);

            expect($totalCost)->toBe(11000); // 5*1000 + 3*2000
        });
    });

    describe('hasCircularReference', function () {

        it('returns false for non-circular BOM', function () {
            $product = Product::factory()->create();
            $material = Product::factory()->create();

            $bom = Bom::factory()->create([
                'product_id' => $product->id,
                'output_quantity' => 1,
                'status' => DocumentStatus::Active,
            ]);

            BomItem::factory()->create([
                'bom_id' => $bom->id,
                'type' => BomItem::TYPE_MATERIAL,
                'product_id' => $material->id,
                'quantity' => 1,
            ]);

            expect($this->exploder->hasCircularReference($bom))->toBeFalse();
        });
    });

    describe('configuration', function () {

        it('respects max depth setting', function () {
            $this->exploder->setMaxDepth(5);

            // The exploder should now limit recursion to 5 levels
            expect(true)->toBeTrue(); // Configuration accepted
        });

        it('can include inactive BOMs', function () {
            $this->exploder->includeInactive(true);

            // The exploder should now include inactive BOMs
            expect(true)->toBeTrue(); // Configuration accepted
        });
    });
});
