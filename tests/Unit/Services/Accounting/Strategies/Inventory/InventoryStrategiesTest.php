<?php

declare(strict_types=1);

use App\Contracts\Accounting\JournalServiceInterface;
use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockOpname;
use App\Models\Inventory\StockOpnameItem;
use App\Models\Purchasing\GoodsReceiptNote;
use App\Models\Purchasing\GoodsReceiptNoteItem;
use App\Models\Sales\DeliveryOrder;
use App\Models\Sales\DeliveryOrderItem;
use App\Services\Accounting\Strategies\Inventory\HybridInventoryStrategy;
use App\Services\Accounting\Strategies\Inventory\PeriodicInventoryStrategy;
use App\Services\Accounting\Strategies\Inventory\PerpetualInventoryStrategy;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\FiscalPeriodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function stockOpnameAppliedMovement(StockOpname $opname, int $quantity, int $unitCost): InventoryMovement
{
    return InventoryMovement::factory()->create([
        'type' => InventoryMovement::TYPE_ADJUSTMENT,
        'quantity' => $quantity,
        'unit_cost' => $unitCost,
        'total_cost' => abs($quantity) * $unitCost,
        'reference_type' => StockOpname::class,
        'reference_id' => $opname->id,
    ]);
}

beforeEach(function () {
    authenticatedAdmin();
    $this->seed([
        ChartOfAccountsSeeder::class,
        FiscalPeriodSeeder::class,
    ]);
});

describe('HybridInventoryStrategy', function () {
    beforeEach(function () {
        $this->journalService = Mockery::mock(JournalServiceInterface::class);
        app()->instance(JournalServiceInterface::class, $this->journalService);
        $this->strategy = new HybridInventoryStrategy($this->journalService);
    });

    describe('onGoodsReceived', function () {
        it('returns null for goods receipt', function () {
            $grn = GoodsReceiptNote::factory()->create();

            $result = $this->strategy->onGoodsReceived($grn);

            expect($result)->toBeNull();
        });
    });

    describe('onGoodsShipped', function () {
        it('returns null for delivery order', function () {
            $deliveryOrder = DeliveryOrder::factory()->create();

            $result = $this->strategy->onGoodsShipped($deliveryOrder);

            expect($result)->toBeNull();
        });
    });

    describe('onStockAdjustment', function () {
        it('creates journal entry for positive stock adjustment (surplus)', function () {
            $stockOpname = StockOpname::factory()
                ->approved()
                ->create([
                    'opname_number' => 'OPN-202402-0001',
                    'opname_date' => now(),
                ]);

            StockOpnameItem::factory()
                ->forStockOpname($stockOpname)
                ->withSurplus(10)
                ->create([
                    'system_cost' => 5000,
                    'variance_value' => 50000, // 10 * 5000
                ]);

            StockOpnameItem::factory()
                ->forStockOpname($stockOpname)
                ->withSurplus(5)
                ->create([
                    'system_cost' => 10000,
                    'variance_value' => 50000, // 5 * 10000
                ]);

            stockOpnameAppliedMovement($stockOpname, 10, 5000);
            stockOpnameAppliedMovement($stockOpname, 5, 10000);

            $totalVariance = 100000; // 50000 + 50000

            $expectedEntry = new JournalEntry(['id' => 1]);

            $this->journalService
                ->shouldReceive('createEntry')
                ->once()
                ->withArgs(function ($data, $autoPost) use ($totalVariance) {
                    expect($data)->toHaveKeys(['entry_date', 'description', 'reference', 'source_type', 'source_id', 'lines'])
                        ->and($data['reference'])->toBe('OPN-202402-0001')
                        ->and($data['source_type'])->toBe(StockOpname::class)
                        ->and($data['lines'])->toHaveCount(2)
                        ->and($autoPost)->toBeTrue();

                    $lines = $data['lines'];

                    expect($lines[0]['account_code'])->toBe('1-1400')
                        ->and($lines[0]['debit'])->toBe($totalVariance)
                        ->and($lines[0]['credit'])->toBe(0)
                        ->and($lines[0]['description'])->toContain('Penyesuaian stok');

                    expect($lines[1]['account_code'])->toBe('5-2900')
                        ->and($lines[1]['debit'])->toBe(0)
                        ->and($lines[1]['credit'])->toBe($totalVariance)
                        ->and($lines[1]['description'])->toContain('Penyesuaian stok');

                    return true;
                })
                ->andReturn($expectedEntry);

            $result = $this->strategy->onStockAdjustment($stockOpname);

            expect($result)->toBeInstanceOf(JournalEntry::class);
            $this->journalService->shouldHaveReceived('createEntry')->once();
        });

        it('creates journal entry for negative stock adjustment (shrinkage)', function () {
            $stockOpname = StockOpname::factory()
                ->approved()
                ->create([
                    'opname_number' => 'OPN-202402-0002',
                    'opname_date' => now(),
                ]);

            StockOpnameItem::factory()
                ->forStockOpname($stockOpname)
                ->withShortage(10)
                ->create([
                    'system_cost' => 5000,
                    'variance_value' => -50000, // -10 * 5000
                ]);

            StockOpnameItem::factory()
                ->forStockOpname($stockOpname)
                ->withShortage(8)
                ->create([
                    'system_cost' => 10000,
                    'variance_value' => -80000, // -8 * 10000
                ]);

            stockOpnameAppliedMovement($stockOpname, -10, 5000);
            stockOpnameAppliedMovement($stockOpname, -8, 10000);

            $totalVariance = -130000; // -50000 + -80000
            $absVariance = 130000;

            $expectedEntry = new JournalEntry(['id' => 2]);

            $this->journalService
                ->shouldReceive('createEntry')
                ->once()
                ->withArgs(function ($data, $autoPost) use ($absVariance) {
                    expect($data)->toHaveKeys(['entry_date', 'description', 'reference', 'source_type', 'source_id', 'lines'])
                        ->and($data['reference'])->toBe('OPN-202402-0002')
                        ->and($data['lines'])->toHaveCount(2)
                        ->and($autoPost)->toBeTrue();

                    $lines = $data['lines'];

                    expect($lines[0]['account_code'])->toBe('5-2900')
                        ->and($lines[0]['debit'])->toBe($absVariance)
                        ->and($lines[0]['credit'])->toBe(0)
                        ->and($lines[0]['description'])->toContain('selisih kurang');

                    expect($lines[1]['account_code'])->toBe('1-1400')
                        ->and($lines[1]['debit'])->toBe(0)
                        ->and($lines[1]['credit'])->toBe($absVariance)
                        ->and($lines[1]['description'])->toContain('selisih kurang');

                    return true;
                })
                ->andReturn($expectedEntry);

            $result = $this->strategy->onStockAdjustment($stockOpname);

            expect($result)->toBeInstanceOf(JournalEntry::class);
            $this->journalService->shouldHaveReceived('createEntry')->once();
        });

        it('returns null when variance is zero', function () {
            $stockOpname = StockOpname::factory()
                ->approved()
                ->create([
                    'opname_number' => 'OPN-202402-0003',
                    'opname_date' => now(),
                ]);

            StockOpnameItem::factory()
                ->forStockOpname($stockOpname)
                ->counted()
                ->create([
                    'system_cost' => 5000,
                    'variance_value' => 0,
                ]);

            $this->journalService
                ->shouldNotReceive('createEntry');

            $result = $this->strategy->onStockAdjustment($stockOpname);

            expect($result)->toBeNull();
        });

        it('handles mixed positive and negative adjustments correctly', function () {
            $stockOpname = StockOpname::factory()
                ->approved()
                ->create([
                    'opname_number' => 'OPN-202402-0004',
                    'opname_date' => now(),
                ]);

            StockOpnameItem::factory()
                ->forStockOpname($stockOpname)
                ->withSurplus(10)
                ->create([
                    'system_cost' => 5000,
                    'variance_value' => 50000,
                ]);

            StockOpnameItem::factory()
                ->forStockOpname($stockOpname)
                ->withShortage(5)
                ->create([
                    'system_cost' => 6000,
                    'variance_value' => -30000,
                ]);

            stockOpnameAppliedMovement($stockOpname, 10, 5000);
            stockOpnameAppliedMovement($stockOpname, -5, 6000);

            $netVariance = 20000; // 50000 + (-30000)

            $expectedEntry = new JournalEntry(['id' => 3]);

            $this->journalService
                ->shouldReceive('createEntry')
                ->once()
                ->withArgs(function ($data) use ($netVariance) {
                    $lines = $data['lines'];

                    expect($lines[0]['debit'])->toBe($netVariance)
                        ->and($lines[1]['credit'])->toBe($netVariance);

                    return true;
                })
                ->andReturn($expectedEntry);

            $result = $this->strategy->onStockAdjustment($stockOpname);

            expect($result)->toBeInstanceOf(JournalEntry::class);
        });

        it('handles opname_date as string correctly', function () {
            $stockOpname = StockOpname::factory()
                ->approved()
                ->create([
                    'opname_number' => 'OPN-202402-0005',
                    'opname_date' => '2024-02-15',
                ]);

            StockOpnameItem::factory()
                ->forStockOpname($stockOpname)
                ->withSurplus(5)
                ->create(['variance_value' => 25000]);

            stockOpnameAppliedMovement($stockOpname, 5, 5000);

            $expectedEntry = new JournalEntry(['id' => 4]);

            $this->journalService
                ->shouldReceive('createEntry')
                ->once()
                ->withArgs(function ($data) {
                    expect($data['entry_date'])->toBeString();

                    return true;
                })
                ->andReturn($expectedEntry);

            $this->strategy->onStockAdjustment($stockOpname);
        });
    });

    describe('getIdentifier', function () {
        it('returns hybrid as identifier', function () {
            expect($this->strategy->getIdentifier())->toBe('hybrid');
        });
    });
});

describe('PerpetualInventoryStrategy', function () {
    beforeEach(function () {
        $this->journalService = Mockery::mock(JournalServiceInterface::class);
        $hybridStrategy = Mockery::mock(HybridInventoryStrategy::class);
        $this->strategy = new PerpetualInventoryStrategy($this->journalService, $hybridStrategy);
        $this->hybridStrategy = $hybridStrategy;
    });

    describe('onGoodsReceived', function () {
        it('creates journal entry for goods receipt with inventory items', function () {
            $product = Product::factory()
                ->create([
                    'track_inventory' => true,
                    'purchase_price' => 10000,
                ]);

            $grn = GoodsReceiptNote::factory()
                ->create([
                    'grn_number' => 'GRN-202402-0001',
                    'receipt_date' => '2024-02-15',
                ]);

            GoodsReceiptNoteItem::factory()
                ->forGoodsReceiptNote($grn)
                ->create([
                    'product_id' => $product->id,
                    'quantity_received' => 10,
                    'unit_price' => 10000,
                ]);

            $totalValue = 100000; // 10 * 10000

            $expectedEntry = new JournalEntry(['id' => 1]);

            $this->journalService
                ->shouldReceive('createEntry')
                ->once()
                ->withArgs(function ($data, $autoPost) use ($totalValue) {
                    expect($data)->toHaveKeys(['entry_date', 'description', 'reference', 'source_type', 'source_id', 'lines'])
                        ->and($data['reference'])->toBe('GRN-202402-0001')
                        ->and($data['source_type'])->toBe(GoodsReceiptNote::class)
                        ->and($data['lines'])->toHaveCount(2)
                        ->and($autoPost)->toBeTrue();

                    $lines = $data['lines'];

                    expect($lines[0]['account_code'])->toBe('1-1400')
                        ->and($lines[0]['debit'])->toBe($totalValue)
                        ->and($lines[0]['credit'])->toBe(0)
                        ->and($lines[0]['description'])->toContain('Penerimaan barang');

                    expect($lines[1]['account_code'])->toBe('2-1300')
                        ->and($lines[1]['debit'])->toBe(0)
                        ->and($lines[1]['credit'])->toBe($totalValue)
                        ->and($lines[1]['description'])->toContain('GRNI');

                    return true;
                })
                ->andReturn($expectedEntry);

            $result = $this->strategy->onGoodsReceived($grn);

            expect($result)->toBeInstanceOf(JournalEntry::class);
            $this->journalService->shouldHaveReceived('createEntry')->once();
        });

        it('returns null when total value is zero', function () {
            $product = Product::factory()
                ->create([
                    'track_inventory' => false, // Not tracked
                ]);

            $grn = GoodsReceiptNote::factory()->create();

            GoodsReceiptNoteItem::factory()
                ->forGoodsReceiptNote($grn)
                ->create([
                    'product_id' => $product->id,
                    'quantity_received' => 10,
                    'unit_price' => 10000,
                ]);

            $this->journalService->shouldNotReceive('createEntry');

            $result = $this->strategy->onGoodsReceived($grn);

            expect($result)->toBeNull();
        });

        it('skips non-inventory products', function () {
            $inventoryProduct = Product::factory()
                ->create([
                    'track_inventory' => true,
                    'purchase_price' => 10000,
                ]);

            $serviceProduct = Product::factory()
                ->create([
                    'track_inventory' => false,
                    'purchase_price' => 50000,
                ]);

            $grn = GoodsReceiptNote::factory()
                ->create([
                    'grn_number' => 'GRN-202402-0002',
                    'receipt_date' => now(),
                ]);

            GoodsReceiptNoteItem::factory()
                ->forGoodsReceiptNote($grn)
                ->create([
                    'product_id' => $inventoryProduct->id,
                    'quantity_received' => 10,
                    'unit_price' => 10000,
                ]);

            GoodsReceiptNoteItem::factory()
                ->forGoodsReceiptNote($grn)
                ->create([
                    'product_id' => $serviceProduct->id,
                    'quantity_received' => 5,
                    'unit_price' => 50000, // Should be skipped
                ]);

            $expectedValue = 100000; // Only inventory product: 10 * 10000

            $expectedEntry = new JournalEntry(['id' => 2]);

            $this->journalService
                ->shouldReceive('createEntry')
                ->once()
                ->withArgs(function ($data) use ($expectedValue) {
                    $lines = $data['lines'];

                    expect($lines[0]['debit'])->toBe($expectedValue)
                        ->and($lines[1]['credit'])->toBe($expectedValue);

                    return true;
                })
                ->andReturn($expectedEntry);

            $result = $this->strategy->onGoodsReceived($grn);

            expect($result)->toBeInstanceOf(JournalEntry::class);
        });
    });

    describe('onGoodsShipped', function () {
        it('creates journal entry for goods shipment with inventory items', function () {
            $product = Product::factory()
                ->create([
                    'track_inventory' => true,
                    'purchase_price' => 8000,
                ]);

            $deliveryOrder = DeliveryOrder::factory()
                ->create([
                    'do_number' => 'DO-202402-0001',
                    'shipping_date' => '2024-02-20',
                    'do_date' => '2024-02-19',
                ]);

            DeliveryOrderItem::factory()
                ->forDeliveryOrder($deliveryOrder)
                ->create([
                    'product_id' => $product->id,
                    'quantity' => 15,
                ]);

            $cogsAmount = 120000; // 15 * 8000

            $expectedEntry = new JournalEntry(['id' => 3]);

            $this->journalService
                ->shouldReceive('createEntry')
                ->once()
                ->withArgs(function ($data, $autoPost) use ($cogsAmount) {
                    expect($data)->toHaveKeys(['entry_date', 'description', 'reference', 'source_type', 'source_id', 'lines'])
                        ->and($data['reference'])->toBe('DO-202402-0001')
                        ->and($data['source_type'])->toBe(DeliveryOrder::class)
                        ->and($data['lines'])->toHaveCount(2)
                        ->and($autoPost)->toBeTrue();

                    $lines = $data['lines'];

                    expect($lines[0]['account_code'])->toBe('5-1001')
                        ->and($lines[0]['debit'])->toBe($cogsAmount)
                        ->and($lines[0]['credit'])->toBe(0)
                        ->and($lines[0]['description'])->toContain('HPP Pengiriman');

                    expect($lines[1]['account_code'])->toBe('1-1400')
                        ->and($lines[1]['debit'])->toBe(0)
                        ->and($lines[1]['credit'])->toBe($cogsAmount)
                        ->and($lines[1]['description'])->toContain('Pengurangan persediaan');

                    return true;
                })
                ->andReturn($expectedEntry);

            $result = $this->strategy->onGoodsShipped($deliveryOrder);

            expect($result)->toBeInstanceOf(JournalEntry::class);
            $this->journalService->shouldHaveReceived('createEntry')->once();
        });

        it('uses do_date as fallback when shipping_date is null', function () {
            $product = Product::factory()
                ->create([
                    'track_inventory' => true,
                    'purchase_price' => 5000,
                ]);

            $deliveryOrder = DeliveryOrder::factory()
                ->create([
                    'do_number' => 'DO-202402-0002',
                    'shipping_date' => null,
                    'do_date' => '2024-02-18',
                ]);

            DeliveryOrderItem::factory()
                ->forDeliveryOrder($deliveryOrder)
                ->create([
                    'product_id' => $product->id,
                    'quantity' => 10,
                ]);

            $expectedEntry = new JournalEntry(['id' => 4]);

            $this->journalService
                ->shouldReceive('createEntry')
                ->once()
                ->withArgs(function ($data) {
                    expect($data['entry_date'])->toBe('2024-02-18');

                    return true;
                })
                ->andReturn($expectedEntry);

            $this->strategy->onGoodsShipped($deliveryOrder);
        });

        it('returns null when COGS is zero', function () {
            $product = Product::factory()
                ->create([
                    'track_inventory' => false, // Not tracked
                    'purchase_price' => 10000,
                ]);

            $deliveryOrder = DeliveryOrder::factory()->create();

            DeliveryOrderItem::factory()
                ->forDeliveryOrder($deliveryOrder)
                ->create([
                    'product_id' => $product->id,
                    'quantity' => 10,
                ]);

            $this->journalService->shouldNotReceive('createEntry');

            $result = $this->strategy->onGoodsShipped($deliveryOrder);

            expect($result)->toBeNull();
        });

        it('skips non-inventory products in COGS calculation', function () {
            $inventoryProduct = Product::factory()
                ->create([
                    'track_inventory' => true,
                    'purchase_price' => 6000,
                ]);

            $serviceProduct = Product::factory()
                ->create([
                    'track_inventory' => false,
                    'purchase_price' => 100000,
                ]);

            $deliveryOrder = DeliveryOrder::factory()
                ->create(['do_number' => 'DO-202402-0003']);

            DeliveryOrderItem::factory()
                ->forDeliveryOrder($deliveryOrder)
                ->create([
                    'product_id' => $inventoryProduct->id,
                    'quantity' => 20,
                ]);

            DeliveryOrderItem::factory()
                ->forDeliveryOrder($deliveryOrder)
                ->create([
                    'product_id' => $serviceProduct->id,
                    'quantity' => 5, // Should be skipped
                ]);

            $expectedCOGS = 120000; // Only inventory product: 20 * 6000

            $expectedEntry = new JournalEntry(['id' => 5]);

            $this->journalService
                ->shouldReceive('createEntry')
                ->once()
                ->withArgs(function ($data) use ($expectedCOGS) {
                    $lines = $data['lines'];

                    expect($lines[0]['debit'])->toBe($expectedCOGS)
                        ->and($lines[1]['credit'])->toBe($expectedCOGS);

                    return true;
                })
                ->andReturn($expectedEntry);

            $result = $this->strategy->onGoodsShipped($deliveryOrder);

            expect($result)->toBeInstanceOf(JournalEntry::class);
        });
    });

    describe('onStockAdjustment', function () {
        it('delegates to hybrid strategy', function () {
            $stockOpname = StockOpname::factory()
                ->approved()
                ->create();

            StockOpnameItem::factory()
                ->forStockOpname($stockOpname)
                ->withSurplus(5)
                ->create(['variance_value' => 25000]);

            $expectedEntry = new JournalEntry(['id' => 6]);

            $this->hybridStrategy
                ->shouldReceive('onStockAdjustment')
                ->once()
                ->with($stockOpname)
                ->andReturn($expectedEntry);

            $result = $this->strategy->onStockAdjustment($stockOpname);

            expect($result)->toBeInstanceOf(JournalEntry::class);
            $this->hybridStrategy->shouldHaveReceived('onStockAdjustment')->once()->with($stockOpname);
        });
    });

    describe('getIdentifier', function () {
        it('returns perpetual as identifier', function () {
            expect($this->strategy->getIdentifier())->toBe('perpetual');
        });
    });
});

describe('PeriodicInventoryStrategy', function () {
    beforeEach(function () {
        $hybridStrategy = Mockery::mock(HybridInventoryStrategy::class);
        $this->strategy = new PeriodicInventoryStrategy($hybridStrategy);
        $this->hybridStrategy = $hybridStrategy;
    });

    describe('onGoodsReceived', function () {
        it('returns null for goods receipt', function () {
            $grn = GoodsReceiptNote::factory()->create();

            $result = $this->strategy->onGoodsReceived($grn);

            expect($result)->toBeNull();
        });
    });

    describe('onGoodsShipped', function () {
        it('returns null for delivery order', function () {
            $deliveryOrder = DeliveryOrder::factory()->create();

            $result = $this->strategy->onGoodsShipped($deliveryOrder);

            expect($result)->toBeNull();
        });
    });

    describe('onStockAdjustment', function () {
        it('delegates to hybrid strategy', function () {
            $stockOpname = StockOpname::factory()
                ->approved()
                ->create();

            StockOpnameItem::factory()
                ->forStockOpname($stockOpname)
                ->withShortage(3)
                ->create(['variance_value' => -15000]);

            $expectedEntry = new JournalEntry(['id' => 6]);

            $this->hybridStrategy
                ->shouldReceive('onStockAdjustment')
                ->once()
                ->with($stockOpname)
                ->andReturn($expectedEntry);

            $result = $this->strategy->onStockAdjustment($stockOpname);

            expect($result)->toBeInstanceOf(JournalEntry::class);
            $this->hybridStrategy->shouldHaveReceived('onStockAdjustment')->once()->with($stockOpname);
        });
    });

    describe('getIdentifier', function () {
        it('returns periodic as identifier', function () {
            expect($this->strategy->getIdentifier())->toBe('periodic');
        });
    });
});
