<?php

namespace App\Console\Commands;

use App\Contracts\Purchasing\BillServiceInterface;
use App\Contracts\Sales\InvoiceServiceInterface;
use App\Enums\DocumentStatus;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MarkOverdueDocuments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:mark-overdue
                            {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark invoices and bills as overdue if past due date';

    public function __construct(
        private readonly InvoiceServiceInterface $invoiceService,
        private readonly BillServiceInterface $billService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('Starting overdue document check...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $invoicesMarked = $this->markOverdueInvoices($dryRun);
        $billsMarked = $this->markOverdueBills($dryRun);

        $this->newLine();
        $this->info('Summary:');
        $this->table(
            ['Document Type', 'Marked Overdue'],
            [
                ['Invoices', $invoicesMarked],
                ['Bills', $billsMarked],
            ]
        );

        $total = $invoicesMarked + $billsMarked;

        if ($total > 0 && ! $dryRun) {
            Log::info('MarkOverdueDocuments: Marked documents as overdue', [
                'invoices' => $invoicesMarked,
                'bills' => $billsMarked,
            ]);
        }

        $this->info('Overdue document check completed.');

        return self::SUCCESS;
    }

    /**
     * Mark overdue invoices.
     */
    private function markOverdueInvoices(bool $dryRun): int
    {
        // Get invoices that are past due and not already marked as overdue/paid/cancelled
        $overdueInvoices = Invoice::query()
            ->where('due_date', '<', today())
            ->whereIn('status', [DocumentStatus::Sent, DocumentStatus::Partial])
            ->get();

        if ($overdueInvoices->isEmpty()) {
            $this->info('No overdue invoices found.');

            return 0;
        }

        $this->info("Found {$overdueInvoices->count()} overdue invoices:");

        $count = 0;

        foreach ($overdueInvoices as $invoice) {
            $daysOverdue = $invoice->getDaysOverdue();
            $contactName = $invoice->contact->name ?? 'Unknown';

            $this->line(sprintf(
                '  - %s | %s | Rp %s | %d days overdue',
                $invoice->invoice_number,
                $contactName,
                number_format($invoice->getOutstandingAmount(), 0, ',', '.'),
                $daysOverdue
            ));

            if (! $dryRun) {
                try {
                    $result = $this->invoiceService->markAsOverdue($invoice);
                    if ($result->isFailure()) {
                        $this->warn("    Failed to mark invoice {$invoice->invoice_number}: {$result->getMessage()}");

                        continue;
                    }
                } catch (\Throwable $e) {
                    $this->error("    Error marking invoice {$invoice->invoice_number}: {$e->getMessage()}");
                    Log::warning('MarkOverdueDocuments: Failed to mark invoice as overdue', [
                        'invoice_id' => $invoice->id,
                        'error' => $e->getMessage(),
                    ]);

                    continue;
                }
            }

            $count++;
        }

        return $count;
    }

    /**
     * Mark overdue bills.
     */
    private function markOverdueBills(bool $dryRun): int
    {
        // Get bills that are past due and not already marked as overdue/paid/cancelled
        $overdueBills = Bill::query()
            ->where('due_date', '<', today())
            ->whereIn('status', [DocumentStatus::Received, DocumentStatus::Partial])
            ->get();

        if ($overdueBills->isEmpty()) {
            $this->info('No overdue bills found.');

            return 0;
        }

        $this->info("Found {$overdueBills->count()} overdue bills:");

        $count = 0;

        foreach ($overdueBills as $bill) {
            $daysOverdue = (int) $bill->due_date->diffInDays(now());
            $contactName = $bill->contact->name ?? 'Unknown';
            $outstandingAmount = $bill->total_amount - ($bill->paid_amount ?? 0);

            $this->line(sprintf(
                '  - %s | %s | Rp %s | %d days overdue',
                $bill->bill_number,
                $contactName,
                number_format($outstandingAmount, 0, ',', '.'),
                $daysOverdue
            ));

            if (! $dryRun) {
                try {
                    $result = $this->billService->markAsOverdue($bill);
                    if ($result->isFailure()) {
                        $this->warn("    Failed to mark bill {$bill->bill_number}: {$result->getMessage()}");

                        continue;
                    }
                } catch (\Throwable $e) {
                    $this->error("    Error marking bill {$bill->bill_number}: {$e->getMessage()}");
                    Log::warning('MarkOverdueDocuments: Failed to mark bill as overdue', [
                        'bill_id' => $bill->id,
                        'error' => $e->getMessage(),
                    ]);

                    continue;
                }
            }

            $count++;
        }

        return $count;
    }
}
