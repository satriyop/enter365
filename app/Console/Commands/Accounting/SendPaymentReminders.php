<?php

namespace App\Console\Commands\Accounting;

use App\Services\Accounting\ReminderService;
use Illuminate\Console\Command;

class SendPaymentReminders extends Command
{
    protected $signature = 'accounting:send-reminders';

    protected $description = 'Send payment reminders for upcoming and overdue invoices/bills';

    public function handle(ReminderService $reminderService): int
    {
        if (! config('accounting.notifications.payment_reminder.enabled', true)) {
            $this->warn('Payment reminders are disabled.');

            return Command::SUCCESS;
        }

        $this->info('Sending payment reminders...');

        $sent = $reminderService->sendDueReminders();

        if ($sent->isEmpty()) {
            $this->info('No reminders were due to be sent.');

            return Command::SUCCESS;
        }

        $this->info("Sent {$sent->count()} reminder(s):");

        foreach ($sent as $reminder) {
            $document = $reminder->remindable;
            $documentNumber = $document->invoice_number ?? $document->bill_number ?? 'N/A';
            $this->line("  - {$reminder->type}: {$documentNumber} to {$reminder->contact->name}");
        }

        return Command::SUCCESS;
    }
}
