<?php

namespace App\Console\Commands\Accounting;

use App\Services\Accounting\OverdueService;
use Illuminate\Console\Command;

class MarkOverdueDocuments extends Command
{
    protected $signature = 'accounting:mark-overdue';

    protected $description = 'Mark overdue invoices and bills that have passed their due date';

    public function handle(OverdueService $overdueService): int
    {
        $this->info('Checking for overdue documents...');

        $result = $overdueService->markAllOverdue();

        $invoiceCount = $result['invoices']->count();
        $billCount = $result['bills']->count();

        if ($invoiceCount > 0) {
            $this->info("Marked {$invoiceCount} invoice(s) as overdue.");
            foreach ($result['invoices'] as $invoice) {
                $this->line("  - {$invoice->invoice_number}");
            }
        }

        if ($billCount > 0) {
            $this->info("Marked {$billCount} bill(s) as overdue.");
            foreach ($result['bills'] as $bill) {
                $this->line("  - {$bill->bill_number}");
            }
        }

        if ($invoiceCount === 0 && $billCount === 0) {
            $this->info('No documents needed to be marked as overdue.');
        }

        return Command::SUCCESS;
    }
}
