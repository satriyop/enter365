<?php

declare(strict_types=1);

/**
 * ACCT-PEST-01: Journal entry CRUD + workflow browser tests.
 *
 * Prerequisites:
 * - Seeded user: admin@example.com / password
 * - Seeded accounts: 1-1010 (Bank BCA, id=1001), 5-1002 (Pembelian, id=1055)
 *
 * Tests cover: create via SPA form, post, reverse, list page.
 *
 * JE workflow: Draft → Posted → (optionally) Reversed
 * Status labels: Draft, Posted, Reversed.
 *
 * Shared helpers (realDb, loginAndVisit, spaUrl, etc.) are in tests/Pest.php.
 */

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function getJeIdFromUrl($page): int
{
    $url = $page->url();
    preg_match('/journal-entries\/(\d+)/', $url, $matches);

    return (int) ($matches[1] ?? 0);
}

function waitForJePosted(int $jeId, int $maxRetries = 30): void
{
    for ($i = 0; $i < $maxRetries; $i++) {
        $isPosted = realDb()->table('journal_entries')->where('id', $jeId)->value('is_posted');
        if ($isPosted) {
            return;
        }
        usleep(200_000); // 200ms
    }
}

function generateJeNumber(): string
{
    $prefix = 'JE-'.now()->format('Ym').'-';
    $lastNumber = realDb()->table('journal_entries')
        ->where('entry_number', 'like', $prefix.'%')
        ->orderByDesc('entry_number')
        ->value('entry_number');

    if ($lastNumber) {
        $seq = (int) substr($lastNumber, strlen($prefix)) + 1;
    } else {
        $seq = 1;
    }

    return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
}

/**
 * Create a journal entry via DB insertion with 2 balanced lines.
 */
function createJournalEntryInDb(
    string $description = 'E2E JE Test',
    bool $isPosted = false,
    int $amount = 500000,
): int {
    $db = realDb();
    $userId = (int) $db->table('users')->where('email', 'admin@example.com')->value('id');
    $fiscalPeriodId = $db->table('fiscal_periods')
        ->where('start_date', '<=', now()->toDateString())
        ->where('end_date', '>=', now()->toDateString())
        ->value('id');

    $jeId = (int) $db->table('journal_entries')->insertGetId([
        'entry_number' => generateJeNumber(),
        'entry_date' => now()->toDateString(),
        'description' => $description,
        'reference' => 'E2E-REF',
        'source_type' => 'manual',
        'source_id' => null,
        'fiscal_period_id' => $fiscalPeriodId,
        'is_posted' => $isPosted,
        'is_reversed' => false,
        'reversed_by_id' => null,
        'reversal_of_id' => null,
        'created_by' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Line 1: Debit Bank BCA (1-1010, id=1001)
    $db->table('journal_entry_lines')->insert([
        'journal_entry_id' => $jeId,
        'account_id' => 1001,
        'description' => 'Debit line: '.$description,
        'debit' => $amount,
        'credit' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Line 2: Credit Pembelian (5-1002, id=1055)
    $db->table('journal_entry_lines')->insert([
        'journal_entry_id' => $jeId,
        'account_id' => 1055,
        'description' => 'Credit line: '.$description,
        'debit' => 0,
        'credit' => $amount,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $jeId;
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('can create a manual journal entry via form', function () {
    $page = loginAndVisit('/accounting/journal-entries/new');

    $page->assertSee('New Journal Entry');

    // Fill description
    $page->fill('[data-testid="je-description"]', 'E2E Manual JE Test');

    // Fill reference
    $page->fill('[data-testid="je-reference"]', 'REF-E2E-001');

    // Line 1: Select account (Bank BCA)
    // The Select trigger renders as a button with role="combobox" inside the table
    $page->click('[data-testid="je-line-0-account"]');
    $page->click('[role="option"] >> text=Bank BCA');

    // Fill debit for first line
    $page->click('[data-testid="je-line-0-debit"]');
    $page->type('[data-testid="je-line-0-debit"]', '500000');

    // Line 2: Select account (Pembelian — exact match to avoid "Diskon Pembelian" etc.)
    $page->click('[data-testid="je-line-1-account"]');
    $page->click('[role="option"] >> text=5-1002 - Pembelian');

    // Fill credit for second line
    $page->click('[data-testid="je-line-1-credit"]');
    $page->type('[data-testid="je-line-1-credit"]', '500000');

    // Should show balanced indicator
    $page->assertSee('Entry is balanced');

    // Submit the form
    $page->click('[data-testid="je-submit"]');

    // Wait for navigation to detail page
    $page->assertSee('JE-');

    $jeId = getJeIdFromUrl($page);
    expect($jeId)->toBeGreaterThan(0);

    // Should show Draft status
    $page->assertSee('Draft');

    // DB assertions
    $je = realDb()->table('journal_entries')->where('id', $jeId)->first();
    expect($je->is_posted)->toBeFalsy();
    expect($je->description)->toBe('E2E Manual JE Test');

    $lines = realDb()->table('journal_entry_lines')
        ->where('journal_entry_id', $jeId)
        ->get();
    expect($lines)->toHaveCount(2);
    expect($lines->sum('debit'))->toBe($lines->sum('credit'));
});

it('can post a draft journal entry', function () {
    // Create JE via DB for speed
    $jeId = createJournalEntryInDb('Post JE Test');

    $page = loginAndVisit("/accounting/journal-entries/{$jeId}");

    $page->assertSee('JE-');
    $page->assertSee('Draft');
    $page->assertSee('Post Entry');

    // Click "Post Entry" button — opens a modal
    $page->click('button >> text=Post Entry');

    // Modal should appear with confirmation
    $page->assertSee('Post Journal Entry');

    // Click confirm button inside the modal
    $page->click('[role="dialog"] button >> text=Post Entry');

    // Wait for backend to process
    waitForJePosted($jeId);

    // Reload to see updated status
    $page->navigate(spaUrl("/accounting/journal-entries/{$jeId}"));

    // Should show "Posted" status
    $page->assertSee('Posted');

    // DB assertions
    $je = realDb()->table('journal_entries')->where('id', $jeId)->first();
    expect($je->is_posted)->toBeTruthy();
});

it('can reverse a posted journal entry', function () {
    // Create and post a JE via DB for speed
    $jeId = createJournalEntryInDb('Reverse JE Test', isPosted: true, amount: 300000);

    $page = loginAndVisit("/accounting/journal-entries/{$jeId}");

    $page->assertSee('Posted');
    $page->assertSee('Reverse');

    // Click "Reverse" button — opens a modal
    $page->click('button >> text=Reverse');

    // Modal should appear
    $page->assertSee('Reverse Journal Entry');

    // Click "Create Reversal" in the modal
    $page->click('[role="dialog"] button >> text=Create Reversal');

    // Wait for processing — the original entry should be marked as reversed
    for ($i = 0; $i < 30; $i++) {
        $isReversed = realDb()->table('journal_entries')->where('id', $jeId)->value('is_reversed');
        if ($isReversed) {
            break;
        }
        usleep(200_000);
    }

    // Reload the page
    $page->navigate(spaUrl("/accounting/journal-entries/{$jeId}"));

    // Should show "Reversed" status
    $page->assertSee('Reversed');

    // DB assertions
    $original = realDb()->table('journal_entries')->where('id', $jeId)->first();
    expect($original->is_reversed)->toBeTruthy();
    expect($original->reversed_by_id)->not->toBeNull();

    // Reversing entry should exist and be posted
    $reversing = realDb()->table('journal_entries')
        ->where('id', $original->reversed_by_id)
        ->first();
    expect($reversing)->not->toBeNull();
    expect($reversing->is_posted)->toBeTruthy();
    expect((int) $reversing->reversal_of_id)->toBe($jeId);

    // Reversing entry lines should have swapped debits/credits
    $originalLines = realDb()->table('journal_entry_lines')
        ->where('journal_entry_id', $jeId)
        ->get();
    $reversingLines = realDb()->table('journal_entry_lines')
        ->where('journal_entry_id', $original->reversed_by_id)
        ->get();

    // Net effect should be zero
    $netDebit = $originalLines->sum('debit') + $reversingLines->sum('debit');
    $netCredit = $originalLines->sum('credit') + $reversingLines->sum('credit');
    expect($netDebit)->toBe($netCredit);

    // Trial balance should remain balanced after reversal
    $trialBalance = realDb()->table('journal_entry_lines as jel')
        ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
        ->where('je.is_posted', true)
        ->where('je.deleted_at', null)
        ->where('je.is_reversed', false)
        ->selectRaw('COALESCE(SUM(jel.debit), 0) as total_debit, COALESCE(SUM(jel.credit), 0) as total_credit')
        ->first();

    expect((int) $trialBalance->total_debit)->toBe((int) $trialBalance->total_credit);
});

it('rejects creation of unbalanced journal entry', function () {
    $page = loginAndVisit('/accounting/journal-entries/new');

    $page->assertSee('New Journal Entry');

    // Fill description
    $page->fill('[data-testid="je-description"]', 'E2E Unbalanced JE Test');

    // Line 1: Select account (Bank BCA), debit 500000
    $page->click('[data-testid="je-line-0-account"]');
    $page->click('[role="option"] >> text=Bank BCA');

    $page->click('[data-testid="je-line-0-debit"]');
    $page->type('[data-testid="je-line-0-debit"]', '500000');

    // Line 2: Select account (Pembelian), credit 300000 — intentionally unbalanced
    $page->click('[data-testid="je-line-1-account"]');
    $page->click('[role="option"] >> text=5-1002 - Pembelian');

    $page->click('[data-testid="je-line-1-credit"]');
    $page->type('[data-testid="je-line-1-credit"]', '300000');

    // Should show "Entry is not balanced" indicator (frontend validation)
    $page->assertSee('Entry is not balanced');

    // Submit the form — frontend should show validation error and stay on page
    $page->script('document.querySelector("[data-testid=je-submit]")?.click()');
    usleep(1_500_000); // 1.5s for any async validation

    // Should still be on the same page with validation error
    $page->assertSee('New Journal Entry');
    $page->assertSee('Entry is not balanced');

    // DB: no new JE created with this description
    $je = realDb()->table('journal_entries')
        ->where('description', 'E2E Unbalanced JE Test')
        ->first();
    expect($je)->toBeNull();
});

it('shows journal entries in the list page', function () {
    // Create a JE so the list is not empty
    createJournalEntryInDb('List Page JE Test');

    $page = loginAndVisit('/accounting/journal-entries');

    $page->assertSee('Journal Entries');
    $page->assertSee('JE-');
});
