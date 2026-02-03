<?php

declare(strict_types=1);

/**
 * ACC-PEST-03: Fiscal period lock/close browser tests.
 *
 * Prerequisites:
 * - Seeded user: admin@example.com / password
 * - Seeded fiscal periods covering the current date
 *
 * Tests cover: lock/unlock toggle, close prevents JE posting.
 *
 * Fiscal period statuses: Open, Locked, Closed.
 * Lock/Unlock via list page buttons + modals.
 * Close prevents JE posting (checked in JournalService::postEntry()).
 *
 * Shared helpers (realDb, loginAndVisit, spaUrl, etc.) are in tests/Pest.php.
 */

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

if (! function_exists('withRealDb')) {
    function withRealDb(callable $callback): mixed
    {
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

function getOpenFiscalPeriodId(): int
{
    // First try to find a period covering the current date
    $id = (int) realDb()->table('fiscal_periods')
        ->where('start_date', '<=', now()->toDateString())
        ->where('end_date', '>=', now()->toDateString())
        ->where('is_closed', false)
        ->value('id');

    if ($id > 0) {
        return $id;
    }

    // Fall back to any open (not closed) period
    return (int) realDb()->table('fiscal_periods')
        ->where('is_closed', false)
        ->orderByDesc('start_date')
        ->value('id');
}

function waitForFiscalPeriodLocked(int $periodId, bool $expectedLocked, int $maxRetries = 30): void
{
    for ($i = 0; $i < $maxRetries; $i++) {
        $isLocked = (bool) realDb()->table('fiscal_periods')->where('id', $periodId)->value('is_locked');
        if ($isLocked === $expectedLocked) {
            return;
        }
        usleep(200_000); // 200ms
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('can lock and unlock a fiscal period', function () {
    $page = loginAndVisit('/accounting/fiscal-periods');

    // Assert page loads with period list
    $page->assertSee('Fiscal Periods');

    // Find an open (not locked, not closed) period from the current date
    $periodId = getOpenFiscalPeriodId();
    expect($periodId)->toBeGreaterThan(0);

    $period = realDb()->table('fiscal_periods')->where('id', $periodId)->first();

    // Ensure the period is open (not locked, not closed) before starting.
    // Reset if left locked by a previous test run.
    if ($period->is_locked && ! $period->is_closed) {
        realDb()->table('fiscal_periods')->where('id', $periodId)->update(['is_locked' => false]);
        $page->navigate(spaUrl('/accounting/fiscal-periods'));
    } elseif ($period->is_closed) {
        $this->markTestSkipped('Current fiscal period is closed — cannot test lock/unlock');
    }

    // Assert "Open" status label visible and "Lock" button visible
    $page->assertSee('Open');
    $page->assertSee('Lock');

    // Click Lock button on the list page (use nth=0 to avoid ambiguity)
    $page->click('button >> text=Lock >> nth=0');

    // Lock modal should appear
    $page->assertSee('Lock Fiscal Period');

    // Click confirm "Lock Period" button in the modal footer
    $page->click('[role="dialog"] button >> text=Lock Period');

    // Wait for DB: is_locked = true
    waitForFiscalPeriodLocked($periodId, true);

    // Reload the page to see updated status
    $page->navigate(spaUrl('/accounting/fiscal-periods'));
    $page->assertSee('Locked');

    // DB assertions
    $period = realDb()->table('fiscal_periods')->where('id', $periodId)->first();
    expect((bool) $period->is_locked)->toBeTrue();

    // Now unlock it
    $page->click('button >> text=Unlock >> nth=0');

    // Unlock modal should appear
    $page->assertSee('Unlock Fiscal Period');

    // Click confirm "Unlock Period" button in the modal footer
    $page->click('[role="dialog"] button >> text=Unlock Period');

    // Wait for DB: is_locked = false
    waitForFiscalPeriodLocked($periodId, false);

    // DB assertions
    $period = realDb()->table('fiscal_periods')->where('id', $periodId)->first();
    expect((bool) $period->is_locked)->toBeFalse();
});

it('closing a fiscal period prevents posting journal entries', function () {
    // Find current fiscal period
    $periodId = getOpenFiscalPeriodId();
    expect($periodId)->toBeGreaterThan(0);

    $period = realDb()->table('fiscal_periods')->where('id', $periodId)->first();

    // Ensure the period is open (not closed) before starting
    if ($period->is_closed) {
        $this->markTestSkipped('Current fiscal period is already closed');
    }

    // Close the period via service call in withRealDb context
    withRealDb(function () use ($periodId) {
        $period = \App\Models\Accounting\FiscalPeriod::find($periodId);

        // Must lock before closing
        if (! $period->is_locked) {
            $period->lock();
        }

        $service = app(\App\Contracts\Accounting\FiscalPeriodServiceInterface::class);
        $service->closePeriod($period, 'E2E test: closing for JE prevention test');
    });

    // Verify period is closed in DB
    $period = realDb()->table('fiscal_periods')->where('id', $periodId)->first();
    expect((bool) $period->is_closed)->toBeTrue();

    // Try to post a new JE with date in that closed period via the service
    $postFailed = false;
    $errorMessage = '';

    try {
        withRealDb(function () use ($periodId, $period) {
            $userId = (int) realDb()->table('users')->where('email', 'admin@example.com')->value('id');

            // Create a draft JE in the closed period
            $je = \App\Models\Accounting\JournalEntry::create([
                'entry_number' => 'JE-CLOSE-TEST-'.now()->timestamp,
                'entry_date' => $period->start_date, // Date within the closed period
                'description' => 'E2E closed period test',
                'reference' => 'E2E-REF',
                'source_type' => 'manual',
                'fiscal_period_id' => $periodId,
                'is_posted' => false,
                'is_reversed' => false,
                'created_by' => $userId,
            ]);

            // Add balanced lines
            $je->lines()->createMany([
                ['account_id' => 1001, 'description' => 'Test debit', 'debit' => 100000, 'credit' => 0],
                ['account_id' => 1055, 'description' => 'Test credit', 'debit' => 0, 'credit' => 100000],
            ]);

            // Attempt to post via JournalService — should throw exception
            $service = app(\App\Services\Accounting\JournalService::class);
            $service->postEntry($je);
        });
    } catch (\Exception $e) {
        $postFailed = true;
        $errorMessage = $e->getMessage();
    }

    expect($postFailed)->toBeTrue();
    expect($errorMessage)->toContain('closed fiscal period');

    // CRITICAL: Reopen the period after test to avoid breaking other tests
    withRealDb(function () use ($periodId) {
        $service = app(\App\Contracts\Accounting\FiscalPeriodServiceInterface::class);
        $period = \App\Models\Accounting\FiscalPeriod::find($periodId);
        $service->reopenPeriod($period);
    });

    // Verify period is reopened
    $period = realDb()->table('fiscal_periods')->where('id', $periodId)->first();
    expect((bool) $period->is_closed)->toBeFalse();
    expect((bool) $period->is_locked)->toBeFalse();
});
