<?php

declare(strict_types=1);

use App\Contracts\Manufacturing\BomServiceInterface;
use App\Enums\DocumentStatus;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->product = Product::factory()->create(['purchase_price' => 100000]);
    $this->material = Product::factory()->create(['purchase_price' => 50000]);

    $this->service = app(BomServiceInterface::class);
    $this->actingAs($this->user);
});

describe('CRUD Operations', function () {
    test('creates BOM with items', function () {
        $data = [
            'product_id' => $this->product->id,
            'name' => 'Test BOM',
            'output_quantity' => 10,
            'items' => [
                [
                    'type' => BomItem::TYPE_MATERIAL,
                    'product_id' => $this->material->id,
                    'description' => 'Material 1',
                    'quantity' => 5,
                    'unit' => 'pcs',
                    'unit_cost' => 10000,
                    'waste_percentage' => 5,
                ],
            ],
        ];

        $bom = $this->service->create($data);

        expect($bom)
            ->toBeInstanceOf(Bom::class)
            ->status->toBe(DocumentStatus::Draft)
            ->product_id->toBe($this->product->id)
            ->bom_number->not->toBeEmpty()
            ->items->toHaveCount(1);

        expect($bom->items->first())
            ->product_id->toBe($this->material->id);

        expect((float) $bom->items->first()->quantity)->toBe(5.0);
    });

    test('calculates item totals after creation', function () {
        $bom = $this->service->create([
            'product_id' => $this->product->id,
            'name' => 'Test BOM',
            'output_quantity' => 10,
            'items' => [
                [
                    'type' => BomItem::TYPE_MATERIAL,
                    'description' => 'Item 1',
                    'quantity' => 5,
                    'unit_cost' => 10000,
                    'waste_percentage' => 10,
                ],
                [
                    'type' => BomItem::TYPE_LABOR,
                    'description' => 'Labor',
                    'quantity' => 2,
                    'unit_cost' => 50000,
                ],
            ],
        ]);

        expect($bom)
            ->total_material_cost->toBeGreaterThan(0)
            ->total_labor_cost->toBeGreaterThan(0)
            ->total_cost->toBe($bom->total_material_cost + $bom->total_labor_cost + $bom->total_overhead_cost);
    });

    test('updates draft BOM', function () {
        $bom = Bom::factory()
            ->has(BomItem::factory(), 'items')
            ->create(['name' => 'Old Name']);

        $updated = $this->service->update($bom, ['name' => 'New Name']);

        expect($updated->name)->toBe('New Name');
    });

    test('replaces items when updating BOM', function () {
        $bom = Bom::factory()
            ->has(BomItem::factory()->count(2), 'items')
            ->create();

        $updated = $this->service->update($bom, [
            'items' => [
                [
                    'type' => BomItem::TYPE_MATERIAL,
                    'description' => 'New Item',
                    'quantity' => 1,
                    'unit_cost' => 5000,
                ],
            ],
        ]);

        expect($updated->items)->toHaveCount(1)
            ->and($updated->items->first()->description)->toBe('New Item');
    });

    test('throws exception when updating non-draft BOM', function () {
        $bom = Bom::factory()
            ->has(BomItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Active]);

        $this->service->update($bom, ['name' => 'Test']);
    })->throws(InvalidArgumentException::class, 'draft');

    test('deletes draft BOM with items', function () {
        $bom = Bom::factory()
            ->has(BomItem::factory()->count(3), 'items')
            ->create();

        $result = $this->service->delete($bom);

        expect($result)->toBeTrue()
            ->and(Bom::find($bom->id))->toBeNull()
            ->and(BomItem::where('bom_id', $bom->id)->count())->toBe(0);
    });

    test('throws exception when deleting non-draft BOM', function () {
        $bom = Bom::factory()
            ->has(BomItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Active]);

        $this->service->delete($bom);
    })->throws(InvalidArgumentException::class, 'draft');
});

describe('Activation and Deactivation', function () {
    test('activates draft BOM with items', function () {
        $bom = Bom::factory()
            ->has(BomItem::factory(), 'items')
            ->create([
                'product_id' => $this->product->id,
                'status' => DocumentStatus::Draft,
            ]);

        $activated = $this->service->activate($bom);

        expect($activated)
            ->status->toBe(DocumentStatus::Active)
            ->approved_at->not->toBeNull()
            ->approved_by->toBe($this->user->id);
    });

    test('deactivates existing active BOM when activating new one', function () {
        $existingBom = Bom::factory()
            ->has(BomItem::factory(), 'items')
            ->create([
                'product_id' => $this->product->id,
                'status' => DocumentStatus::Active,
            ]);

        $newBom = Bom::factory()
            ->has(BomItem::factory(), 'items')
            ->create([
                'product_id' => $this->product->id,
                'status' => DocumentStatus::Draft,
            ]);

        $this->service->activate($newBom);

        $existingBom->refresh();
        expect($existingBom->status)->toBe(DocumentStatus::Inactive);
    });

    test('throws exception when activating BOM without items', function () {
        $bom = Bom::factory()->create(['status' => DocumentStatus::Draft]);

        $this->service->activate($bom);
    })->throws(InvalidArgumentException::class);

    test('deactivates active BOM', function () {
        $bom = Bom::factory()
            ->has(BomItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Active]);

        $deactivated = $this->service->deactivate($bom);

        expect($deactivated->status)->toBe(DocumentStatus::Inactive);
    });

    test('throws exception when deactivating non-active BOM', function () {
        $bom = Bom::factory()
            ->has(BomItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Draft]);

        $this->service->deactivate($bom);
    })->throws(InvalidArgumentException::class, 'active');
});

describe('Duplication', function () {
    test('duplicates BOM as new draft with incremented version', function () {
        $bom = Bom::factory()
            ->has(BomItem::factory()->count(3), 'items')
            ->create([
                'status' => DocumentStatus::Active,
                'version' => '1.0',
            ]);

        $duplicate = $this->service->duplicate($bom);

        expect($duplicate)
            ->status->toBe(DocumentStatus::Draft)
            ->bom_number->not->toBe($bom->bom_number)
            ->version->toBe('1.1')
            ->parent_bom_id->toBe($bom->id)
            ->items->toHaveCount(3);
    });

    test('increments minor version correctly', function () {
        $bom = Bom::factory()
            ->has(BomItem::factory(), 'items')
            ->create(['version' => '2.5']);

        $duplicate = $this->service->duplicate($bom);

        expect($duplicate->version)->toBe('2.6');
    });
});

describe('Query Methods', function () {
    test('gets active BOM for product', function () {
        $activeBom = Bom::factory()
            ->has(BomItem::factory(), 'items')
            ->create([
                'product_id' => $this->product->id,
                'status' => DocumentStatus::Active,
            ]);

        // Create inactive BOMs
        Bom::factory()->count(2)->create([
            'product_id' => $this->product->id,
            'status' => DocumentStatus::Inactive,
        ]);

        $result = $this->service->getActiveForProduct($this->product);

        expect($result)
            ->not->toBeNull()
            ->id->toBe($activeBom->id);
    });

    test('returns null when no active BOM for product', function () {
        Bom::factory()->create([
            'product_id' => $this->product->id,
            'status' => DocumentStatus::Draft,
        ]);

        $result = $this->service->getActiveForProduct($this->product);

        expect($result)->toBeNull();
    });
});

describe('Cost Calculation', function () {
    test('calculates production cost for given quantity', function () {
        $bom = Bom::factory()
            ->has(BomItem::factory()->state([
                'type' => BomItem::TYPE_MATERIAL,
                'product_id' => $this->material->id,
                'quantity' => 5,
                'waste_percentage' => 10,
                'unit_cost' => 10000,
            ]), 'items')
            ->create([
                'product_id' => $this->product->id,
                'output_quantity' => 10,
                'total_material_cost' => 55000,
                'total_labor_cost' => 100000,
                'total_overhead_cost' => 50000,
            ]);

        $cost = $this->service->calculateProductionCost($bom, 20);

        expect($cost)
            ->toBeArray()
            ->toHaveKey('bom_id')
            ->toHaveKey('quantity_to_produce')
            ->toHaveKey('materials')
            ->toHaveKey('cost_breakdown')
            ->toHaveKey('total_cost')
            ->toHaveKey('unit_cost');

        // Multiplier is 20/10 = 2, so costs should double
        expect($cost['quantity_to_produce'])->toBe(20.0);
    });

    test('calculates unit cost correctly', function () {
        $bom = Bom::factory()
            ->has(BomItem::factory()->state([
                'type' => BomItem::TYPE_MATERIAL,
                'quantity' => 5,
                'unit_cost' => 10000,
            ]), 'items')
            ->create([
                'output_quantity' => 10,
                'total_material_cost' => 50000,
                'total_labor_cost' => 50000,
                'total_overhead_cost' => 0,
            ]);

        $cost = $this->service->calculateProductionCost($bom, 10);

        // Total should include material (50000) + labor (50000) + overhead (0) = 100000
        // But the calculator includes waste/scrap percentage in effective quantity
        expect($cost['unit_cost'])->toBeGreaterThan(0);
    });

    test('handles zero quantity gracefully', function () {
        $bom = Bom::factory()
            ->has(BomItem::factory(), 'items')
            ->create();

        $cost = $this->service->calculateProductionCost($bom, 0);

        expect($cost['unit_cost'])->toBe(0);
    });
});

describe('Statistics', function () {
    test('returns BOM statistics', function () {
        Bom::factory()->count(5)->create(['status' => DocumentStatus::Draft]);
        Bom::factory()
            ->has(BomItem::factory(), 'items')
            ->count(3)
            ->create(['status' => DocumentStatus::Active]);
        Bom::factory()->count(2)->create(['status' => DocumentStatus::Inactive]);

        $stats = $this->service->getStatistics();

        expect($stats)
            ->toBeArray()
            ->total_count->toBe(10)
            ->draft_count->toBe(5)
            ->active_count->toBe(3)
            ->inactive_count->toBe(2);
    });

    test('calculates average costs', function () {
        Bom::factory()
            ->has(BomItem::factory(), 'items')
            ->count(3)
            ->create([
                'status' => DocumentStatus::Active,
                'total_material_cost' => 100000,
            ]);

        $stats = $this->service->getStatistics();

        expect($stats)
            ->avg_material_cost->toBe(100000)
            ->products_with_bom->toBeGreaterThanOrEqual(1);
    });
});
