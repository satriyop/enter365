<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;

describe('Scheduled tasks registration', function () {
    it('registers mark-overdue command in schedule', function () {
        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $overdueEvent = $events->first(fn ($event) => str_contains($event->command ?? '', 'documents:mark-overdue'));

        expect($overdueEvent)->not->toBeNull();
    });

    it('registers send-reminders command in schedule', function () {
        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $reminderEvent = $events->first(fn ($event) => str_contains($event->command ?? '', 'accounting:send-reminders'));

        expect($reminderEvent)->not->toBeNull();
    });

    it('registers generate-recurring command in schedule', function () {
        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $recurringEvent = $events->first(fn ($event) => str_contains($event->command ?? '', 'accounting:generate-recurring'));

        expect($recurringEvent)->not->toBeNull();
    });

    it('has all three required scheduled tasks', function () {
        $schedule = app(Schedule::class);
        $events = collect($schedule->events());
        $commands = $events->map(fn ($event) => $event->command ?? '');

        $required = [
            'documents:mark-overdue',
            'accounting:send-reminders',
            'accounting:generate-recurring',
        ];

        foreach ($required as $command) {
            expect($commands->contains(fn ($cmd) => str_contains($cmd, $command)))
                ->toBeTrue("Scheduled command '{$command}' not found");
        }
    });
});

describe('Email template existence', function () {
    it('has payment-confirmation email template', function () {
        $templatePath = resource_path('views/emails/payment-confirmation.blade.php');

        expect(file_exists($templatePath))->toBeTrue();
    });
});
