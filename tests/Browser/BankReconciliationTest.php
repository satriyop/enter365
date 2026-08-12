<?php

declare(strict_types=1);

/**
 * ACC-PEST: Bank reconciliation browser chain.
 *
 * Hybrid flow:
 * - Seed a receive payment in the live browser DB (match target)
 * - Create bank transaction via SPA form
 * - Match → Unmatch → Match → Reconcile via UI
 * - Optional report page loads with selected bank account
 *
 * Prerequisites:
 * - SPA_URL, API_URL, admin@example.com / password
 * - FEATURE_BANK_RECONCILIATION enabled (core default true)
 * - Chart account 1-1010 Bank BCA
 *
 * Related backlog: tasks/backlog/003-browser-bank-recon.md
 */
if (! function_exists('waitForBankTxnStatus')) {
    function waitForBankTxnStatus(int $txnId, string $expectedStatus, int $maxRetries = 40): void
    {
        for ($i = 0; $i < $maxRetries; $i++) {
            $status = realDb()->table('bank_transactions')->where('id', $txnId)->value('status');
            if ($status === $expectedStatus) {
                return;
            }
            usleep(250_000);
        }
    }
}

/**
 * @return array{
 *   payment_id: int,
 *   payment_number: string,
 *   amount: int,
 *   account_id: int,
 *   account_label: string,
 *   contact_name: string
 * }
 */
function seedBankReconPayment(): array
{
    $db = realDb();
    $suffix = substr((string) time(), -6);
    $amount = 7_654_000 + ((int) $suffix % 1000); // unique-ish amount for suggestions

    $accountId = browserCashAccountId(); // 1-1010 Bank BCA
    $account = $db->table('accounts')->where('id', $accountId)->first();
    expect($account)->not->toBeNull();

    $customer = ensureBrowserTestCustomer();
    $adminId = $db->table('users')->where('email', 'admin@example.com')->value('id');

    $paymentNumber = "RCV-E2E-{$suffix}";

    $paymentId = (int) $db->table('payments')->insertGetId([
        'payment_number' => $paymentNumber,
        'type' => 'receive',
        'contact_id' => $customer->id,
        'payment_date' => now()->toDateString(),
        'amount' => $amount,
        'payment_method' => 'transfer',
        'reference' => "TRF-E2E-{$suffix}",
        'notes' => "E2E bank recon payment {$suffix}",
        'currency' => 'IDR',
        'exchange_rate' => 1,
        'base_currency_amount' => $amount,
        'cash_account_id' => $accountId,
        'journal_entry_id' => null,
        'payable_type' => null,
        'payable_id' => null,
        'is_voided' => false,
        'created_by' => $adminId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [
        'payment_id' => $paymentId,
        'payment_number' => $paymentNumber,
        'amount' => $amount,
        'account_id' => $accountId,
        'account_label' => "{$account->code} - {$account->name}",
        'contact_name' => $customer->name,
    ];
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('creates bank transaction, matches payment, unmatches, rematches, and reconciles', function () {
    $seed = seedBankReconPayment();
    $description = 'E2E Bank Recon In '.$seed['payment_number'];

    // --- Create via UI ---
    $page = loginAndVisit('/accounting/bank-reconciliation/new');
    $page->assertSee('Add Bank Transaction');

    $page->click('[data-testid="bank-txn-account"]');
    $page->click('[role="option"] >> text=1-1010');

    $page->fill('[data-testid="bank-txn-description"]', $description);
    $page->fill('[data-testid="bank-txn-reference"]', $seed['payment_number']);

    // Type defaults to Debit (Money In) — matches receive payment
    $page->click('[data-testid="bank-txn-amount"]');
    $page->fill('[data-testid="bank-txn-amount"]', (string) $seed['amount']);

    $page->click('[data-testid="bank-txn-submit"]');

    // Wait for SPA navigation to detail (create toast may flash)
    $txnId = 0;
    for ($i = 0; $i < 40; $i++) {
        $url = $page->url();
        if (preg_match('#/accounting/bank-reconciliation/(\d+)#', $url, $matches)
            && ! str_contains($url, '/new')) {
            $txnId = (int) $matches[1];
            break;
        }
        usleep(250_000);
    }

    // Fallback: resolve from DB if URL race (form title also contains "Bank Transaction")
    if ($txnId === 0) {
        $txnId = (int) realDb()->table('bank_transactions')
            ->where('description', $description)
            ->orderByDesc('id')
            ->value('id');
        expect($txnId)->toBeGreaterThan(0);
        $page = loginAndVisit("/accounting/bank-reconciliation/{$txnId}");
    }

    expect($txnId)->toBeGreaterThan(0);
    $page->assertSee('Suggested Matches');

    $txn = realDb()->table('bank_transactions')->where('id', $txnId)->first();
    expect($txn->status)->toBe('unmatched')
        ->and((int) $txn->debit)->toBe($seed['amount'])
        ->and((int) $txn->account_id)->toBe($seed['account_id']);

    // --- Match suggested payment ---
    $page->assertSee('Suggested Matches');
    $page->assertSee($seed['payment_number']);
    $page->click('[data-testid="match-payment-button"]');
    waitForBankTxnStatus($txnId, 'matched');

    $page = loginAndVisit("/accounting/bank-reconciliation/{$txnId}");
    $page->assertSee('Sudah Di-match'); // BankTransactionStatus::Matched label
    $page->assertSee($seed['payment_number']);

    $txn = realDb()->table('bank_transactions')->where('id', $txnId)->first();
    expect($txn->status)->toBe('matched')
        ->and((int) $txn->matched_payment_id)->toBe($seed['payment_id']);

    // --- Unmatch ---
    $page->click('[data-testid="bank-txn-unmatch"]');
    waitForBankTxnStatus($txnId, 'unmatched');
    $page = loginAndVisit("/accounting/bank-reconciliation/{$txnId}");
    $page->assertSee('Belum Di-match');

    expect(realDb()->table('bank_transactions')->where('id', $txnId)->value('matched_payment_id'))
        ->toBeNull();

    // --- Rematch ---
    $page->assertSee($seed['payment_number']);
    $page->click('[data-testid="match-payment-button"]');
    waitForBankTxnStatus($txnId, 'matched');
    $page = loginAndVisit("/accounting/bank-reconciliation/{$txnId}");
    $page->assertSee('Sudah Di-match');

    // --- Reconcile ---
    $page->click('[data-testid="bank-txn-reconcile"]');
    $page->assertSee('Reconcile Transaction');
    $page->click('[data-testid="bank-txn-reconcile-confirm"]');
    waitForBankTxnStatus($txnId, 'reconciled');

    $page = loginAndVisit("/accounting/bank-reconciliation/{$txnId}");
    $page->assertSee('Sudah Rekonsiliasi');
    $page->assertSee('Reconciled');

    $txn = realDb()->table('bank_transactions')->where('id', $txnId)->first();
    expect($txn->status)->toBe('reconciled')
        ->and($txn->reconciled_at)->not->toBeNull()
        ->and((int) $txn->matched_payment_id)->toBe($seed['payment_id']);
});

it('loads bank reconciliation report for bank account', function () {
    $accountId = browserCashAccountId();

    $page = loginAndVisit('/reports/bank-reconciliation-report');
    $page->assertSee('Bank Reconciliation Report');
    $page->assertSee('Select a bank account above to view the reconciliation report');

    // Select Bank BCA
    $page->click('button[role="combobox"]');
    $page->click('[role="option"] >> text=1-1010');

    // Report should load (structure exists even with sparse data)
    $page->assertSee('Book Balance');
    $page->assertNoJavascriptErrors();

    // Coherent: account code appears after selection
    $page->assertSee('1-1010');
});
