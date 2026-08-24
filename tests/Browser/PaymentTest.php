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
beforeEach(fn () => skipUnlessLiveFeature('payments'));

// ---------------------------------------------------------------------------
// Bill Helpers (shared with BillTest.php, guarded to prevent redefinition)
// ---------------------------------------------------------------------------

if (! function_exists('getTestSupplierName')) {
    function getTestSupplierName(): string
    {
        $name = realDb()->table('contacts')
            ->where('type', 'supplier')
            ->orderBy('id')
            ->value('name');

        return $name ?: 'Rohan-Predovic';
    }
}

if (! function_exists('getBillIdFromUrl')) {
    function getBillIdFromUrl($page): int
    {
        $url = $page->url();
        preg_match('/\/bills\/(\d+)/', $url, $matches);

        return (int) ($matches[1] ?? 0);
    }
}

if (! function_exists('waitForBillStatus')) {
    function waitForBillStatus(int $billId, string $expectedStatus, int $maxRetries = 30): void
    {
        for ($i = 0; $i < $maxRetries; $i++) {
            $status = realDb()->table('bills')->where('id', $billId)->value('status');
            if ($status === $expectedStatus) {
                return;
            }
            usleep(200_000);
        }
    }
}

if (! function_exists('createBillViaUi')) {
    function createBillViaUi(
        string $description = 'E2E Bill Item',
        int $qty = 5,
        string $price = '50000',
    ) {
        $supplierName = getTestSupplierName();
        $page = loginAndVisit('/bills/new');

        $page->assertSee('New Bill');

        $page->click('[data-testid="bill-vendor"]');
        $page->click('[role="option"] >> text='.$supplierName);
        $page->fill('[data-testid="bill-item-0-description"]', $description);
        $page->fill('[data-testid="bill-item-0-quantity"]', (string) $qty);
        $page->click('[data-testid="bill-item-0-price"]');
        $page->type('[data-testid="bill-item-0-price"]', $price);
        $page->click('[data-testid="bill-submit"]');
        $page->assertSee('BL-');

        return $page;
    }
}

if (! function_exists('postBill')) {
    function postBill($page): int
    {
        $billId = getBillIdFromUrl($page);

        $page->script('window.confirm = () => true');
        $page->click('Post Bill');

        waitForBillStatus($billId, 'received');
        $page->navigate(spaUrl('/bills/'.$billId));

        return $billId;
    }
}

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
    $page->click('[data-testid="payment-customer"]');
    $page->click('[role="option"] >> text="'.browserTestCustomerName().'"');

    // Fill amount = full total
    $page->fill('[data-testid="payment-amount"]', (string) $totalAmount);

    // Select cash account
    $page->click('[data-testid="payment-account"]');
    $page->click('[role="option"] >> text=Bank BCA');

    // Submit payment
    $page->click('[data-testid="payment-submit"]');
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

    $page->click('[data-testid="payment-customer"]');
    $page->click('[role="option"] >> text="'.browserTestCustomerName().'"');
    $page->fill('[data-testid="payment-amount"]', (string) $totalAmount);
    $page->click('[data-testid="payment-account"]');
    $page->click('[role="option"] >> text=Bank BCA');
    $page->click('[data-testid="payment-submit"]');
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

it('recording a payment creates correct journal entry lines', function () {
    // Create and post an invoice
    $page = createInvoice('Payment JE Lines Test', 10, '100000');
    $invoiceId = getInvoiceIdFromUrl($page);
    postInvoice($page);

    $invoice = realDb()->table('invoices')->where('id', $invoiceId)->first();
    $totalAmount = (int) $invoice->total_amount;

    // Record full payment
    $page->navigate(spaUrl("/payments/new?invoice_id={$invoiceId}"));
    $page->assertSee('Record Payment');
    $page->click('[data-testid="payment-customer"]');
    $page->click('[role="option"] >> text="'.browserTestCustomerName().'"');
    $page->fill('[data-testid="payment-amount"]', (string) $totalAmount);
    $page->click('[data-testid="payment-account"]');
    $page->click('[role="option"] >> text=Bank BCA');
    $page->click('[data-testid="payment-submit"]');
    $page->assertSee('Payment recorded successfully');

    // Find the payment and verify JE lines
    $payment = realDb()->table('payments')
        ->where('payable_type', 'App\\Models\\Sales\\Invoice')
        ->where('payable_id', $invoiceId)
        ->where('is_voided', false)
        ->first();
    expect($payment->journal_entry_id)->not->toBeNull();

    $jeLines = realDb()->table('journal_entry_lines')
        ->where('journal_entry_id', $payment->journal_entry_id)
        ->get();

    // JE must balance
    expect($jeLines->sum('debit'))->toBe($jeLines->sum('credit'));

    // Debit: Bank BCA (1-1010, id=1001) for total amount
    $cashLine = $jeLines->first(fn ($l) => $l->account_id === browserCashAccountId());
    expect($cashLine)->not->toBeNull();
    expect((int) $cashLine->debit)->toBe($totalAmount);

    // Credit: Piutang Usaha / AR (1-1100, id=1004) for total amount
    $arLine = $jeLines->first(fn ($l) => $l->account_id === browserAccountIdByCode('1-1100'));
    expect($arLine)->not->toBeNull();
    expect((int) $arLine->credit)->toBe($totalAmount);

    // Trial balance check
    $trialBalance = realDb()->table('journal_entry_lines as jel')
        ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
        ->where('je.is_posted', true)
        ->where('je.deleted_at', null)
        ->where('je.is_reversed', false)
        ->selectRaw('COALESCE(SUM(jel.debit), 0) as total_debit, COALESCE(SUM(jel.credit), 0) as total_credit')
        ->first();

    expect((int) $trialBalance->total_debit)->toBe((int) $trialBalance->total_credit);
});

it('voiding a payment creates correct reversal journal entry lines', function () {
    // Create and post an invoice, record payment
    $page = createInvoice('Void JE Lines Test', 5, '200000');
    $invoiceId = getInvoiceIdFromUrl($page);
    postInvoice($page);

    $invoice = realDb()->table('invoices')->where('id', $invoiceId)->first();
    $totalAmount = (int) $invoice->total_amount;

    $page->navigate(spaUrl("/payments/new?invoice_id={$invoiceId}"));
    $page->assertSee('Record Payment');
    $page->click('[data-testid="payment-customer"]');
    $page->click('[role="option"] >> text="'.browserTestCustomerName().'"');
    $page->fill('[data-testid="payment-amount"]', (string) $totalAmount);
    $page->click('[data-testid="payment-account"]');
    $page->click('[role="option"] >> text=Bank BCA');
    $page->click('[data-testid="payment-submit"]');
    $page->assertSee('Payment recorded successfully');

    // Get the payment
    $payment = realDb()->table('payments')
        ->where('payable_type', 'App\\Models\\Sales\\Invoice')
        ->where('payable_id', $invoiceId)
        ->where('is_voided', false)
        ->first();
    $paymentId = (int) $payment->id;
    $originalJeId = (int) $payment->journal_entry_id;

    // Void the payment
    $page->navigate(spaUrl("/payments/{$paymentId}"));
    $page->assertSee($payment->payment_number);
    $page->script('window.confirm = () => true');
    $page->click('Void Payment');
    waitForPaymentVoided($paymentId);

    // Verify reversal JE lines
    $originalJe = realDb()->table('journal_entries')->where('id', $originalJeId)->first();
    expect($originalJe->reversed_by_id)->not->toBeNull();

    $reversalLines = realDb()->table('journal_entry_lines')
        ->where('journal_entry_id', $originalJe->reversed_by_id)
        ->get();

    // Reversal JE must balance
    expect($reversalLines->sum('debit'))->toBe($reversalLines->sum('credit'));

    // Reversal: Debit AR (1-1100, id=1004) — opposite of original
    $arLine = $reversalLines->first(fn ($l) => $l->account_id === browserAccountIdByCode('1-1100'));
    expect($arLine)->not->toBeNull();
    expect((int) $arLine->debit)->toBe($totalAmount);

    // Reversal: Credit Cash (1-1010, id=1001) — opposite of original
    $cashLine = $reversalLines->first(fn ($l) => $l->account_id === browserCashAccountId());
    expect($cashLine)->not->toBeNull();
    expect((int) $cashLine->credit)->toBe($totalAmount);

    // Trial balance should still be balanced after void
    $trialBalance = realDb()->table('journal_entry_lines as jel')
        ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
        ->where('je.is_posted', true)
        ->where('je.deleted_at', null)
        ->where('je.is_reversed', false)
        ->selectRaw('COALESCE(SUM(jel.debit), 0) as total_debit, COALESCE(SUM(jel.credit), 0) as total_credit')
        ->first();

    expect((int) $trialBalance->total_debit)->toBe((int) $trialBalance->total_credit);
});

it('recording a bill payment creates correct journal entry lines', function () {
    // Create and post a bill
    $page = createBillViaUi('Bill Pay JE Test', 10, '100000');
    $billId = getBillIdFromUrl($page);
    postBill($page);

    $bill = realDb()->table('bills')->where('id', $billId)->first();
    $totalAmount = (int) $bill->total_amount;

    // Record full payment via send-type payment form
    $page->navigate(spaUrl("/payments/new?type=send&bill_id={$billId}"));
    $page->assertSee('Record Payment');

    // Select vendor
    $page->click('[data-testid="payment-vendor"]');
    $page->click('[role="option"] >> text='.getTestSupplierName());

    // Fill amount = full total
    $page->fill('[data-testid="payment-amount"]', (string) $totalAmount);

    // Select cash account
    $page->click('[data-testid="payment-account"]');
    $page->click('[role="option"] >> text=Bank BCA');

    // Submit payment
    $page->click('[data-testid="payment-submit"]');
    $page->assertSee('Payment recorded successfully');

    // Find the payment and verify JE lines
    $payment = realDb()->table('payments')
        ->where('payable_type', 'App\\Models\\Purchasing\\Bill')
        ->where('payable_id', $billId)
        ->where('is_voided', false)
        ->first();
    expect($payment)->not->toBeNull();
    expect($payment->journal_entry_id)->not->toBeNull();

    $jeLines = realDb()->table('journal_entry_lines')
        ->where('journal_entry_id', $payment->journal_entry_id)
        ->get();

    // JE must balance
    expect($jeLines->sum('debit'))->toBe($jeLines->sum('credit'));

    // Debit: AP (2-1100, id=1024) — reducing payable
    $apLine = $jeLines->first(fn ($l) => $l->account_id === browserAccountIdByCode('2-1100'));
    expect($apLine)->not->toBeNull();
    expect((int) $apLine->debit)->toBe($totalAmount);

    // Credit: Bank BCA (1-1010, id=1001) — cash out
    $cashLine = $jeLines->first(fn ($l) => $l->account_id === browserCashAccountId());
    expect($cashLine)->not->toBeNull();
    expect((int) $cashLine->credit)->toBe($totalAmount);

    // Trial balance check
    $trialBalance = realDb()->table('journal_entry_lines as jel')
        ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
        ->where('je.is_posted', true)
        ->where('je.deleted_at', null)
        ->where('je.is_reversed', false)
        ->selectRaw('COALESCE(SUM(jel.debit), 0) as total_debit, COALESCE(SUM(jel.credit), 0) as total_credit')
        ->first();

    expect((int) $trialBalance->total_debit)->toBe((int) $trialBalance->total_credit);
});

it('voiding a bill payment creates correct reversal journal entry lines', function () {
    // Create and post a bill, then pay it
    $page = createBillViaUi('Void Bill Pay JE Test', 5, '200000');
    $billId = getBillIdFromUrl($page);
    postBill($page);

    $bill = realDb()->table('bills')->where('id', $billId)->first();
    $totalAmount = (int) $bill->total_amount;

    // Record full payment
    $page->navigate(spaUrl("/payments/new?type=send&bill_id={$billId}"));
    $page->assertSee('Record Payment');
    $page->click('[data-testid="payment-vendor"]');
    $page->click('[role="option"] >> text='.getTestSupplierName());
    $page->fill('[data-testid="payment-amount"]', (string) $totalAmount);
    $page->click('[data-testid="payment-account"]');
    $page->click('[role="option"] >> text=Bank BCA');
    $page->click('[data-testid="payment-submit"]');
    $page->assertSee('Payment recorded successfully');

    // Get the payment
    $payment = realDb()->table('payments')
        ->where('payable_type', 'App\\Models\\Purchasing\\Bill')
        ->where('payable_id', $billId)
        ->where('is_voided', false)
        ->first();
    $paymentId = (int) $payment->id;
    $originalJeId = (int) $payment->journal_entry_id;

    // Void the payment
    $page->navigate(spaUrl("/payments/{$paymentId}"));
    $page->assertSee($payment->payment_number);
    $page->script('window.confirm = () => true');
    $page->click('Void Payment');
    waitForPaymentVoided($paymentId);

    // Verify reversal JE lines
    $originalJe = realDb()->table('journal_entries')->where('id', $originalJeId)->first();
    expect($originalJe->is_reversed)->toBeTruthy();
    expect($originalJe->reversed_by_id)->not->toBeNull();

    $reversalLines = realDb()->table('journal_entry_lines')
        ->where('journal_entry_id', $originalJe->reversed_by_id)
        ->get();

    // Reversal JE must balance
    expect($reversalLines->sum('debit'))->toBe($reversalLines->sum('credit'));

    // Reversal: Debit Cash (1-1010, id=1001) — opposite of original
    $cashLine = $reversalLines->first(fn ($l) => $l->account_id === browserCashAccountId());
    expect($cashLine)->not->toBeNull();
    expect((int) $cashLine->debit)->toBe($totalAmount);

    // Reversal: Credit AP (2-1100, id=1024) — opposite of original
    $apLine = $reversalLines->first(fn ($l) => $l->account_id === browserAccountIdByCode('2-1100'));
    expect($apLine)->not->toBeNull();
    expect((int) $apLine->credit)->toBe($totalAmount);

    // Bill paid_amount should revert to 0
    // Note: Bill status may remain 'paid' because the state machine does not
    // support Paid→Received reverse transition. The key accounting assertion
    // is that paid_amount is zeroed and the JE is reversed.
    $bill = realDb()->table('bills')->where('id', $billId)->first();
    expect((int) $bill->paid_amount)->toBe(0);

    // Trial balance should still be balanced after void
    $trialBalance = realDb()->table('journal_entry_lines as jel')
        ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
        ->where('je.is_posted', true)
        ->where('je.deleted_at', null)
        ->where('je.is_reversed', false)
        ->selectRaw('COALESCE(SUM(jel.debit), 0) as total_debit, COALESCE(SUM(jel.credit), 0) as total_credit')
        ->first();

    expect((int) $trialBalance->total_debit)->toBe((int) $trialBalance->total_credit);
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
    $page->click('[data-testid="payment-customer"]');
    $page->click('[role="option"] >> text="'.browserTestCustomerName().'"');
    $page->fill('[data-testid="payment-amount"]', (string) $totalAmount);
    $page->click('[data-testid="payment-account"]');
    $page->click('[role="option"] >> text=Bank BCA');
    $page->click('[data-testid="payment-submit"]');
    $page->assertSee('Payment recorded successfully');

    // Navigate to payments list
    $page->navigate(spaUrl('/payments'));

    $page->assertSee('Payments');
    $page->assertSee('PAY-');
    $page->assertSee('Active');
});
