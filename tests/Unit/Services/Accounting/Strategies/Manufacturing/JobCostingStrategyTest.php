<?php

declare(strict_types=1);

use App\Contracts\Accounting\JournalServiceInterface;
use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\MaterialConsumption;
use App\Models\Manufacturing\WorkOrder;
use App\Services\Accounting\Strategies\Manufacturing\JobCostingStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();

    // Seed required data
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    // Mock JournalService
    $this->journalServiceMock = Mockery::mock(JournalServiceInterface::class);
    $this->app->instance(JournalServiceInterface::class, $this->journalServiceMock);

    $this->strategy = new JobCostingStrategy($this->journalServiceMock);
});

describe('JobCostingStrategy', function () {

    it('returns identifier "job_costing"', function () {
        expect($this->strategy->getIdentifier())->toBe('job_costing');
    });

    it('calculates total cost from material consumptions', function () {
        $workOrder = WorkOrder::factory()->create();

        MaterialConsumption::factory()->forWorkOrder($workOrder)->create([
            'total_cost' => 100000,
        ]);

        MaterialConsumption::factory()->forWorkOrder($workOrder)->create([
            'total_cost' => 50000,
        ]);

        MaterialConsumption::factory()->forWorkOrder($workOrder)->create([
            'total_cost' => 25000,
        ]);

        $totalCost = $this->strategy->calculateTotalCost($workOrder);

        expect($totalCost)->toBe(175000);
    });

    it('returns zero when work order has no consumptions', function () {
        $workOrder = WorkOrder::factory()->create();

        $totalCost = $this->strategy->calculateTotalCost($workOrder);

        expect($totalCost)->toBe(0);
    });
});

describe('onWorkOrderStart', function () {

    it('returns null as no journal entry is created on start', function () {
        $workOrder = WorkOrder::factory()->create();

        $result = $this->strategy->onWorkOrderStart($workOrder);

        expect($result)->toBeNull();
    });
});

describe('onMaterialConsumption', function () {

    it('creates journal entry for material consumption', function () {
        $product = Product::factory()->create(['name' => 'Steel Plate']);
        $workOrder = WorkOrder::factory()->create(['wo_number' => 'WO-2024-001']);

        $consumption = MaterialConsumption::factory()
            ->forWorkOrder($workOrder)
            ->create([
                'product_id' => $product->id,
                'quantity_consumed' => 10,
                'unit_cost' => 5000,
                'total_cost' => 50000,
                'consumed_date' => '2024-01-15',
            ]);

        $mockEntry = Mockery::mock(JournalEntry::class);

        $this->journalServiceMock
            ->shouldReceive('createEntry')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['entry_date'] === '2024-01-15'
                    && $data['reference'] === 'WO-2024-001'
                    && $data['description'] === 'Konsumsi Material (Job Costing): WO-2024-001'
                    && $data['source_type'] === MaterialConsumption::class
                    && $data['source_id'] === 1
                    && count($data['lines']) === 2
                    && $data['lines'][0]['account_code'] === config('accounting.default_accounts.wip', '1-1450')
                    && $data['lines'][0]['description'] === 'Konsumsi material: Steel Plate'
                    && $data['lines'][0]['debit'] === 50000
                    && $data['lines'][0]['credit'] === 0
                    && $data['lines'][1]['account_code'] === config('accounting.default_accounts.inventory', '1-1400')
                    && $data['lines'][1]['description'] === 'Transfer ke WIP: WO-2024-001'
                    && $data['lines'][1]['debit'] === 0
                    && $data['lines'][1]['credit'] === 50000;
            }))
            ->andReturn($mockEntry);

        $result = $this->strategy->onMaterialConsumption($consumption);

        expect($result)->toBe($mockEntry);
    });

    it('returns null when total_cost is zero', function () {
        $product = Product::factory()->create();
        $workOrder = WorkOrder::factory()->create();

        $consumption = MaterialConsumption::factory()
            ->forWorkOrder($workOrder)
            ->create([
                'total_cost' => 0,
            ]);

        $this->journalServiceMock->shouldNotReceive('createEntry');

        $result = $this->strategy->onMaterialConsumption($consumption);

        expect($result)->toBeNull();
    });

    it('returns null when total_cost is negative', function () {
        $product = Product::factory()->create();
        $workOrder = WorkOrder::factory()->create();

        $consumption = MaterialConsumption::factory()
            ->forWorkOrder($workOrder)
            ->create([
                'total_cost' => -100,
            ]);

        $this->journalServiceMock->shouldNotReceive('createEntry');

        $result = $this->strategy->onMaterialConsumption($consumption);

        expect($result)->toBeNull();
    });

    it('uses consumed_date when available', function () {
        $product = Product::factory()->create();
        $workOrder = WorkOrder::factory()->create();

        $consumption = MaterialConsumption::factory()
            ->forWorkOrder($workOrder)
            ->create([
                'product_id' => $product->id,
                'total_cost' => 10000,
                'consumed_date' => '2024-06-15',
            ]);

        $mockEntry = Mockery::mock(JournalEntry::class);

        $this->journalServiceMock
            ->shouldReceive('createEntry')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['entry_date'] === '2024-06-15';
            }))
            ->andReturn($mockEntry);

        $this->strategy->onMaterialConsumption($consumption);
    });

    it('uses current date when consumed_date is not explicitly set', function () {
        $product = Product::factory()->create();
        $workOrder = WorkOrder::factory()->create();

        // MaterialConsumption factory already sets consumed_date to now()
        $consumption = MaterialConsumption::factory()
            ->forWorkOrder($workOrder)
            ->create([
                'product_id' => $product->id,
                'total_cost' => 10000,
            ]);

        $mockEntry = Mockery::mock(JournalEntry::class);

        $this->journalServiceMock
            ->shouldReceive('createEntry')
            ->once()
            ->with(Mockery::on(function ($data) {
                // Entry date should be formatted as Y-m-d
                return is_string($data['entry_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['entry_date']);
            }))
            ->andReturn($mockEntry);

        $this->strategy->onMaterialConsumption($consumption);
    });
});

describe('onWorkOrderComplete', function () {

    it('creates journal entry when work order completes', function () {
        $product1 = Product::factory()->create(['name' => 'Steel Plate']);
        $product2 = Product::factory()->create(['name' => 'Copper Wire']);

        $workOrder = WorkOrder::factory()->create([
            'wo_number' => 'WO-2024-002',
            'completed_at' => '2024-02-20 14:30:00',
        ]);

        MaterialConsumption::factory()
            ->forWorkOrder($workOrder)
            ->create([
                'product_id' => $product1->id,
                'total_cost' => 100000,
            ]);

        MaterialConsumption::factory()
            ->forWorkOrder($workOrder)
            ->create([
                'product_id' => $product2->id,
                'total_cost' => 75000,
            ]);

        $mockEntry = Mockery::mock(JournalEntry::class);

        $this->journalServiceMock
            ->shouldReceive('createEntry')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['entry_date'] === '2024-02-20'
                    && $data['reference'] === 'WO-2024-002'
                    && $data['description'] === 'Penyelesaian Work Order (Job Costing): WO-2024-002'
                    && $data['source_type'] === WorkOrder::class
                    && count($data['lines']) === 2
                    && $data['lines'][0]['account_code'] === config('accounting.default_accounts.finished_goods', '1-1410')
                    && $data['lines'][0]['description'] === 'Barang jadi (Job Costing): WO-2024-002'
                    && $data['lines'][0]['debit'] === 175000
                    && $data['lines'][0]['credit'] === 0
                    && $data['lines'][1]['account_code'] === config('accounting.default_accounts.wip', '1-1450')
                    && $data['lines'][1]['description'] === 'Transfer dari WIP: WO-2024-002'
                    && $data['lines'][1]['debit'] === 0
                    && $data['lines'][1]['credit'] === 175000;
            }))
            ->andReturn($mockEntry);

        $result = $this->strategy->onWorkOrderComplete($workOrder);

        expect($result)->toBe($mockEntry);
    });

    it('returns null when total WIP cost is zero', function () {
        $workOrder = WorkOrder::factory()->create();

        $this->journalServiceMock->shouldNotReceive('createEntry');

        $result = $this->strategy->onWorkOrderComplete($workOrder);

        expect($result)->toBeNull();
    });

    it('returns null when total WIP cost is negative', function () {
        $product = Product::factory()->create();
        $workOrder = WorkOrder::factory()->create();

        MaterialConsumption::factory()
            ->forWorkOrder($workOrder)
            ->create([
                'product_id' => $product->id,
                'total_cost' => -50000,
            ]);

        $this->journalServiceMock->shouldNotReceive('createEntry');

        $result = $this->strategy->onWorkOrderComplete($workOrder);

        expect($result)->toBeNull();
    });

    it('uses completed_at when available', function () {
        $product = Product::factory()->create();
        $workOrder = WorkOrder::factory()->create([
            'completed_at' => '2024-08-25 10:15:00',
        ]);

        MaterialConsumption::factory()
            ->forWorkOrder($workOrder)
            ->create([
                'product_id' => $product->id,
                'total_cost' => 100000,
            ]);

        $mockEntry = Mockery::mock(JournalEntry::class);

        $this->journalServiceMock
            ->shouldReceive('createEntry')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['entry_date'] === '2024-08-25';
            }))
            ->andReturn($mockEntry);

        $this->strategy->onWorkOrderComplete($workOrder);
    });

    it('uses current date when completed_at is null', function () {
        $product = Product::factory()->create();
        $workOrder = WorkOrder::factory()->create([
            'completed_at' => null,
        ]);

        MaterialConsumption::factory()
            ->forWorkOrder($workOrder)
            ->create([
                'product_id' => $product->id,
                'total_cost' => 100000,
            ]);

        $mockEntry = Mockery::mock(JournalEntry::class);

        $this->journalServiceMock
            ->shouldReceive('createEntry')
            ->once()
            ->with(Mockery::on(function ($data) {
                // Entry date should be formatted as Y-m-d
                return is_string($data['entry_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['entry_date']);
            }))
            ->andReturn($mockEntry);

        $this->strategy->onWorkOrderComplete($workOrder);
    });

    it('sums multiple material consumptions correctly', function () {
        $product = Product::factory()->create();
        $workOrder = WorkOrder::factory()->create([
            'wo_number' => 'WO-2024-003',
            'completed_at' => '2024-03-15',
        ]);

        // Create multiple consumptions
        MaterialConsumption::factory()
            ->forWorkOrder($workOrder)
            ->create([
                'product_id' => $product->id,
                'total_cost' => 25000,
            ]);

        MaterialConsumption::factory()
            ->forWorkOrder($workOrder)
            ->create([
                'product_id' => $product->id,
                'total_cost' => 35000,
            ]);

        MaterialConsumption::factory()
            ->forWorkOrder($workOrder)
            ->create([
                'product_id' => $product->id,
                'total_cost' => 40000,
            ]);

        $mockEntry = Mockery::mock(JournalEntry::class);

        $this->journalServiceMock
            ->shouldReceive('createEntry')
            ->once()
            ->with(Mockery::on(function ($data) {
                // Total should be 25000 + 35000 + 40000 = 100000
                return $data['lines'][0]['debit'] === 100000
                    && $data['lines'][1]['credit'] === 100000;
            }))
            ->andReturn($mockEntry);

        $this->strategy->onWorkOrderComplete($workOrder);
    });
});
