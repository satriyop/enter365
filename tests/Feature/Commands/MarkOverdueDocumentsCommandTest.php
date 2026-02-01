<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();

    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
});

describe('documents:mark-overdue command', function () {
    it('returns success when no overdue documents exist', function () {
        $this->artisan('documents:mark-overdue')
            ->expectsOutputToContain('No overdue invoices found')
            ->expectsOutputToContain('No overdue bills found')
            ->assertSuccessful();
    });

    it('marks overdue invoices', function () {
        // Create a sent invoice past due date
        $invoice = Invoice::factory()->sent()->create([
            'due_date' => now()->subDays(5),
        ]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

        $this->artisan('documents:mark-overdue')
            ->assertSuccessful();

        $invoice->refresh();
        expect($invoice->status)->toBe(DocumentStatus::Overdue);
    });

    it('marks overdue bills', function () {
        $bill = Bill::factory()->received()->create([
            'due_date' => now()->subDays(5),
        ]);

        $this->artisan('documents:mark-overdue')
            ->assertSuccessful();

        $bill->refresh();
        expect($bill->status)->toBe(DocumentStatus::Overdue);
    });

    it('does not mark draft documents as overdue', function () {
        Invoice::factory()->draft()->create([
            'due_date' => now()->subDays(5),
        ]);

        Bill::factory()->draft()->create([
            'due_date' => now()->subDays(5),
        ]);

        $this->artisan('documents:mark-overdue')
            ->expectsOutputToContain('No overdue invoices found')
            ->expectsOutputToContain('No overdue bills found')
            ->assertSuccessful();
    });

    it('does not mark already-paid documents as overdue', function () {
        Invoice::factory()->create([
            'status' => DocumentStatus::Paid,
            'due_date' => now()->subDays(5),
        ]);

        $this->artisan('documents:mark-overdue')
            ->expectsOutputToContain('No overdue invoices found')
            ->assertSuccessful();
    });

    it('runs in dry-run mode without making changes', function () {
        $invoice = Invoice::factory()->sent()->create([
            'due_date' => now()->subDays(5),
        ]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

        $this->artisan('documents:mark-overdue --dry-run')
            ->expectsOutputToContain('DRY RUN MODE')
            ->assertSuccessful();

        $invoice->refresh();
        expect($invoice->status)->toBe(DocumentStatus::Sent);
    });

    it('marks partial invoices as overdue', function () {
        $invoice = Invoice::factory()->create([
            'status' => DocumentStatus::Partial,
            'due_date' => now()->subDays(3),
            'paid_amount' => 500000,
        ]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

        $this->artisan('documents:mark-overdue')
            ->assertSuccessful();

        $invoice->refresh();
        expect($invoice->status)->toBe(DocumentStatus::Overdue);
    });
});
