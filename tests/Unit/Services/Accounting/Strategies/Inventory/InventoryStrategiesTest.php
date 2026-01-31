<?php

declare(strict_types=1);

use App\Contracts\Accounting\JournalServiceInterface;
use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\StockOpname;
use App\Models\Inventory\StockOpnameItem;
use App\Models\Purchasing\GoodsReceiptNote;
use App\Models\Sales\DeliveryOrder;
use App\Services\Accounting\Strategies\Inventory\HybridInventoryStrategy;
use App\Services\Accounting\Strategies\Inventory\PeriodicInventoryStrategy;
use App\Services\Accounting\Strategies\Inventory\PerpetualInventoryStrategy;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\FiscalPeriodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
        $hybridStrategy = Mockery::mock(HybridInventoryStrategy::class);
        $this->strategy = new PerpetualInventoryStrategy($hybridStrategy);
        $this->hybridStrategy = $hybridStrategy;
    });

    describe('onGoodsReceived', function () {
        it('returns null for goods receipt (TODO implementation)', function () {
            $grn = GoodsReceiptNote::factory()->create();

            $result = $this->strategy->onGoodsReceived($grn);

            expect($result)->toBeNull();
        });
    });

    describe('onGoodsShipped', function () {
        it('returns null for delivery order (TODO implementation)', function () {
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
                ->withSurplus(5)
                ->create(['variance_value' => 25000]);

            $expectedEntry = new JournalEntry(['id' => 5]);

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
