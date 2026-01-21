# Phase 3: Auto-Overdue Scheduler

> **Type**: FIX
> **Status**: 📋 READY TO IMPLEMENT
> **Priority**: Medium
> **Effort**: Small (2-3 hours)

## Problem

Invoices past `due_date` remain in `Sent` or `Partial` status indefinitely. The system does not automatically detect and mark them as overdue.

Current behavior:
- User must manually call API to mark overdue
- No scheduler/cron job exists
- Overdue invoices appear in "active" lists incorrectly

### User Story

> As an accounts receivable manager, I want overdue invoices to be automatically marked so that my dashboard accurately reflects collection status without manual intervention.

---

## Solution

Create Artisan command that runs daily to mark overdue invoices.

---

## Implementation

### 1. Artisan Command

```php
// app/Console/Commands/MarkOverdueInvoicesCommand.php

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Sales\InvoiceServiceInterface;
use App\Enums\DocumentStatus;
use App\Models\Sales\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MarkOverdueInvoicesCommand extends Command
{
    protected $signature = 'invoices:mark-overdue
                            {--dry-run : Show which invoices would be marked without making changes}';

    protected $description = 'Mark invoices past due date as overdue';

    public function __construct(
        private InvoiceServiceInterface $invoiceService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Checking for overdue invoices...');

        $overdueInvoices = Invoice::query()
            ->whereIn('status', [
                DocumentStatus::Sent->value,
                DocumentStatus::Partial->value,
            ])
            ->where('due_date', '<', now()->startOfDay())
            ->get();

        if ($overdueInvoices->isEmpty()) {
            $this->info('No invoices to mark as overdue.');
            return self::SUCCESS;
        }

        $this->info("Found {$overdueInvoices->count()} invoice(s) past due date.");

        if ($this->option('dry-run')) {
            $this->table(
                ['ID', 'Number', 'Due Date', 'Days Overdue', 'Outstanding'],
                $overdueInvoices->map(fn ($inv) => [
                    $inv->id,
                    $inv->invoice_number,
                    $inv->due_date->format('Y-m-d'),
                    $inv->getDaysOverdue(),
                    number_format($inv->getOutstandingAmount() / 100, 2),
                ])
            );
            $this->warn('Dry run - no changes made.');
            return self::SUCCESS;
        }

        $marked = 0;
        $errors = 0;

        $this->withProgressBar($overdueInvoices, function (Invoice $invoice) use (&$marked, &$errors) {
            try {
                $result = $this->invoiceService->markAsOverdue($invoice);

                if ($result->isSuccess()) {
                    $marked++;
                    Log::info('Invoice marked as overdue', [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'due_date' => $invoice->due_date->toDateString(),
                        'days_overdue' => $invoice->getDaysOverdue(),
                    ]);
                } else {
                    $errors++;
                    Log::warning('Failed to mark invoice as overdue', [
                        'invoice_id' => $invoice->id,
                        'reason' => $result->getMessage(),
                    ]);
                }
            } catch (\Exception $e) {
                $errors++;
                Log::error('Error marking invoice as overdue', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        $this->newLine(2);
        $this->info("Marked {$marked} invoice(s) as overdue.");

        if ($errors > 0) {
            $this->warn("{$errors} invoice(s) could not be marked. Check logs for details.");
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
```

### 2. Schedule Registration

```php
// bootstrap/app.php (or routes/console.php for Laravel 11+)

use Illuminate\Console\Scheduling\Schedule;

// In withSchedule callback:
$schedule->command('invoices:mark-overdue')
    ->dailyAt('00:15')
    ->runInBackground()
    ->withoutOverlapping()
    ->onFailure(function () {
        // Alert monitoring (e.g., Sentry, Slack)
        Log::error('invoices:mark-overdue command failed');
    });
```

### 3. Optional: API Endpoint for Manual Trigger

```php
// app/Http/Controllers/Api/V1/InvoiceController.php

/**
 * Mark all overdue invoices.
 *
 * Triggers the same logic as the scheduled command.
 * Useful for manual intervention or testing.
 */
public function markAllOverdue(): JsonResponse
{
    $overdueInvoices = Invoice::query()
        ->whereIn('status', [
            DocumentStatus::Sent->value,
            DocumentStatus::Partial->value,
        ])
        ->where('due_date', '<', now()->startOfDay())
        ->get();

    $results = ['marked' => 0, 'failed' => 0];

    foreach ($overdueInvoices as $invoice) {
        $result = $this->invoiceService->markAsOverdue($invoice);
        $result->isSuccess() ? $results['marked']++ : $results['failed']++;
    }

    return response()->json([
        'message' => "Marked {$results['marked']} invoice(s) as overdue.",
        'results' => $results,
    ]);
}
```

```php
// routes/api.php

Route::post('invoices/mark-all-overdue', [InvoiceController::class, 'markAllOverdue'])
    ->middleware('can:manage,App\Models\Sales\Invoice'); // Add appropriate policy
```

---

## Tests

```php
// tests/Feature/Console/MarkOverdueInvoicesTest.php

<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

it('marks sent invoices past due as overdue', function () {
    $overdueInvoice = Invoice::factory()
        ->has(InvoiceItem::factory(), 'items')
        ->sent()
        ->create([
            'due_date' => now()->subDays(5),
        ]);

    $this->artisan('invoices:mark-overdue')
        ->assertSuccessful();

    expect($overdueInvoice->fresh()->status)->toBe(DocumentStatus::Overdue);
});

it('marks partial invoices past due as overdue', function () {
    $overdueInvoice = Invoice::factory()
        ->has(InvoiceItem::factory(), 'items')
        ->create([
            'status' => DocumentStatus::Partial,
            'due_date' => now()->subDays(10),
            'total_amount' => 1000000,
            'paid_amount' => 500000,
        ]);

    $this->artisan('invoices:mark-overdue')
        ->assertSuccessful();

    expect($overdueInvoice->fresh()->status)->toBe(DocumentStatus::Overdue);
});

it('does not mark invoices due today', function () {
    $dueToday = Invoice::factory()
        ->has(InvoiceItem::factory(), 'items')
        ->sent()
        ->create([
            'due_date' => now(),
        ]);

    $this->artisan('invoices:mark-overdue')
        ->assertSuccessful();

    expect($dueToday->fresh()->status)->toBe(DocumentStatus::Sent);
});

it('does not mark invoices due in future', function () {
    $notDue = Invoice::factory()
        ->has(InvoiceItem::factory(), 'items')
        ->sent()
        ->create([
            'due_date' => now()->addDays(30),
        ]);

    $this->artisan('invoices:mark-overdue')
        ->assertSuccessful();

    expect($notDue->fresh()->status)->toBe(DocumentStatus::Sent);
});

it('does not mark paid invoices', function () {
    $paidInvoice = Invoice::factory()
        ->has(InvoiceItem::factory(), 'items')
        ->create([
            'status' => DocumentStatus::Paid,
            'due_date' => now()->subDays(5),
            'total_amount' => 1000000,
            'paid_amount' => 1000000,
        ]);

    $this->artisan('invoices:mark-overdue')
        ->assertSuccessful();

    expect($paidInvoice->fresh()->status)->toBe(DocumentStatus::Paid);
});

it('does not mark cancelled invoices', function () {
    $cancelledInvoice = Invoice::factory()
        ->has(InvoiceItem::factory(), 'items')
        ->create([
            'status' => DocumentStatus::Cancelled,
            'due_date' => now()->subDays(5),
        ]);

    $this->artisan('invoices:mark-overdue')
        ->assertSuccessful();

    expect($cancelledInvoice->fresh()->status)->toBe(DocumentStatus::Cancelled);
});

it('dry run shows invoices without marking', function () {
    $overdueInvoice = Invoice::factory()
        ->has(InvoiceItem::factory(), 'items')
        ->sent()
        ->create([
            'due_date' => now()->subDays(5),
        ]);

    $this->artisan('invoices:mark-overdue', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Dry run');

    // Status should NOT change
    expect($overdueInvoice->fresh()->status)->toBe(DocumentStatus::Sent);
});

it('handles multiple overdue invoices', function () {
    Invoice::factory()
        ->has(InvoiceItem::factory(), 'items')
        ->sent()
        ->count(5)
        ->create([
            'due_date' => now()->subDays(5),
        ]);

    $this->artisan('invoices:mark-overdue')
        ->assertSuccessful()
        ->expectsOutputToContain('Marked 5 invoice(s)');

    expect(Invoice::where('status', DocumentStatus::Overdue)->count())->toBe(5);
});

it('reports success when no invoices to mark', function () {
    $this->artisan('invoices:mark-overdue')
        ->assertSuccessful()
        ->expectsOutputToContain('No invoices to mark');
});
```

---

## Verification

```bash
# Test the command with dry-run
php artisan invoices:mark-overdue --dry-run

# Run the command
php artisan invoices:mark-overdue

# Verify schedule is registered
php artisan schedule:list | grep invoices

# Run tests
php artisan test --filter=MarkOverdueInvoices
```

---

## Checklist

- [ ] Command created
- [ ] Command registered (auto-discovery)
- [ ] Schedule added to bootstrap/app.php
- [ ] Dry-run option working
- [ ] Progress bar for large batches
- [ ] Logging for audit trail
- [ ] Tests written and passing
- [ ] Optional: API endpoint added
- [ ] Monitoring/alerting configured

---

## Rollback

Remove:
- `app/Console/Commands/MarkOverdueInvoicesCommand.php`
- Schedule entry from `bootstrap/app.php`
- Optional API route
