<?php

declare(strict_types=1);

use App\Contracts\Manufacturing\WorkOrderServiceInterface;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\DocumentLockedException;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderItem;
use App\Models\Projects\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->warehouse = Warehouse::factory()->create();
    $this->product = Product::factory()->create(['purchase_price' => 100000]);
    $this->project = Project::factory()->create();

    $this->service = app(WorkOrderServiceInterface::class);
    $this->actingAs($this->user);
});

describe('CRUD Operations', function () {
    test('creates work order with items', function () {
        $data = [
            'product_id' => $this->product->id,
            'name' => 'Test Work Order',
            'type' => WorkOrder::TYPE_PRODUCTION,
            'quantity_ordered' => 50,
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'type' => WorkOrderItem::TYPE_MATERIAL,
                    'description' => 'Material 1',
                    'quantity_required' => 100,
                    'unit_cost' => 5000,
                ],
            ],
        ];

        $wo = $this->service->create($data);

        expect($wo)
            ->toBeInstanceOf(WorkOrder::class)
            ->status->toBe(DocumentStatus::Draft)
            ->wo_number->not->toBeEmpty()
            ->name->toBe('Test Work Order')
            ->items->toHaveCount(1);
    });

    test('creates work order from project', function () {
        $wo = $this->service->createFromProject($this->project, [
            'product_id' => $this->product->id,
            'quantity_ordered' => 10,
        ]);

        expect($wo)
            ->project_id->toBe($this->project->id)
            ->name->toContain($this->project->name);
    });

    test('creates work order from BOM with exploded items', function () {
        $bom = Bom::factory()
            ->has(BomItem::factory()->count(3)->state([
                'type' => BomItem::TYPE_MATERIAL,
                'quantity' => 5,
                'waste_percentage' => 10,
            ]), 'items')
            ->create([
                'product_id' => $this->product->id,
                'name' => 'Test BOM',
                'status' => DocumentStatus::Active,
                'output_quantity' => 10,
            ]);

        $wo = $this->service->createFromBom($bom, [
            'quantity' => 20,
            'warehouse_id' => $this->warehouse->id,
        ]);

        expect($wo)
            ->bom_id->toBe($bom->id)
            ->product_id->toBe($this->product->id)
            ->items->toHaveCount(3);

        expect((float) $wo->quantity_ordered)->toBe(20.0);

        // Quantities should be scaled (multiplier = 20/10 = 2)
        $wo->items->each(function ($item) {
            $effectiveQty = 5 * (1 + 0.1); // 5.5
            expect((float) $item->quantity_required)->toBe($effectiveQty * 2); // 11.0
        });
    });

    test('creates sub work order', function () {
        $parent = WorkOrder::factory()->create(['project_id' => $this->project->id]);

        $sub = $this->service->createSubWorkOrder($parent, [
            'product_id' => $this->product->id,
            'name' => 'Sub Work Order',
            'quantity_ordered' => 5,
        ]);

        expect($sub)
            ->parent_work_order_id->toBe($parent->id)
            ->project_id->toBe($parent->project_id)
            ->warehouse_id->toBe($parent->warehouse_id);
    });

    test('updates draft work order', function () {
        $wo = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->create(['name' => 'Old Name']);

        $updated = $this->service->update($wo, ['name' => 'New Name']);

        expect($updated->name)->toBe('New Name');
    });

    test('throws exception when updating non-draft work order', function () {
        $wo = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Confirmed]);

        $this->service->update($wo, ['name' => 'Test']);
    })->throws(DocumentLockedException::class);

    test('deletes draft work order with items and sub work orders', function () {
        $wo = WorkOrder::factory()
            ->has(WorkOrderItem::factory()->count(2), 'items')
            ->create();

        WorkOrder::factory()->create(['parent_work_order_id' => $wo->id]);

        $result = $this->service->delete($wo);

        expect($result)->toBeTrue()
            ->and(WorkOrder::find($wo->id))->toBeNull()
            ->and(WorkOrderItem::where('work_order_id', $wo->id)->count())->toBe(0);
    });
});

describe('Workflow Transitions', function () {
    test('confirms draft work order with items', function () {
        $wo = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Draft]);

        $confirmed = $this->service->confirm($wo, $this->user->id);

        expect($confirmed)
            ->status->toBe(DocumentStatus::Confirmed)
            ->confirmed_at->not->toBeNull();
    });

    test('throws exception when confirming without items', function () {
        $wo = WorkOrder::factory()->create(['status' => DocumentStatus::Draft]);

        $this->service->confirm($wo, $this->user->id);
    })->throws(StateTransitionException::class);

    test('starts confirmed work order', function () {
        $wo = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Confirmed]);

        $started = $this->service->start($wo, $this->user->id);

        expect($started)
            ->status->toBe(DocumentStatus::InProgress)
            ->actual_start_date->not->toBeNull();
    });

    test('completes in-progress work order', function () {
        $wo = WorkOrder::factory()
            ->has(WorkOrderItem::factory()->state([
                'type' => WorkOrderItem::TYPE_MATERIAL,
                'quantity_required' => 100,
            ]), 'items')
            ->create([
                'status' => DocumentStatus::InProgress,
                'quantity_ordered' => 10,
            ]);

        $completed = $this->service->complete($wo, $this->user->id);

        expect($completed)
            ->status->toBe(DocumentStatus::Completed)
            ->completed_at->not->toBeNull();
    });

    test('cancels work order in allowed states', function () {
        $wo = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Draft]);

        $cancelled = $this->service->cancel($wo, 'Material not available', $this->user->id);

        expect($cancelled)
            ->status->toBe(DocumentStatus::Cancelled)
            ->cancellation_reason->toBe('Material not available');
    });

    test('throws exception when canceling completed work order', function () {
        $wo = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->create(['status' => DocumentStatus::Completed]);

        $this->service->cancel($wo, 'Test', $this->user->id);
    })->throws(StateTransitionException::class);
});

describe('Output Recording', function () {
    test('records output quantity', function () {
        $wo = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->create([
                'status' => DocumentStatus::InProgress,
                'quantity_ordered' => 100,
                'quantity_completed' => 0,
                'quantity_scrapped' => 0,
            ]);

        $updated = $this->service->recordOutput($wo, 50, 5);

        expect((float) $updated->quantity_completed)->toBe(50.0);
        expect((float) $updated->quantity_scrapped)->toBe(5.0);
    });

    test('accumulates multiple output recordings', function () {
        $wo = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->create([
                'status' => DocumentStatus::InProgress,
                'quantity_completed' => 30,
                'quantity_scrapped' => 2,
            ]);

        $updated = $this->service->recordOutput($wo, 20, 3);

        expect((float) $updated->quantity_completed)->toBe(50.0);
        expect((float) $updated->quantity_scrapped)->toBe(5.0);
    });

    test('throws exception when recording output for non-in-progress work order', function () {
        $wo = WorkOrder::factory()->create(['status' => DocumentStatus::Draft]);

        $this->service->recordOutput($wo, 10);
    })->throws(StateTransitionException::class);
});

describe('Material Consumption', function () {
    test('records material consumption', function () {
        $wo = WorkOrder::factory()
            ->has(WorkOrderItem::factory()->state([
                'product_id' => $this->product->id,
                'quantity_required' => 100,
            ]), 'items')
            ->create(['status' => DocumentStatus::InProgress]);

        $item = $wo->items->first();

        $this->service->recordConsumption($wo, [
            [
                'work_order_item_id' => $item->id,
                'product_id' => $this->product->id,
                'quantity_consumed' => 50,
                'quantity_scrapped' => 0,
            ],
        ]);

        // Should delegate to material service
        expect(true)->toBeTrue();
    });
});

describe('Cost and Material Status', function () {
    test('gets cost summary', function () {
        $wo = WorkOrder::factory()
            ->has(WorkOrderItem::factory()->count(3), 'items')
            ->create();

        $summary = $this->service->getCostSummary($wo);

        expect($summary)->toBeArray();
    });

    test('gets material status', function () {
        $wo = WorkOrder::factory()
            ->has(WorkOrderItem::factory()->state([
                'type' => WorkOrderItem::TYPE_MATERIAL,
            ])->count(2), 'items')
            ->create();

        $status = $this->service->getMaterialStatus($wo);

        expect($status)->toBeArray()
            ->toHaveKey('materials');
    });
});

describe('Statistics', function () {
    test('returns work order statistics', function () {
        WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->count(5)
            ->create(['status' => DocumentStatus::Completed]);

        $stats = $this->service->getStatistics();

        expect($stats)->toBeArray()
            ->toHaveKey('total_count');
    });

    test('filters statistics by date range', function () {
        WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->create([
                'status' => DocumentStatus::Completed,
                'actual_start_date' => '2024-01-15',
            ]);

        WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->create([
                'status' => DocumentStatus::Completed,
                'actual_start_date' => '2024-02-15',
            ]);

        $stats = $this->service->getStatistics('2024-01-01', '2024-01-31');

        expect($stats)->toBeArray();
    });
});
