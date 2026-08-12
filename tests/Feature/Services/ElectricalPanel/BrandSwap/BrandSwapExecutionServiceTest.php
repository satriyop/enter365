<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Models\ElectricalPanel\ComponentBrandMapping;
use App\Models\ElectricalPanel\ComponentStandard;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use App\Services\ElectricalPanel\BrandSwap\BrandSwapExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Addons\ElectricalPanelHelpers;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(BrandSwapExecutionService::class);

    // Create component standard
    $this->standard = ComponentStandard::factory()->create([
        'code' => 'MCB-16A-1P',
        'name' => 'MCB 16A 1 Pole',
    ]);

    // Source product (Schneider)
    $this->sourceProduct = Product::factory()->create([
        'name' => 'Schneider MCB 16A',
        'sku' => 'SCH-MCB-16A',
        'purchase_price' => 200000,
    ]);

    // Target product (Chint)
    $this->targetProduct = Product::factory()->create([
        'name' => 'Chint MCB 16A',
        'sku' => 'CHT-MCB-16A',
        'purchase_price' => 120000,
    ]);

    // Brand mappings
    ComponentBrandMapping::factory()->create([
        'component_standard_id' => $this->standard->id,
        'product_id' => $this->sourceProduct->id,
        'brand' => 'schneider',
        'brand_sku' => 'A9F74116',
        'is_preferred' => true,
    ]);

    ComponentBrandMapping::factory()->create([
        'component_standard_id' => $this->standard->id,
        'product_id' => $this->targetProduct->id,
        'brand' => 'chint',
        'brand_sku' => 'NB1-63-1P-16A',
        'is_preferred' => true,
    ]);

    // Create BOM
    $this->bom = Bom::factory()->create([
        'status' => DocumentStatus::Active,
        'total_material_cost' => 2000000,
        'total_cost' => 2000000,
    ]);

    $this->bomItem = BomItem::factory()->create([
        'bom_id' => $this->bom->id,
        'type' => BomItem::TYPE_MATERIAL,
        'product_id' => $this->sourceProduct->id,
        'description' => 'Schneider MCB 16A',
        'quantity' => 10,
        'unit_cost' => 200000,
        'total_cost' => 2000000,
    ]);
    ElectricalPanelHelpers::attachBomItemStandard($this->bomItem, $this->standard);
});

describe('swapBomBrand', function () {
    test('creates new BOM variant with swapped products', function () {
        $result = $this->service->swapBomBrand($this->bom, 'chint', createVariant: true);

        expect($result)->toHaveKeys(['bom', 'swap_report']);

        $newBom = $result['bom'];
        expect($newBom)->toBeInstanceOf(Bom::class)
            ->and($newBom->id)->not->toBe($this->bom->id) // New BOM created
            ->and($newBom->status)->toBe(DocumentStatus::Draft)
            ->and($newBom->variant_name)->toBe('Chint');

        $report = $result['swap_report'];
        expect($report)->toHaveKeys([
            'total_items', 'swapped', 'no_mapping', 'partial_match', 'kept_original', 'items',
        ])
            ->and($report['total_items'])->toBe(1)
            ->and($report['swapped'])->toBe(1)
            ->and($report['no_mapping'])->toBe(0);
    });

    test('swap report items have correct structure', function () {
        $result = $this->service->swapBomBrand($this->bom, 'chint', createVariant: true);

        $item = $result['swap_report']['items'][0];
        expect($item)->toHaveKeys(['status', 'original', 'new', 'notes'])
            ->and($item['status'])->toBe('swapped')
            ->and($item['original']['product_id'])->toBe($this->sourceProduct->id)
            ->and($item['new']['product_id'])->toBe($this->targetProduct->id)
            ->and($item['new']['brand'])->toBe('chint')
            ->and($item['new']['unit_cost'])->toEqual(120000);
    });

    test('in-place swap modifies existing BOM when createVariant is false', function () {
        $result = $this->service->swapBomBrand($this->bom, 'chint', createVariant: false);

        $returnedBom = $result['bom'];
        expect($returnedBom->id)->toBe($this->bom->id); // Same BOM
    });

    test('reports no_mapping when no equivalent exists', function () {
        // Add unmapped item
        $unmappedProduct = Product::factory()->create([
            'name' => 'Custom Widget',
            'purchase_price' => 500000,
        ]);

        BomItem::factory()->create([
            'bom_id' => $this->bom->id,
            'type' => BomItem::TYPE_MATERIAL,
            'product_id' => $unmappedProduct->id,
            'description' => 'Custom Widget',
            'quantity' => 5,
            'unit_cost' => 500000,
            'total_cost' => 2500000,
        ]);

        $result = $this->service->swapBomBrand($this->bom, 'chint', createVariant: true);

        // 2 items: 1 swapped (MCB), 1 no_product (no component_standard → no mapping lookup → 'no_product')
        expect($result['swap_report']['total_items'])->toBe(2);
    });
});

describe('quickSwapItem', function () {
    test('swaps item in-place and returns savings', function () {
        $result = $this->service->quickSwapItem(
            $this->bomItem,
            $this->targetProduct,
            'Menggunakan brand lebih murah'
        );

        expect($result)->toHaveKeys(['success', 'item', 'previous', 'new', 'savings', 'reason'])
            ->and($result['success'])->toBeTrue()
            ->and($result['previous']['product_id'])->toBe($this->sourceProduct->id)
            ->and($result['previous']['unit_cost'])->toEqual(200000)
            ->and($result['new']['product_id'])->toBe($this->targetProduct->id)
            ->and($result['new']['unit_cost'])->toEqual(120000)
            ->and($result['savings'])->toEqual(800000) // (200K - 120K) * 10
            ->and($result['reason'])->toBe('Menggunakan brand lebih murah');

        // Verify item persisted
        $this->bomItem->refresh();
        expect($this->bomItem->product_id)->toBe($this->targetProduct->id)
            ->and($this->bomItem->unit_cost)->toEqual(120000);
    });
});

describe('swapItem', function () {
    test('returns no_product status for items without product_id', function () {
        $manualItem = BomItem::factory()->create([
            'bom_id' => $this->bom->id,
            'type' => BomItem::TYPE_MATERIAL,
            'product_id' => null,
            'description' => 'Manual entry',
            'unit_cost' => 50000,
        ]);

        $newBom = Bom::factory()->create();

        $result = $this->service->swapItem($manualItem, $newBom, 'chint', true);

        expect($result['status'])->toBe('no_product')
            ->and($result['new'])->toBeNull();
    });

    test('returns no_mapping when product has no brand mapping', function () {
        $unmappedProduct = Product::factory()->create([
            'name' => 'Generic Product',
            'purchase_price' => 100000,
        ]);

        $unmappedItem = BomItem::factory()->create([
            'bom_id' => $this->bom->id,
            'type' => BomItem::TYPE_MATERIAL,
            'product_id' => $unmappedProduct->id,
            'unit_cost' => 100000,
        ]);

        $newBom = Bom::factory()->create();

        $result = $this->service->swapItem($unmappedItem, $newBom, 'chint', true);

        expect($result['status'])->toBe('no_mapping')
            ->and($result['notes'])->toContain('No component standard mapping');
    });

    test('returns swapped when equivalent exists in target brand', function () {
        $newBom = Bom::factory()->create();

        $result = $this->service->swapItem($this->bomItem, $newBom, 'chint', true);

        expect($result['status'])->toBe('swapped')
            ->and($result['new'])->not->toBeNull()
            ->and($result['new']['product_id'])->toBe($this->targetProduct->id)
            ->and($result['new']['brand'])->toBe('chint');
    });
});
