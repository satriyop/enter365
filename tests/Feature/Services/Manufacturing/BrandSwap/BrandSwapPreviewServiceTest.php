<?php

declare(strict_types=1);

use App\Models\Inventory\Product;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use App\Models\Manufacturing\ComponentBrandMapping;
use App\Models\Manufacturing\ComponentStandard;
use App\Services\Manufacturing\BrandSwap\BrandSwapPreviewService;
use App\Services\Manufacturing\SpecValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Mock SpecValidationService to avoid complex validation setup
    $this->validationService = Mockery::mock(SpecValidationService::class);
    $this->validationService->shouldReceive('validateBomBrandSwap')
        ->andReturn([
            'valid' => true,
            'items' => [],
            'summary' => [],
        ]);

    $this->service = new BrandSwapPreviewService($this->validationService);

    // Create component standard
    $this->standard = ComponentStandard::factory()->create([
        'code' => 'MCB-16A-1P',
        'name' => 'MCB 16A 1 Pole',
    ]);

    // Create source product (Schneider, 200K)
    $this->sourceProduct = Product::factory()->create([
        'name' => 'Schneider MCB 16A',
        'sku' => 'SCH-MCB-16A',
        'purchase_price' => 200000,
    ]);

    // Create target product (Chint, 120K)
    $this->targetProduct = Product::factory()->create([
        'name' => 'Chint MCB 16A',
        'sku' => 'CHT-MCB-16A',
        'purchase_price' => 120000,
    ]);

    // Source brand mapping
    $this->sourceMapping = ComponentBrandMapping::factory()->create([
        'component_standard_id' => $this->standard->id,
        'product_id' => $this->sourceProduct->id,
        'brand' => 'schneider',
        'brand_sku' => 'A9F74116',
        'is_preferred' => true,
        'is_verified' => true,
    ]);

    // Target brand mapping
    $this->targetMapping = ComponentBrandMapping::factory()->create([
        'component_standard_id' => $this->standard->id,
        'product_id' => $this->targetProduct->id,
        'brand' => 'chint',
        'brand_sku' => 'NB1-63-1P-16A',
        'is_preferred' => true,
        'is_verified' => true,
    ]);

    // Create BOM with material item using source product
    $this->bom = Bom::factory()->create();
    $this->bomItem = BomItem::factory()->create([
        'bom_id' => $this->bom->id,
        'type' => BomItem::TYPE_MATERIAL,
        'product_id' => $this->sourceProduct->id,
        'component_standard_id' => $this->standard->id,
        'description' => 'Schneider MCB 16A',
        'quantity' => 10,
        'unit_cost' => 200000,
        'total_cost' => 2000000,
    ]);
});

describe('previewItemSwap', function () {
    test('finds target product when mapping exists', function () {
        $result = $this->service->previewItemSwap($this->bomItem, 'chint');

        expect($result)->toHaveKeys(['can_swap', 'estimated_unit_cost', 'target_product', 'target_sku'])
            ->and($result['can_swap'])->toBeTrue()
            ->and($result['estimated_unit_cost'])->toEqual(120000)
            ->and($result['target_product'])->toBe('Chint MCB 16A')
            ->and($result['target_sku'])->toBe('NB1-63-1P-16A');
    });

    test('returns cannot swap when no mapping for target brand', function () {
        $result = $this->service->previewItemSwap($this->bomItem, 'nonexistent_brand');

        expect($result['can_swap'])->toBeFalse()
            ->and($result['estimated_unit_cost'])->toEqual(200000) // keeps original cost
            ->and($result['target_product'])->toBeNull()
            ->and($result['target_sku'])->toBeNull();
    });

    test('returns cannot swap when item has no product', function () {
        $manualItem = BomItem::factory()->create([
            'bom_id' => $this->bom->id,
            'type' => BomItem::TYPE_MATERIAL,
            'product_id' => null,
            'description' => 'Manual entry',
            'unit_cost' => 50000,
        ]);

        $result = $this->service->previewItemSwap($manualItem, 'chint');

        expect($result['can_swap'])->toBeFalse();
    });

    test('returns cannot swap when product has no brand mapping', function () {
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

        $result = $this->service->previewItemSwap($unmappedItem, 'chint');

        expect($result['can_swap'])->toBeFalse();
    });
});

describe('previewSwapBrand', function () {
    test('returns correct structure with cost calculations', function () {
        $result = $this->service->previewSwapBrand($this->bom, 'chint');

        expect($result)->toHaveKeys([
            'target_brand', 'current_total', 'estimated_total',
            'savings', 'savings_percentage', 'coverage', 'items', 'validation',
        ])
            ->and($result['target_brand'])->toBe('chint')
            ->and($result['current_total'])->toEqual(2000000)  // 10 * 200K
            ->and($result['estimated_total'])->toEqual(1200000) // 10 * 120K
            ->and($result['savings'])->toEqual(800000)
            ->and($result['coverage'])->toHaveKeys(['total', 'swappable', 'no_mapping', 'percentage'])
            ->and($result['coverage']['total'])->toBe(1)
            ->and($result['coverage']['swappable'])->toBe(1)
            ->and($result['coverage']['no_mapping'])->toBe(0)
            ->and($result['coverage']['percentage'])->toBe(100.0);
    });

    test('items array contains detailed per-item info', function () {
        $result = $this->service->previewSwapBrand($this->bom, 'chint');

        expect($result['items'])->toHaveCount(1);

        $item = $result['items'][0];
        expect($item)->toHaveKeys([
            'bom_item_id', 'description', 'quantity',
            'current_unit_cost', 'current_total',
            'estimated_unit_cost', 'estimated_total',
            'cost_change', 'can_swap', 'target_product', 'target_sku',
            'validation',
        ])
            ->and($item['bom_item_id'])->toBe($this->bomItem->id)
            ->and($item['can_swap'])->toBeTrue()
            ->and($item['cost_change'])->toEqual(-800000); // 1.2M - 2M
    });

    test('validation structure is included', function () {
        $result = $this->service->previewSwapBrand($this->bom, 'chint');

        expect($result['validation'])->toHaveKeys(['valid', 'rule_set', 'summary'])
            ->and($result['validation']['valid'])->toBeTrue();
    });
});

describe('compareBrands', function () {
    test('returns all available brands with previews', function () {
        $result = $this->service->compareBrands($this->bom);

        expect($result)->toHaveKeys(['current', 'brands', 'recommendations'])
            ->and($result['current'])->toHaveKeys(['total', 'total_items'])
            ->and($result['current']['total'])->toEqual(2000000)
            ->and($result['current']['total_items'])->toBe(1);

        // Should include at least schneider and chint (from our mappings)
        expect($result['brands'])->not->toBeEmpty();

        // Recommendations should have best_value and best_coverage
        expect($result['recommendations'])->toHaveKeys(['best_value', 'best_coverage']);
    });

    test('brands sorted by savings highest first', function () {
        $result = $this->service->compareBrands($this->bom);

        $savings = array_column($result['brands'], 'savings');
        for ($i = 1; $i < count($savings); $i++) {
            expect($savings[$i])->toBeLessThanOrEqual($savings[$i - 1]);
        }
    });
});

describe('getItemAlternatives', function () {
    test('returns alternatives for item with component standard', function () {
        $result = $this->service->getItemAlternatives($this->bomItem);

        expect($result)->toHaveKeys(['current', 'alternatives', 'has_standard'])
            ->and($result['has_standard'])->toBeTrue()
            ->and($result['current'])->toHaveKeys([
                'product_id', 'product_name', 'product_sku', 'unit_cost', 'brand',
            ])
            ->and($result['current']['product_id'])->toBe($this->sourceProduct->id)
            ->and($result['current']['brand'])->toBe('schneider');

        // Should find chint as alternative
        expect($result['alternatives'])->toHaveCount(1);

        $alt = $result['alternatives'][0];
        expect($alt)->toHaveKeys([
            'mapping_id', 'product_id', 'product_name', 'product_sku',
            'brand', 'brand_label', 'brand_sku', 'unit_cost',
            'price_diff', 'price_diff_percent', 'is_preferred', 'is_verified',
        ])
            ->and($alt['product_id'])->toBe($this->targetProduct->id)
            ->and($alt['brand'])->toBe('chint')
            ->and($alt['unit_cost'])->toEqual(120000)
            ->and($alt['price_diff'])->toEqual(-80000); // 120K - 200K
    });

    test('returns empty alternatives when no component standard', function () {
        $noStandardItem = BomItem::factory()->create([
            'bom_id' => $this->bom->id,
            'type' => BomItem::TYPE_MATERIAL,
            'product_id' => $this->sourceProduct->id,
            'component_standard_id' => null,
            'unit_cost' => 200000,
        ]);

        $result = $this->service->getItemAlternatives($noStandardItem);

        expect($result['has_standard'])->toBeFalse()
            ->and($result['alternatives'])->toBeEmpty();
    });
});
