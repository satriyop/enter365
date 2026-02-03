<?php

declare(strict_types=1);

/**
 * FIN-PEST-02: Down payment browser tests (LIMITED scope).
 *
 * Prerequisites:
 * - Seeded user: admin@example.com / password
 * - Seeded customer: PT Test Customer
 * - Seeded accounts: 1-1010 (Bank BCA, id=1001)
 *
 * Limitations:
 * - No dedicated form page — DPs are created via DB insertion
 * - Account mapping bug (dp_receivable => '2-2100' maps to wrong account)
 *   means accounting verification is limited
 * - Tests focus on detail display, cancel workflow, and list page
 *
 * DP statuses: active, fully_applied, refunded, cancelled
 *
 * Shared helpers (realDb, loginAndVisit, spaUrl, etc.) are in tests/Pest.php.
 */

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

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
        $contactId = (int) $db->table('contacts')->where('name', 'PT Test Customer')->value('id');
    } else {
        $contactId = (int) $db->table('contacts')->where('type', 'supplier')->orderBy('id')->value('id');
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
        'cash_account_id' => 1001, // Bank BCA
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

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('can view a down payment detail page', function () {
    $dpId = createDownPaymentInDb('receivable', 5000000, 'active');

    $page = loginAndVisit("/finance/down-payments/{$dpId}");

    // Assert detail page renders with correct data
    $page->assertSee('DPR-');
    $page->assertSee('Aktif'); // Indonesian for Active
    $page->assertSee('PT Test Customer');

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

it('shows down payments in the list page', function () {
    // Create a DP so the list is not empty
    createDownPaymentInDb('receivable', 2000000, 'active');

    $page = loginAndVisit('/finance/down-payments');

    $page->assertSee('Down Payments');
    $page->assertSee('DPR-');
});
