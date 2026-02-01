<?php

declare(strict_types=1);

use App\Jobs\RunMrpCalculation;
use App\Models\Manufacturing\MrpRun;
use App\Services\Manufacturing\MrpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
});

describe('RunMrpCalculation job', function () {
    it('can be dispatched to the queue', function () {
        Queue::fake();

        $run = MrpRun::factory()->draft()->create();

        RunMrpCalculation::dispatch($run, 1);

        Queue::assertPushed(RunMrpCalculation::class, function ($job) use ($run) {
            return $job->run->id === $run->id
                && $job->userId === 1;
        });
    });

    it('has correct timeout and retry configuration', function () {
        $run = MrpRun::factory()->draft()->create();
        $job = new RunMrpCalculation($run);

        expect($job->tries)->toBe(1)
            ->and($job->timeout)->toBe(900);
    });

    it('calls MrpService executeRun', function () {
        $run = MrpRun::factory()->draft()->create();

        $mockService = Mockery::mock(MrpService::class);
        $mockService->shouldReceive('executeRun')
            ->once()
            ->with($run, 42)
            ->andReturn($run);

        $job = new RunMrpCalculation($run, 42);
        $job->handle($mockService);
    });
});
