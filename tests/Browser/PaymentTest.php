<?php

declare(strict_types=1);

/**
 * FIN-PEST-01: Payment workflow browser tests.
 *
 * Prerequisites:
 * - Seeded user: admin@example.com / password
 * - Seeded customer: PT Test Customer
 * - Seeded accounts: 1-1010 (Bank BCA, id=1001), 1-1100 (AR, id=1004)
 *
 * Tests cover: record payment for invoice, void payment with JE reversal,
 * list page display.
 *
 * Payment workflow: Active → (optionally) Voided
 * The payment form is at /payments/new and supports query params:
 *   ?type=receive&invoice_id=X or ?type=send&bill_id=X
 *
 * Shared helpers (realDb, loginAndVisit, spaUrl, createInvoice, postInvoice,
 * getInvoiceIdFromUrl) are in tests/Pest.php.
 */

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function getPaymentIdFromUrl($page): int
{
    $url = $page->url();
    preg_match('/\/payments\/(\d+)/', $url, $matches);

    return (int) ($matches[1] ?? 0);
}

function waitForPaymentVoided(int $paymentId, int $maxRetries = 30): void
{
    for ($i = 0; $i < $maxRetries; $i++) {
        $isVoided = realDb()->table('payments')->where('id', $paymentId)->value('is_voided');
        if ($isVoided) {
            return;
        }
        usleep(200_000); // 200ms
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('can record a payment for a posted invoice', function () {
    // Create and post an invoice
    $page = createInvoice('Payment Record Test', 10, '100000');
    $invoiceId = getInvoiceIdFromUrl($page);
    postInvoice($page);

    $invoice = realDb()->table('invoices')->where('id', $invoiceId)->first();
    $totalAmount = (int) $invoice->total_amount;

    // Navigate to payment form with invoice pre-linked
    $page->navigate(spaUrl("/payments/new?invoice_id={$invoiceId}"));
    $page->assertSee('Record Payment');

    // Select customer
    $page->click('Select customer');
    $page->click('[role="option"] >> text=PT Test Customer');

    // Fill amount = full total
    $page->fill('input[type="number"][step="1000"]', (string) $totalAmount);

    // Select cash account
    $page->click('Select account');
    $page->click('[role="option"] >> text=Bank BCA');

    // Submit payment
    $page->click('button[type="submit"]');
    $page->assertSee('Payment recorded successfully');

    // DB assertions
    $invoice = realDb()->table('invoices')->where('id', $invoiceId)->first();
    expect($invoice->status)->toBe('paid');
    expect((int) $invoice->paid_amount)->toBe($totalAmount);

    // Payment should exist with JE
    $payment = realDb()->table('payments')
        ->where('payable_type', 'App\\Models\\Sales\\Invoice')
        ->where('payable_id', $invoiceId)
        ->where('is_voided', false)
        ->first();
    expect($payment)->not->toBeNull();
    expect((int) $payment->amount)->toBe($totalAmount);
    expect($payment->journal_entry_id)->not->toBeNull();
});

it('voiding a payment reverses JE and restores invoice status', function () {
    // Create and post an invoice
    $page = createInvoice('Void Payment Test', 10, '100000');
    $invoiceId = getInvoiceIdFromUrl($page);
    postInvoice($page);

    $invoice = realDb()->table('invoices')->where('id', $invoiceId)->first();
    $totalAmount = (int) $invoice->total_amount;

    // Record full payment
    $page->navigate(spaUrl("/payments/new?invoice_id={$invoiceId}"));
    $page->assertSee('Record Payment');

    $page->click('Select customer');
    $page->click('[role="option"] >> text=PT Test Customer');
    $page->fill('input[type="number"][step="1000"]', (string) $totalAmount);
    $page->click('Select account');
    $page->click('[role="option"] >> text=Bank BCA');
    $page->click('button[type="submit"]');
    $page->assertSee('Payment recorded successfully');

    // Find the payment
    $payment = realDb()->table('payments')
        ->where('payable_type', 'App\\Models\\Sales\\Invoice')
        ->where('payable_id', $invoiceId)
        ->where('is_voided', false)
        ->first();
    $paymentId = (int) $payment->id;
    $paymentJeId = (int) $payment->journal_entry_id;

    // Navigate to payment detail page
    $page->navigate(spaUrl("/payments/{$paymentId}"));
    $page->assertSee($payment->payment_number);
    $page->assertSee('Void Payment');

    // Override confirm() to auto-accept (Playwright may not handle native dialogs)
    $page->script('window.confirm = () => true');

    // Click "Void Payment"
    $page->click('Void Payment');

    // Wait for DB status change
    waitForPaymentVoided($paymentId);

    // Reload the page
    $page->navigate(spaUrl("/payments/{$paymentId}"));

    // Should show "Voided" badge
    $page->assertSee('Voided');

    // DB assertions: payment is voided
    $payment = realDb()->table('payments')->where('id', $paymentId)->first();
    expect($payment->is_voided)->toBeTruthy();

    // Payment JE should be reversed
    $paymentJe = realDb()->table('journal_entries')->where('id', $paymentJeId)->first();
    expect($paymentJe->is_reversed)->toBeTruthy();
    expect($paymentJe->reversed_by_id)->not->toBeNull();

    // Reversing entry should exist and be posted
    $reversingJe = realDb()->table('journal_entries')
        ->where('id', $paymentJe->reversed_by_id)
        ->first();
    expect($reversingJe)->not->toBeNull();
    expect($reversingJe->is_posted)->toBeTruthy();

    // Invoice status should revert to 'sent' (not 'paid')
    $invoice = realDb()->table('invoices')->where('id', $invoiceId)->first();
    expect($invoice->status)->toBe('sent');
    expect((int) $invoice->paid_amount)->toBe(0);
});

it('shows payments in the list page', function () {
    // Create a payment first (via invoice workflow)
    $page = createInvoice('List Payment Test', 5, '50000');
    $invoiceId = getInvoiceIdFromUrl($page);
    postInvoice($page);

    $invoice = realDb()->table('invoices')->where('id', $invoiceId)->first();
    $totalAmount = (int) $invoice->total_amount;

    $page->navigate(spaUrl("/payments/new?invoice_id={$invoiceId}"));
    $page->assertSee('Record Payment');
    $page->click('Select customer');
    $page->click('[role="option"] >> text=PT Test Customer');
    $page->fill('input[type="number"][step="1000"]', (string) $totalAmount);
    $page->click('Select account');
    $page->click('[role="option"] >> text=Bank BCA');
    $page->click('button[type="submit"]');
    $page->assertSee('Payment recorded successfully');

    // Navigate to payments list
    $page->navigate(spaUrl('/payments'));

    $page->assertSee('Payments');
    $page->assertSee('PAY-');
    $page->assertSee('Active');
});
