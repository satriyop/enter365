<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes - Indonesian Accounting System
|--------------------------------------------------------------------------
|
| Scheduled tasks for the accounting system automation.
|
*/

// Mark overdue invoices and bills daily at 1:00 AM
Schedule::command('accounting:mark-overdue')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/overdue.log'));

// Generate recurring documents daily at 6:00 AM
Schedule::command('accounting:generate-recurring')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/recurring.log'));

// Send payment reminders daily at 8:00 AM
Schedule::command('accounting:send-reminders')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/reminders.log'));
