<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\MrpRun;
use App\Models\User;
use App\Services\Manufacturing\MrpService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->service = app(MrpService::class);
    $this->warehouse = Warehouse::factory()->create();
});

describe('MrpService run management', function () {
    it('creates MRP run', function () {
        $run = $this->service->create([
            'name' => 'MRP Run January 2024',
            'warehouse_id' => $this->warehouse->id,
            'planning_horizon_start' => now()->toDateString(),
            'planning_horizon_end' => now()->addDays(30)->toDateString(),
            'planning_horizon_days' => 30,
        ]);

        expect($run)->toBeInstanceOf(MrpRun::class);
        expect($run->status)->toBe(DocumentStatus::Draft);
        expect($run->warehouse_id)->toBe($this->warehouse->id);
    });

    it('updates draft MRP run', function () {
        $run = MrpRun::factory()->create(['status' => DocumentStatus::Draft]);

        $updated = $this->service->update($run, [
            'name' => 'Updated MRP Run',
        ]);

        expect($updated->name)->toBe('Updated MRP Run');
    });

    it('deletes draft MRP run', function () {
        $run = MrpRun::factory()->create(['status' => DocumentStatus::Draft]);

        $deleted = $this->service->delete($run);

        expect($deleted)->toBeTrue();
        expect(MrpRun::find($run->id))->toBeNull();
    });

    it('gets MRP statistics', function () {
        MrpRun::factory()->create(['status' => DocumentStatus::Draft]);
        MrpRun::factory()->create(['status' => DocumentStatus::Completed]);

        $stats = $this->service->getStatistics();

        expect($stats)->toHaveKeys(['total_runs', 'by_status', 'last_completed_run']);
        expect($stats['total_runs'])->toBeGreaterThanOrEqual(2);
    });
});
