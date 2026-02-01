<?php

declare(strict_types=1);

use App\Jobs\CloseFiscalPeriod;
use App\Models\Accounting\FiscalPeriod;
use App\Services\Accounting\FiscalPeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
});

describe('CloseFiscalPeriod job', function () {
    it('can be dispatched to the queue', function () {
        Queue::fake();

        $period = FiscalPeriod::factory()->create();

        CloseFiscalPeriod::dispatch($period, 'Year-end closing', 1);

        Queue::assertPushed(CloseFiscalPeriod::class, function ($job) use ($period) {
            return $job->period->id === $period->id
                && $job->notes === 'Year-end closing'
                && $job->userId === 1;
        });
    });

    it('has correct timeout and retry configuration', function () {
        $period = FiscalPeriod::factory()->create();
        $job = new CloseFiscalPeriod($period);

        expect($job->tries)->toBe(1)
            ->and($job->timeout)->toBe(600);
    });

    it('calls FiscalPeriodService closePeriod', function () {
        $period = FiscalPeriod::factory()->create();

        $mockService = Mockery::mock(FiscalPeriodService::class);
        $mockService->shouldReceive('withContext')
            ->once()
            ->andReturnSelf();
        $mockService->shouldReceive('closePeriod')
            ->once()
            ->with($period, 'Test notes')
            ->andReturn(['success' => true, 'message' => 'Period closed', 'closing_entry' => null]);

        $job = new CloseFiscalPeriod($period, 'Test notes', 1);
        $job->handle($mockService);
    });

    it('handles failed close gracefully', function () {
        $period = FiscalPeriod::factory()->create();

        $mockService = Mockery::mock(FiscalPeriodService::class);
        $mockService->shouldReceive('withContext')
            ->once()
            ->andReturnSelf();
        $mockService->shouldReceive('closePeriod')
            ->once()
            ->andReturn(['success' => false, 'message' => 'Period already closed', 'closing_entry' => null]);

        $job = new CloseFiscalPeriod($period, null, null);
        // Should not throw — failure is logged
        $job->handle($mockService);
    });
});
