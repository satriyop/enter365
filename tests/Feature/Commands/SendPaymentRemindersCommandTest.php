<?php

declare(strict_types=1);

use App\Services\Sales\ReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
});

describe('accounting:send-reminders command', function () {
    it('returns success when reminders are disabled', function () {
        config(['accounting.notifications.payment_reminder.enabled' => false]);

        $this->artisan('accounting:send-reminders')
            ->expectsOutputToContain('Payment reminders are disabled')
            ->assertSuccessful();
    });

    it('returns success when no reminders are due', function () {
        $mockService = Mockery::mock(ReminderService::class);
        $mockService->shouldReceive('sendDueReminders')
            ->once()
            ->andReturn(new Collection);

        app()->instance(ReminderService::class, $mockService);

        $this->artisan('accounting:send-reminders')
            ->expectsOutputToContain('No reminders were due')
            ->assertSuccessful();
    });

    it('exits successfully when reminders are enabled', function () {
        config(['accounting.notifications.payment_reminder.enabled' => true]);

        $mockService = Mockery::mock(ReminderService::class);
        $mockService->shouldReceive('sendDueReminders')
            ->once()
            ->andReturn(new Collection);

        app()->instance(ReminderService::class, $mockService);

        $this->artisan('accounting:send-reminders')
            ->assertSuccessful();
    });
});
