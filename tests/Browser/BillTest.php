<?php

declare(strict_types=1);

/**
 * PURCH-PEST-03: Bill CRUD + accounting browser tests.
 *
 * Prerequisites:
 * - Seeded user: admin@example.com / password
 * - Seeded suppliers: Rohan-Predovic (or first supplier)
 * - Seeded accounts: 2-1100 (AP, id=1024), 5-1002 (Expense, id=1055),
 *   1-1300 (PPN Masukan, id=1007), 1-1010 (Bank BCA, id=1001)
 *
 * Tests cover: create via SPA form, post with JE verification,
 * partial payment, full payment with AP balance check, list page.
 *
 * Bill workflow: Draft → Received (post) → Partial → Paid
 * Status labels (Indonesian): Draft, Diterima, Sebagian, Lunas.
 *
 * Shared helpers (realDb, loginAndVisit, spaUrl, etc.) are in tests/Pest.php.
 * Supplier helper (getTestSupplierName) is in PurchaseOrderTest.php.
 */

// ---------------------------------------------------------------------------
// Helpers
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
            usleep(200_000); // 200ms
        }
    }
}

if (! function_exists('generateBillNumber')) {
    function generateBillNumber(): string
    {
        $prefix = 'BL-'.now()->format('Ym').'-';
        $lastNumber = realDb()->table('bills')
            ->where('bill_number', 'like', $prefix.'%')
            ->orderByDesc('bill_number')
            ->value('bill_number');

        if ($lastNumber) {
            $seq = (int) substr($lastNumber, strlen($prefix)) + 1;
        } else {
            $seq = 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}

if (! function_exists('createBillViaUi')) {
    /**
     * Create a bill via the SPA form and return the page on the detail view.
     */
    function createBillViaUi(
        string $description = 'E2E Bill Item',
        int $qty = 5,
        string $price = '50000',
    ) {
        $supplierName = getTestSupplierName();
        $page = loginAndVisit('/bills/new');

        $page->assertSee('New Bill');

        // Select vendor — Radix-Vue Select
        $page->click('[data-testid="bill-vendor"]');
        $page->click('[role="option"] >> text='.$supplierName);

        // Fill line item description
        $page->fill('[data-testid="bill-item-0-description"]', $description);

        // Fill quantity
        $page->fill('[data-testid="bill-item-0-quantity"]', (string) $qty);

        // Fill unit price (CurrencyInput)
        $page->click('[data-testid="bill-item-0-price"]');
        $page->type('[data-testid="bill-item-0-price"]', $price);

        // Submit the form
        $page->click('[data-testid="bill-submit"]');

        // Wait for navigation to detail page
        $page->assertSee('BL-');

        return $page;
    }
}

if (! function_exists('postBill')) {
    /**
     * Post a bill from its detail page, wait for status change, and reload.
     * The bill detail page uses window.confirm() before posting.
     * We override confirm to auto-accept, then click the button.
     * Returns the bill ID.
     */
    function postBill($page): int
    {
        $billId = getBillIdFromUrl($page);

        // Override confirm() to auto-accept (Playwright may not handle it)
        $page->script('window.confirm = () => true');

        $page->click('Post Bill');

        // Wait for backend to process
        waitForBillStatus($billId, 'received');

        // Reload the page to see updated status
        $page->navigate(spaUrl('/bills/'.$billId));

        return $billId;
    }
}

if (! function_exists('createBillInDb')) {
    /**
     * Create a bill via DB insertion with one item.
     * Used for fast-forwarding to specific statuses.
     */
    function createBillInDb(
        string $status = 'draft',
        string $description = 'E2E Bill Item',
        int $qty = 5,
        int $unitPrice = 50000,
    ): int {
        $db = realDb();
        $userId = (int) $db->table('users')->where('email', 'admin@example.com')->value('id');
        $contactId = (int) $db->table('contacts')->where('type', 'supplier')->orderBy('id')->value('id');

        $lineTotal = $qty * $unitPrice;
        $taxAmount = (int) round($lineTotal * 0.11);
        $totalAmount = $lineTotal + $taxAmount;

        $billId = (int) $db->table('bills')->insertGetId([
            'bill_number' => generateBillNumber(),
            'vendor_invoice_number' => null,
            'contact_id' => $contactId,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'description' => $description,
            'reference' => null,
            'subtotal' => $lineTotal,
            'tax_amount' => $taxAmount,
            'tax_rate' => 11,
            'discount_amount' => 0,
            'total_amount' => $totalAmount,
            'paid_amount' => 0,
            'status' => $status,
            'journal_entry_id' => null,
            'payable_account_id' => 1024, // AP 2-1100
            'currency' => 'IDR',
            'exchange_rate' => 1,
            'base_currency_total' => $totalAmount,
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $db->table('bill_items')->insert([
            'bill_id' => $billId,
            'product_id' => null,
            'description' => $description,
            'quantity' => $qty,
            'unit' => 'pcs',
            'unit_price' => $unitPrice,
            'discount_percent' => 0,
            'discount_amount' => 0,
            'tax_rate' => 11,
            'tax_amount' => $taxAmount,
            'line_total' => $lineTotal,
            'sort_order' => 0,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $billId;
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('can create a bill via SPA form', function () {
    $page = createBillViaUi('Bill Create Test', 10, '50000');

    $billId = getBillIdFromUrl($page);
    expect($billId)->toBeGreaterThan(0);

    // Detail page should show Draft status and correct data
    $page->assertSee('Draft');
    $page->assertSee(getTestSupplierName());
    $page->assertSee('Bill Create Test');

    // Post Bill button should be visible for draft
    $page->assertSee('Post Bill');

    // DB assertions
    $bill = realDb()->table('bills')->where('id', $billId)->first();
    expect($bill->status)->toBe('draft');

    $items = realDb()->table('bill_items')
        ->where('bill_id', $billId)
        ->get();
    expect($items)->toHaveCount(1);
    expect((int) $items[0]->quantity)->toBe(10);
    expect((int) $items[0]->unit_price)->toBe(50000);
});

it('posting a bill creates correct journal entries', function () {
    $page = createBillViaUi('Post JE Test', 10, '100000');

    $billId = getBillIdFromUrl($page);
    expect($billId)->toBeGreaterThan(0);

    // Post the bill
    postBill($page);

    // UI should show "Diterima" (Indonesian for Received)
    $page->assertSee('Diterima');

    // DB assertions: verify journal entry was created
    $bill = realDb()->table('bills')->where('id', $billId)->first();
    expect($bill->status)->toBe('received');
    expect($bill->journal_entry_id)->not->toBeNull();

    // Verify journal entry lines balance
    $journalLines = realDb()->table('journal_entry_lines')
        ->where('journal_entry_id', $bill->journal_entry_id)
        ->get();

    $totalDebit = $journalLines->sum('debit');
    $totalCredit = $journalLines->sum('credit');

    // Journal must balance
    expect($totalDebit)->toBe($totalCredit);

    // Expense account (5-1002, id=1055) should be debited for line total
    $expenseLine = $journalLines->first(fn ($l) => $l->account_id === 1055);
    expect($expenseLine)->not->toBeNull();
    expect((int) $expenseLine->debit)->toBeGreaterThan(0);

    // PPN Masukan account (1-1300, id=1007) should be debited for tax
    $taxLine = $journalLines->first(fn ($l) => $l->account_id === 1007);
    expect($taxLine)->not->toBeNull();
    expect((int) $taxLine->debit)->toBeGreaterThan(0);

    // AP account (2-1100, id=1024) should be credited for total_amount
    $apLine = $journalLines->first(fn ($l) => $l->account_id === 1024);
    expect($apLine)->not->toBeNull();
    expect($apLine->credit)->toBe($bill->total_amount);
});

it('can record partial payment on a posted bill', function () {
    // Create and post a bill
    $page = createBillViaUi('Partial Payment Test', 5, '100000');
    $billId = getBillIdFromUrl($page);
    postBill($page);

    // Get the total amount for calculating partial payment
    $bill = realDb()->table('bills')->where('id', $billId)->first();
    $totalAmount = (int) $bill->total_amount;
    $partialAmount = (int) round($totalAmount * 0.5);

    // Navigate to payment form with bill pre-linked
    $page->navigate(spaUrl("/payments/new?type=send&bill_id={$billId}"));
    $page->assertSee('Record Payment');

    // Select vendor
    $page->click('[data-testid="payment-vendor"]');
    $page->click('[role="option"] >> text='.getTestSupplierName());

    // Fill amount
    $page->fill('[data-testid="payment-amount"]', (string) $partialAmount);

    // Select cash account
    $page->click('[data-testid="payment-account"]');
    $page->click('[role="option"] >> text=Bank BCA');

    // Submit payment (default method is 'transfer' which is valid)
    $page->click('[data-testid="payment-submit"]');
    $page->assertSee('Payment recorded successfully');

    // Navigate back to bill detail
    $page->navigate(spaUrl('/bills/'.$billId));
    $page->assertSee('Sebagian'); // Indonesian for Partial

    // DB assertions
    $bill = realDb()->table('bills')->where('id', $billId)->first();
    expect($bill->status)->toBe('partial');
    expect((int) $bill->paid_amount)->toBe($partialAmount);
});

it('full payment zeros AP balance and trial balance is balanced', function () {
    // Create and post a bill
    $page = createBillViaUi('Full Payment Test', 10, '100000');
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

    // DB: bill should be fully paid
    $bill = realDb()->table('bills')->where('id', $billId)->first();
    expect($bill->status)->toBe('paid');
    expect((int) $bill->paid_amount)->toBe($totalAmount);

    // AP balance for this bill should be zero (debits - credits = 0)
    // Collect all journal entry IDs: the bill JE + all payment JEs
    $journalIds = collect([$bill->journal_entry_id]);

    $paymentJournalIds = realDb()->table('payments')
        ->where('payable_type', 'App\\Models\\Purchasing\\Bill')
        ->where('payable_id', $billId)
        ->where('is_voided', false)
        ->pluck('journal_entry_id');

    $journalIds = $journalIds->merge($paymentJournalIds)->filter();

    $apBalance = realDb()->table('journal_entry_lines')
        ->whereIn('journal_entry_id', $journalIds)
        ->where('account_id', 1024) // 2-1100 AP
        ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance')
        ->value('balance');

    expect((int) $apBalance)->toBe(0);

    // Trial balance: total debits = total credits across ALL posted journal entries
    $trialBalance = realDb()->table('journal_entry_lines as jel')
        ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
        ->where('je.is_posted', true)
        ->where('je.deleted_at', null)
        ->where('je.is_reversed', false)
        ->selectRaw('COALESCE(SUM(jel.debit), 0) as total_debit, COALESCE(SUM(jel.credit), 0) as total_credit')
        ->first();

    expect((int) $trialBalance->total_debit)->toBe((int) $trialBalance->total_credit);
});

it('shows bills in the list page', function () {
    // Create a bill so the list is not empty
    createBillInDb('draft', 'List Page Test');

    $page = loginAndVisit('/bills');

    $page->assertSee('BL-');
    $page->assertSee(getTestSupplierName());
});
