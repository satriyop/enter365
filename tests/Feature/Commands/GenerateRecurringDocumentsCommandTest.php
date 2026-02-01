<?php

declare(strict_types=1);

use App\Services\Sales\RecurringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
});

describe('accounting:generate-recurring command', function () {
    it('returns success when recurring is disabled', function () {
        config(['accounting.recurring.enabled' => false]);

        $this->artisan('accounting:generate-recurring')
            ->expectsOutputToContain('Recurring documents feature is disabled')
            ->assertSuccessful();
    });

    it('returns success when no recurring documents are due', function () {
        config(['accounting.recurring.enabled' => true]);

        $mockService = Mockery::mock(RecurringService::class);
        $mockService->shouldReceive('generateDueDocuments')
            ->once()
            ->andReturn(new Collection);

        app()->instance(RecurringService::class, $mockService);

        $this->artisan('accounting:generate-recurring')
            ->expectsOutputToContain('No recurring documents were due')
            ->assertSuccessful();
    });
});
