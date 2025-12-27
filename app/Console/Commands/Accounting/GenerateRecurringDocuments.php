<?php

namespace App\Console\Commands\Accounting;

use App\Models\Accounting\Bill;
use App\Models\Accounting\Invoice;
use App\Services\Accounting\RecurringService;
use Illuminate\Console\Command;

class GenerateRecurringDocuments extends Command
{
    protected $signature = 'accounting:generate-recurring';

    protected $description = 'Generate invoices and bills from recurring templates that are due';

    public function handle(RecurringService $recurringService): int
    {
        if (! config('accounting.recurring.enabled', true)) {
            $this->warn('Recurring documents feature is disabled.');

            return Command::SUCCESS;
        }

        $this->info('Generating recurring documents...');

        $generated = $recurringService->generateDueDocuments();

        if ($generated->isEmpty()) {
            $this->info('No recurring documents were due for generation.');

            return Command::SUCCESS;
        }

        $invoiceCount = $generated->filter(fn ($doc) => $doc instanceof Invoice)->count();
        $billCount = $generated->filter(fn ($doc) => $doc instanceof Bill)->count();

        $this->info("Generated {$generated->count()} document(s):");

        if ($invoiceCount > 0) {
            $this->line("  - {$invoiceCount} invoice(s)");
        }

        if ($billCount > 0) {
            $this->line("  - {$billCount} bill(s)");
        }

        foreach ($generated as $document) {
            if ($document instanceof Invoice) {
                $this->line("    Invoice: {$document->invoice_number} - {$document->contact->name}");
            } elseif ($document instanceof Bill) {
                $this->line("    Bill: {$document->bill_number} - {$document->contact->name}");
            }
        }

        return Command::SUCCESS;
    }
}
