<?php

declare(strict_types=1);

beforeEach(fn () => skipUnlessLiveFeature('down_payments'));

/**
 * FIN-PEST-02: Down payment browser tests (LIMITED scope).
 *
 * Prerequisites:
 * - Seeded user: admin@example.com / password
 * - Seeded customer: PT Test Customer
 * - Seeded accounts: 1-1010 (Bank BCA, id=1001)
 *
 * Seeded accounts: 2-1700 (Uang Muka Penjualan, id=1082),
 *   1-1700 (Uang Muka Pembelian, id=1083)
 *
 * No dedicated form page — DPs are created via service or DB insertion.
 * Tests cover: detail display, cancel workflow, list page, and JE accounting.
 *
 * DP statuses: active, fully_applied, refunded, cancelled
 *
 * Shared helpers (realDb, loginAndVisit, spaUrl, etc.) are in tests/Pest.php.
 */

// ---------------------------------------------------------------------------
// Bill Helpers (shared with BillTest.php, guarded to prevent redefinition)
// ---------------------------------------------------------------------------

if (! function_exists('getTestSupplierName')) {
    function getTestSupplierName(): string
    {
        return ensureBrowserTestSupplier()->name;
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
        ensureOpenCurrentFiscalPeriod();
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

/**
 * Run a callback with the default DB connection pointing to the real PostgreSQL.
 *
 * Browser tests default to SQLite (:memory:), but the running SPA server
 * uses PostgreSQL.  When we need to call Laravel services (which use Eloquent
 * on the default connection), we temporarily swap the default to
 * `browser_pgsql` so that all queries hit the real database.
 */
if (! function_exists('withRealDb')) {
    function withRealDb(callable $callback): mixed
    {
        // Ensure the browser_pgsql connection config exists
        realDb();

        $original = config('database.default');
        config(['database.default' => 'browser_pgsql']);

        try {
            return $callback();
        } finally {
            config(['database.default' => $original]);
        }
    }
}

function getDpIdFromUrl($page): int
{
    $url = $page->url();
    preg_match('/down-payments\/(\d+)/', $url, $matches);

    return (int) ($matches[1] ?? 0);
}

function waitForDpStatus(int $dpId, string $expectedStatus, int $maxRetries = 30): void
{
    for ($i = 0; $i < $maxRetries; $i++) {
        $status = realDb()->table('down_payments')->where('id', $dpId)->value('status');
        if ($status === $expectedStatus) {
            return;
        }
        usleep(200_000); // 200ms
    }
}

function generateDpNumber(string $type = 'receivable'): string
{
    $prefix = ($type === 'receivable' ? 'DPR-' : 'DPP-').now()->format('Ym').'-';
    $lastNumber = realDb()->table('down_payments')
        ->where('dp_number', 'like', $prefix.'%')
        ->orderByDesc('dp_number')
        ->value('dp_number');

    if ($lastNumber) {
        $seq = (int) substr($lastNumber, strlen($prefix)) + 1;
    } else {
        $seq = 1;
    }

    return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
}

/**
 * Create a down payment via DB insertion.
 */
function createDownPaymentInDb(
    string $type = 'receivable',
    int $amount = 5000000,
    string $status = 'active',
): int {
    $db = realDb();
    $userId = (int) $db->table('users')->where('email', 'admin@example.com')->value('id');

    // Use customer for receivable, supplier for payable
    if ($type === 'receivable') {
        $contactId = (int) ensureBrowserTestCustomer()->id;
    } else {
        $contactId = (int) ensureBrowserTestSupplier()->id;
    }

    $dpId = (int) $db->table('down_payments')->insertGetId([
        'dp_number' => generateDpNumber($type),
        'type' => $type,
        'contact_id' => $contactId,
        'dp_date' => now()->toDateString(),
        'amount' => $amount,
        'applied_amount' => 0,
        // remaining_amount is a generated column (amount - applied_amount)
        'payment_method' => 'transfer',
        'cash_account_id' => browserCashAccountId(),
        'reference' => 'E2E-DP-REF',
        'description' => 'E2E Down Payment Test',
        'notes' => 'Created by E2E test',
        'status' => $status,
        'journal_entry_id' => null,
        'refund_payment_id' => null,
        'refunded_at' => null,
        'created_by' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $dpId;
}

/**
 * Create a down payment via the service (which creates JE).
 *
 * Uses withRealDb() to ensure the service's Eloquent queries hit
 * the real PostgreSQL database instead of the test SQLite.
 */
function createDownPaymentViaService(
    string $type = 'receivable',
    int $amount = 5000000,
): int {
    $db = realDb();
    $userId = (int) $db->table('users')->where('email', 'admin@example.com')->value('id');

    if ($type === 'receivable') {
        $contactId = (int) ensureBrowserTestCustomer()->id;
    } else {
        $contactId = (int) ensureBrowserTestSupplier()->id;
    }

    $cashAccountId = browserCashAccountId();

    return withRealDb(function () use ($type, $amount, $contactId, $userId, $cashAccountId) {
        $service = app(\App\Contracts\Sales\DownPaymentServiceInterface::class);
        $dp = $service->create([
            'type' => $type,
            'contact_id' => $contactId,
            'dp_date' => now()->toDateString(),
            'amount' => $amount,
            'payment_method' => 'transfer',
            'cash_account_id' => $cashAccountId,
            'reference' => 'E2E-DP-REF',
            'description' => 'E2E Down Payment Test',
            'notes' => 'Created by E2E test via service',
            'created_by' => $userId,
        ]);

        return (int) $dp->id;
    });
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('can view a down payment detail page', function () {
    $dpId = createDownPaymentInDb('receivable', 5000000, 'active');

    $page = loginAndVisit("/finance/down-payments/{$dpId}");

    // Assert detail page renders with correct data
    $page->assertSee('DPR-');
    $page->assertSee('Aktif'); // Indonesian for Active
    $page->assertSee(browserTestCustomerName());

    // Refund button should be visible (remaining > 0)
    $page->assertSee('Refund');

    // DB assertions
    $dp = realDb()->table('down_payments')->where('id', $dpId)->first();
    expect($dp->status)->toBe('active');
    expect($dp->type)->toBe('receivable');
    expect((int) $dp->amount)->toBe(5000000);
    expect((int) $dp->remaining_amount)->toBe(5000000);
});

it('can cancel a down payment without applications', function () {
    $dpId = createDownPaymentInDb('receivable', 3000000, 'active');

    $page = loginAndVisit("/finance/down-payments/{$dpId}");

    $page->assertSee('DPR-');
    $page->assertSee('Aktif'); // Indonesian for Active

    // Open the "more actions" dropdown (DropdownMenuTrigger)
    $page->click('[aria-haspopup="menu"]');

    // Click Cancel in the dropdown menu
    $page->click('[role="menuitem"] >> text=Cancel');

    // Cancel modal should appear
    $page->assertSee('Cancel Down Payment');
    $page->assertSee('Are you sure you want to cancel this down payment?');

    // Fill optional reason
    $page->fill('input[placeholder="Enter cancellation reason"]', 'Testing cancel flow');

    // Click the warning "Cancel" button in the modal footer (not "Keep")
    $page->click('[role="dialog"] button >> text=Cancel');

    // Wait for DB status change
    waitForDpStatus($dpId, 'cancelled');

    // Reload page to see updated status
    $page->navigate(spaUrl("/finance/down-payments/{$dpId}"));

    // Status should show "Dibatalkan" (Indonesian for Cancelled)
    $page->assertSee('Dibatalkan');

    // DB assertions
    $dp = realDb()->table('down_payments')->where('id', $dpId)->first();
    expect($dp->status)->toBe('cancelled');
});

it('creating a down payment via service creates correct journal entry', function () {
    $dpId = createDownPaymentViaService('receivable', 5000000);

    $dp = realDb()->table('down_payments')->where('id', $dpId)->first();
    expect($dp->journal_entry_id)->not->toBeNull();

    // Verify JE lines
    $jeLines = realDb()->table('journal_entry_lines')
        ->where('journal_entry_id', $dp->journal_entry_id)
        ->get();

    // JE must balance
    expect($jeLines->sum('debit'))->toBe($jeLines->sum('credit'));

    // Debit: Bank BCA (1-1010, id=1001)
    $cashLine = $jeLines->first(fn ($l) => $l->account_id === browserCashAccountId());
    expect($cashLine)->not->toBeNull();
    expect((int) $cashLine->debit)->toBe(5000000);

    // Credit: Uang Muka Penjualan (2-1700)
    $dpAccountId = (int) realDb()->table('accounts')->where('code', '2-1700')->value('id');
    $dpLine = $jeLines->first(fn ($l) => $l->account_id === $dpAccountId);
    expect($dpLine)->not->toBeNull();
    expect((int) $dpLine->credit)->toBe(5000000);

    // Verify detail page shows DP with JE
    $page = loginAndVisit("/finance/down-payments/{$dpId}");
    $page->assertSee('DPR-');
    $page->assertSee('Aktif');
});

it('applying a down payment to invoice creates correct journal entry', function () {
    // Create DP via service
    $dpId = createDownPaymentViaService('receivable', 3000000);

    // Create and post an invoice for the same customer
    $page = createInvoice('DP Application Test', 5, '200000');
    $invoiceId = getInvoiceIdFromUrl($page);
    postInvoice($page);

    $invoice = realDb()->table('invoices')->where('id', $invoiceId)->first();
    $invoiceTotalAmount = (int) $invoice->total_amount;

    // Apply DP to invoice — apply min(dp_amount, invoice_outstanding)
    $applyAmount = min(3000000, $invoiceTotalAmount);

    // Wrap in withRealDb so Eloquent models and service use PostgreSQL
    $applicationJeId = withRealDb(function () use ($dpId, $invoiceId, $applyAmount) {
        $dp = \App\Models\Sales\DownPayment::find($dpId);
        $inv = \App\Models\Sales\Invoice::find($invoiceId);

        $service = app(\App\Contracts\Sales\DownPaymentServiceInterface::class);
        $application = $service->applyToInvoice($dp, $inv, [
            'amount' => $applyAmount,
            'applied_date' => now()->toDateString(),
        ]);

        return (int) $application->journal_entry_id;
    });

    expect($applicationJeId)->toBeGreaterThan(0);

    // Verify application JE lines
    $jeLines = realDb()->table('journal_entry_lines')
        ->where('journal_entry_id', $applicationJeId)
        ->get();

    // JE must balance
    expect($jeLines->sum('debit'))->toBe($jeLines->sum('credit'));

    // Debit: Uang Muka Penjualan (2-1700) — reducing the liability
    $dpAccountId = (int) realDb()->table('accounts')->where('code', '2-1700')->value('id');
    $dpLine = $jeLines->first(fn ($l) => $l->account_id === $dpAccountId);
    expect($dpLine)->not->toBeNull();
    expect((int) $dpLine->debit)->toBe($applyAmount);

    // Credit: Piutang Usaha / AR (1-1100, id=1004) — reducing receivable
    $arLine = $jeLines->first(fn ($l) => $l->account_id === browserAccountIdByCode('1-1100'));
    expect($arLine)->not->toBeNull();
    expect((int) $arLine->credit)->toBe($applyAmount);

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

it('cancelling a down payment reverses the journal entry', function () {
    $dpId = createDownPaymentViaService('receivable', 2000000);

    $dp = realDb()->table('down_payments')->where('id', $dpId)->first();
    $originalJeId = (int) $dp->journal_entry_id;
    expect($originalJeId)->toBeGreaterThan(0);

    // Cancel via UI
    $page = loginAndVisit("/finance/down-payments/{$dpId}");
    $page->assertSee('Aktif');

    $page->click('[aria-haspopup="menu"]');
    $page->click('[role="menuitem"] >> text=Cancel');
    $page->assertSee('Cancel Down Payment');
    $page->fill('input[placeholder="Enter cancellation reason"]', 'Testing JE reversal');
    $page->click('[role="dialog"] button >> text=Cancel');

    waitForDpStatus($dpId, 'cancelled');

    // Verify JE was reversed
    $originalJe = realDb()->table('journal_entries')->where('id', $originalJeId)->first();
    expect($originalJe->is_reversed)->toBeTruthy();
    expect($originalJe->reversed_by_id)->not->toBeNull();

    // Reversing entry should exist and be posted
    $reversingJe = realDb()->table('journal_entries')
        ->where('id', $originalJe->reversed_by_id)
        ->first();
    expect($reversingJe)->not->toBeNull();
    expect($reversingJe->is_posted)->toBeTruthy();

    // Reversal lines should swap: Debit DP Liability, Credit Cash
    $reversalLines = realDb()->table('journal_entry_lines')
        ->where('journal_entry_id', $originalJe->reversed_by_id)
        ->get();
    expect($reversalLines->sum('debit'))->toBe($reversalLines->sum('credit'));

    $dpAccountId = (int) realDb()->table('accounts')->where('code', '2-1700')->value('id');
    $dpLine = $reversalLines->first(fn ($l) => $l->account_id === $dpAccountId);
    expect($dpLine)->not->toBeNull();
    expect((int) $dpLine->debit)->toBe(2000000);

    $cashLine = $reversalLines->first(fn ($l) => $l->account_id === browserCashAccountId());
    expect($cashLine)->not->toBeNull();
    expect((int) $cashLine->credit)->toBe(2000000);
});

it('applying a payable down payment to bill creates correct journal entry', function () {
    // Create payable DP via service (amount=3000000)
    $dpId = createDownPaymentViaService('payable', 3000000);

    // Create and post a bill
    $page = createBillViaUi('DP Apply Bill Test', 5, '200000');
    $billId = getBillIdFromUrl($page);
    postBill($page);

    $bill = realDb()->table('bills')->where('id', $billId)->first();
    $billTotalAmount = (int) $bill->total_amount;

    // Apply DP to bill — apply min(dp_amount, bill_outstanding)
    $applyAmount = min(3000000, $billTotalAmount);

    // Wrap in withRealDb so Eloquent models and service use PostgreSQL
    $applicationJeId = withRealDb(function () use ($dpId, $billId, $applyAmount) {
        $dp = \App\Models\Sales\DownPayment::find($dpId);
        $bill = \App\Models\Purchasing\Bill::find($billId);

        $service = app(\App\Contracts\Sales\DownPaymentServiceInterface::class);
        $application = $service->applyToBill($dp, $bill, [
            'amount' => $applyAmount,
            'applied_date' => now()->toDateString(),
        ]);

        return (int) $application->journal_entry_id;
    });

    expect($applicationJeId)->toBeGreaterThan(0);

    // Verify application JE lines
    $jeLines = realDb()->table('journal_entry_lines')
        ->where('journal_entry_id', $applicationJeId)
        ->get();

    // JE must balance
    expect($jeLines->sum('debit'))->toBe($jeLines->sum('credit'));

    // Debit: AP (2-1100, id=1024) — reducing payable
    $apLine = $jeLines->first(fn ($l) => $l->account_id === browserAccountIdByCode('2-1100'));
    expect($apLine)->not->toBeNull();
    expect((int) $apLine->debit)->toBe($applyAmount);

    // Credit: Uang Muka Pembelian (1-1700, id=1083) — reducing DP asset
    $dpLine = $jeLines->first(fn ($l) => $l->account_id === browserAccountIdByCode('1-1700'));
    expect($dpLine)->not->toBeNull();
    expect((int) $dpLine->credit)->toBe($applyAmount);

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

it('refunding a receivable down payment creates correct journal entry', function () {
    // Create receivable DP via service (amount=2000000)
    $dpId = createDownPaymentViaService('receivable', 2000000);

    $dp = realDb()->table('down_payments')->where('id', $dpId)->first();
    expect($dp->status)->toBe('active');

    // Navigate to DP detail page
    $page = loginAndVisit("/finance/down-payments/{$dpId}");

    // Assert "Aktif" status and "Refund" button visible
    $page->assertSee('Aktif');
    $page->assertSee('Refund');

    // Click "Refund" → modal opens
    $page->click('button >> text=Refund');
    $page->assertSee('Refund Down Payment');

    // Fill refund amount (leave as remaining_amount — fill it to be sure)
    $page->fill('input[type="number"]', '2000000');

    // Fill refund date (should be pre-filled, but ensure it's set)
    $dateInput = 'input[type="date"]';
    $page->fill($dateInput, now()->toDateString());

    // Click the confirm "Refund" button in the modal footer
    $page->click('[role="dialog"] button >> text=Refund');

    // Wait for DB: status = 'refunded'
    waitForDpStatus($dpId, 'refunded');

    // Verify refund payment exists with JE
    $dp = realDb()->table('down_payments')->where('id', $dpId)->first();
    expect($dp->status)->toBe('refunded');
    expect($dp->refund_payment_id)->not->toBeNull();

    // Get the refund payment's JE
    $refundPayment = realDb()->table('payments')
        ->where('id', $dp->refund_payment_id)
        ->first();
    expect($refundPayment)->not->toBeNull();
    expect($refundPayment->journal_entry_id)->not->toBeNull();

    $jeLines = realDb()->table('journal_entry_lines')
        ->where('journal_entry_id', $refundPayment->journal_entry_id)
        ->get();

    // JE must balance
    expect($jeLines->sum('debit'))->toBe($jeLines->sum('credit'));

    // Debit: DP Liability (2-1700, id=1082) — reducing the liability
    $dpLiabLine = $jeLines->first(fn ($l) => $l->account_id === browserAccountIdByCode('2-1700'));
    expect($dpLiabLine)->not->toBeNull();
    expect((int) $dpLiabLine->debit)->toBe(2000000);

    // Credit: Cash (1-1010, id=1001) — cash out for refund
    $cashLine = $jeLines->first(fn ($l) => $l->account_id === browserCashAccountId());
    expect($cashLine)->not->toBeNull();
    expect((int) $cashLine->credit)->toBe(2000000);

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

it('shows down payments in the list page', function () {
    // Create a DP so the list is not empty
    createDownPaymentInDb('receivable', 2000000, 'active');

    $page = loginAndVisit('/finance/down-payments');

    $page->assertSee('Down Payments');
    $page->assertSee('DPR-');
});
