<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/**
 * SALES-PEST-02: Invoice post + payment browser tests.
 *
 * Prerequisites:
 * - Seeded user: admin@example.com / password
 * - Seeded customer: PT Test Customer
 * - Seeded accounts: 1-1100 (AR), 4-1001 (Revenue), 2-1200 (PPN), 1-1010 (Bank BCA)
 *
 * Note: Browser tests hit the real API + PostgreSQL database.
 * DB assertions use a dedicated 'browser_pgsql' connection to bypass
 * the phpunit.xml sqlite override.
 */

/**
 * Get a database connection to the real PostgreSQL used by the API server.
 * phpunit.xml overrides DB_DATABASE to :memory: for sqlite, so we configure
 * a separate connection with the real database name.
 */
function realDb(): \Illuminate\Database\ConnectionInterface
{
    if (! config()->has('database.connections.browser_pgsql')) {
        config(['database.connections.browser_pgsql' => array_merge(
            config('database.connections.pgsql'),
            ['database' => env('BROWSER_DB_DATABASE', 'akuntansi')]
        )]);
    }

    return DB::connection('browser_pgsql');
}

/**
 * Helper: create an invoice via the SPA and return the page on the detail view.
 */
function createInvoice(string $description = 'E2E Test Item', int $qty = 10, string $price = '100000')
{
    $page = loginAndVisit('/invoices/new');

    $page->assertSee('New Invoice');

    // Select customer — Radix-Vue Select
    $page->click('Select customer...');
    $page->click('[role="option"] >> text=PT Test Customer');

    // Fill line item description
    $page->fill('input[placeholder="Item description"]', $description);

    // Fill quantity
    $page->fill('table input[type="number"][min="1"]', (string) $qty);

    // Fill unit price (CurrencyInput in the table row, not the discount field below)
    // Triple-click to select all text, then type the raw number
    $page->click('td input[inputmode="numeric"]');
    $page->type('td input[inputmode="numeric"]', $price);

    // Submit the form
    $page->click('Create Invoice');

    // Wait for navigation to detail page
    $page->assertSee('INV-');

    return $page;
}

/**
 * Helper: post an invoice from its detail page and reload.
 */
function postInvoice($page): void
{
    $page->click('Post Invoice');
    $page->assertSee('posted successfully');

    // Reload to get updated status (SPA may not auto-refresh)
    reloadPage($page);
}

/**
 * Get the invoice ID from the current detail page URL.
 */
function getInvoiceIdFromUrl($page): int
{
    $url = $page->url();
    preg_match('/\/invoices\/(\d+)/', $url, $matches);

    return (int) ($matches[1] ?? 0);
}

it('can create an invoice and verify it in the list', function () {
    $page = createInvoice('Invoice Create Test');

    // Should be on the detail page with Draft status
    $page->assertSee('Draft')
        ->assertSee('PT Test Customer')
        ->assertSee('Invoice Create Test');

    // Navigate to list and verify
    $page->navigate(spaUrl('/invoices'));
    $page->assertSee('Invoices')
        ->assertSee('INV-')
        ->assertSee('PT Test Customer');
});

it('can post an invoice and journal entry is created', function () {
    $page = createInvoice('Post Test Item', 10, '100000');

    $invoiceId = getInvoiceIdFromUrl($page);
    expect($invoiceId)->toBeGreaterThan(0);

    // Post the invoice
    postInvoice($page);

    // UI should now show "Terkirim" (Indonesian for Sent) status
    $page->assertSee('Terkirim');
    $page->assertDontSee('Post Invoice');

    // DB assertions: verify journal entry was created
    $invoice = realDb()->table('invoices')->where('id', $invoiceId)->first();
    expect($invoice->status)->toBe('sent');
    expect($invoice->journal_entry_id)->not->toBeNull();

    // Verify journal entry lines: Debit AR, Credit Revenue, Credit Tax
    $journalLines = realDb()->table('journal_entry_lines')
        ->where('journal_entry_id', $invoice->journal_entry_id)
        ->get();

    $totalDebit = $journalLines->sum('debit');
    $totalCredit = $journalLines->sum('credit');

    // Journal must balance
    expect($totalDebit)->toBe($totalCredit);

    // AR account (1-1100) should be debited for total_amount
    $arLine = $journalLines->first(fn ($l) => $l->account_id === 1004); // 1-1100
    expect($arLine)->not->toBeNull();
    expect($arLine->debit)->toBe($invoice->total_amount);

    // Revenue account (4-1001) should be credited
    $revLine = $journalLines->first(fn ($l) => $l->account_id === 1044); // 4-1001
    expect($revLine)->not->toBeNull();
    expect((int) $revLine->credit)->toBeGreaterThan(0);
});

it('can record partial payment and status changes to Partial', function () {
    // Create and post an invoice
    $page = createInvoice('Partial Payment Test', 10, '100000');
    $invoiceId = getInvoiceIdFromUrl($page);
    postInvoice($page);

    // Get the total amount for calculating 60%
    $invoice = realDb()->table('invoices')->where('id', $invoiceId)->first();
    $totalAmount = (int) $invoice->total_amount;
    $partialAmount = (int) round($totalAmount * 0.6);

    // Navigate to payment form with invoice pre-linked
    $page->navigate(spaUrl("/payments/new?invoice_id={$invoiceId}"));
    $page->assertSee('Record Payment');

    // Select customer
    $page->click('Select customer');
    $page->click('[role="option"] >> text=PT Test Customer');

    // Fill amount
    $page->fill('input[type="number"][step="1000"]', (string) $partialAmount);

    // Select cash account
    $page->click('Select account');
    $page->click('[role="option"] >> text=Bank BCA');

    // Submit payment (default method is 'transfer' which is valid)
    $page->click('button[type="submit"]');
    $page->assertSee('Payment recorded successfully');

    // DB assertions
    $invoice = realDb()->table('invoices')->where('id', $invoiceId)->first();
    expect($invoice->status)->toBe('partial');
    expect((int) $invoice->paid_amount)->toBe($partialAmount);
});

it('can record remaining payment and status changes to Paid', function () {
    // Create and post an invoice
    $page = createInvoice('Full Payment Test', 10, '100000');
    $invoiceId = getInvoiceIdFromUrl($page);
    postInvoice($page);

    $invoice = realDb()->table('invoices')->where('id', $invoiceId)->first();
    $totalAmount = (int) $invoice->total_amount;
    $firstPayment = (int) round($totalAmount * 0.6);
    $secondPayment = $totalAmount - $firstPayment;

    // --- First payment (60%) ---
    $page->navigate(spaUrl("/payments/new?invoice_id={$invoiceId}"));
    $page->assertSee('Record Payment');

    $page->click('Select customer');
    $page->click('[role="option"] >> text=PT Test Customer');
    $page->fill('input[type="number"][step="1000"]', (string) $firstPayment);
    $page->click('Select account');
    $page->click('[role="option"] >> text=Bank BCA');
    $page->click('button[type="submit"]');
    $page->assertSee('Payment recorded successfully');

    // Navigate back to the invoice
    $page->navigate(spaUrl('/invoices/'.$invoiceId));
    $page->assertSee('Sebagian'); // Indonesian for Partial

    // --- Second payment (40%) ---
    $page->navigate(spaUrl("/payments/new?invoice_id={$invoiceId}"));
    $page->assertSee('Record Payment');

    $page->click('Select customer');
    $page->click('[role="option"] >> text=PT Test Customer');
    $page->fill('input[type="number"][step="1000"]', (string) $secondPayment);
    $page->click('Select account');
    $page->click('[role="option"] >> text=Bank BCA');
    $page->click('button[type="submit"]');
    $page->assertSee('Payment recorded successfully');

    // DB assertions: fully paid
    $invoice = realDb()->table('invoices')->where('id', $invoiceId)->first();
    expect($invoice->status)->toBe('paid');
    expect((int) $invoice->paid_amount)->toBe($totalAmount);

    // AR balance for this invoice should be zero (debits - credits = 0)
    // Collect all journal entry IDs: the invoice JE + all payment JEs
    $invoice = realDb()->table('invoices')->where('id', $invoiceId)->first();
    $journalIds = collect([$invoice->journal_entry_id]);

    $paymentJournalIds = realDb()->table('payments')
        ->where('payable_type', 'App\\Models\\Sales\\Invoice')
        ->where('payable_id', $invoiceId)
        ->where('is_voided', false)
        ->pluck('journal_entry_id');

    $journalIds = $journalIds->merge($paymentJournalIds)->filter();

    $arBalance = realDb()->table('journal_entry_lines')
        ->whereIn('journal_entry_id', $journalIds)
        ->where('account_id', 1004) // 1-1100 AR
        ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance')
        ->value('balance');

    expect((int) $arBalance)->toBe(0);
});

it('maintains balanced trial balance after all operations', function () {
    // Create, post, and fully pay an invoice
    $page = createInvoice('Trial Balance Test', 10, '100000');
    $invoiceId = getInvoiceIdFromUrl($page);
    postInvoice($page);

    $invoice = realDb()->table('invoices')->where('id', $invoiceId)->first();
    $totalAmount = (int) $invoice->total_amount;

    // Record full payment via direct navigation to payment form
    $page->navigate(spaUrl("/payments/new?invoice_id={$invoiceId}"));
    $page->assertSee('Record Payment');

    $page->click('Select customer');
    $page->click('[role="option"] >> text=PT Test Customer');
    $page->fill('input[type="number"][step="1000"]', (string) $totalAmount);
    $page->click('Select account');
    $page->click('[role="option"] >> text=Bank BCA');
    $page->click('button[type="submit"]');
    $page->assertSee('Payment recorded successfully');

    // Assert trial balance: total debits = total credits across ALL posted journal entries
    $trialBalance = realDb()->table('journal_entry_lines as jel')
        ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
        ->where('je.is_posted', true)
        ->where('je.deleted_at', null)
        ->where('je.is_reversed', false)
        ->selectRaw('COALESCE(SUM(jel.debit), 0) as total_debit, COALESCE(SUM(jel.credit), 0) as total_credit')
        ->first();

    expect((int) $trialBalance->total_debit)->toBe((int) $trialBalance->total_credit);
});
