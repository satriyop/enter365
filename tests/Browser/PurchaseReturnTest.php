<?php

declare(strict_types=1);

/**
 * PURCH-PEST-04: Purchase return workflow browser tests.
 *
 * Prerequisites:
 * - Seeded user: admin@example.com / password
 * - Seeded suppliers: Rohan-Predovic (or first supplier)
 * - Seeded accounts: 2-1100 (AP, id=1024), 5-1004 (Purchase Returns, id=1057),
 *   1-1300 (PPN Masukan, id=1007)
 *
 * No purchase return form page exists in the SPA — PRs are created via DB
 * insertion and workflow actions are tested from the detail page.
 *
 * PR workflow: Draft → Submitted → Approved → Completed
 * Status labels (Indonesian): Draft, Diajukan, Disetujui, Selesai.
 *
 * Shared helpers (realDb, loginAndVisit, spaUrl, etc.) are in tests/Pest.php.
 * Supplier helper (getTestSupplierName) is in BillTest.php (or PurchaseOrderTest.php).
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

if (! function_exists('getPrIdFromUrl')) {
    function getPrIdFromUrl($page): int
    {
        $url = $page->url();
        preg_match('/purchase-returns\/(\d+)/', $url, $matches);

        return (int) ($matches[1] ?? 0);
    }
}

if (! function_exists('waitForPrStatus')) {
    function waitForPrStatus(int $prId, string $expectedStatus, int $maxRetries = 30): void
    {
        for ($i = 0; $i < $maxRetries; $i++) {
            $status = realDb()->table('purchase_returns')->where('id', $prId)->value('status');
            if ($status === $expectedStatus) {
                return;
            }
            usleep(200_000); // 200ms
        }
    }
}

if (! function_exists('generatePrNumber')) {
    function generatePrNumber(): string
    {
        $prefix = 'PR-'.now()->format('Ym').'-';
        $lastNumber = realDb()->table('purchase_returns')
            ->where('return_number', 'like', $prefix.'%')
            ->orderByDesc('return_number')
            ->value('return_number');

        if ($lastNumber) {
            $seq = (int) substr($lastNumber, strlen($prefix)) + 1;
        } else {
            $seq = 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}

if (! function_exists('createPurchaseReturnInDb')) {
    /**
     * Create a purchase return via DB insertion with one item.
     */
    function createPurchaseReturnInDb(
        string $status = 'draft',
        string $reason = 'E2E Test Return',
        int $qty = 2,
        int $unitPrice = 100000,
        ?int $billId = null,
    ): int {
        $db = realDb();
        $userId = (int) $db->table('users')->where('email', 'admin@example.com')->value('id');
        $contactId = (int) $db->table('contacts')->where('type', 'supplier')->orderBy('id')->value('id');

        $lineTotal = $qty * $unitPrice;
        $taxAmount = (int) round($lineTotal * 0.11);
        $totalAmount = $lineTotal + $taxAmount;

        $prId = (int) $db->table('purchase_returns')->insertGetId([
            'return_number' => generatePrNumber(),
            'bill_id' => $billId,
            'contact_id' => $contactId,
            'warehouse_id' => null,
            'return_date' => now()->toDateString(),
            'reason' => $reason,
            'notes' => 'Created by E2E test',
            'subtotal' => $lineTotal,
            'tax_rate' => 11,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'status' => $status,
            'journal_entry_id' => null,
            'debit_note_id' => null,
            'created_by' => $userId,
            'submitted_by' => $status !== 'draft' ? $userId : null,
            'submitted_at' => $status !== 'draft' ? now() : null,
            'approved_by' => in_array($status, ['approved', 'completed']) ? $userId : null,
            'approved_at' => in_array($status, ['approved', 'completed']) ? now() : null,
            'completed_by' => $status === 'completed' ? $userId : null,
            'completed_at' => $status === 'completed' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $db->table('purchase_return_items')->insert([
            'purchase_return_id' => $prId,
            'bill_item_id' => null,
            'product_id' => browserProductId(),
            'description' => $reason,
            'quantity' => $qty,
            'unit' => 'pcs',
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'discount_percent' => 0,
            'discount_amount' => 0,
            'tax_rate' => 11,
            'tax_amount' => $taxAmount,
            'condition' => 'damaged',
            'notes' => null,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $prId;
    }
}

if (! function_exists('submitPurchaseReturn')) {
    /**
     * Submit a draft PR from its detail page and return the PR ID.
     */
    function submitPurchaseReturn($page): int
    {
        $prId = getPrIdFromUrl($page);

        $page->click('Submit');

        waitForPrStatus($prId, 'submitted');
        $page->navigate(spaUrl("/purchasing/purchase-returns/{$prId}"));

        $page->assertSee('Diajukan');

        return $prId;
    }
}

if (! function_exists('approvePurchaseReturn')) {
    /**
     * Approve a submitted PR from its detail page and return the PR ID.
     */
    function approvePurchaseReturn($page): int
    {
        $prId = getPrIdFromUrl($page);

        $page->click('Approve');

        waitForPrStatus($prId, 'approved');
        $page->navigate(spaUrl("/purchasing/purchase-returns/{$prId}"));

        $page->assertSee('Disetujui');

        return $prId;
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('can view a draft purchase return detail page', function () {
    $prId = createPurchaseReturnInDb('draft', 'View PR Detail Test');

    $page = loginAndVisit("/purchasing/purchase-returns/{$prId}");

    // Assert detail page renders with correct data
    $page->assertSee('PR-');
    $page->assertSee('Draft');
    $page->assertSee(getTestSupplierName());
    $page->assertSee('View PR Detail Test');

    // Items table should show the item
    $page->assertSee('pcs');

    // Submit button should be visible for draft
    $page->assertSee('Submit');

    // DB assertions
    $pr = realDb()->table('purchase_returns')->where('id', $prId)->first();
    expect($pr->status)->toBe('draft');
    expect($pr->reason)->toBe('View PR Detail Test');

    $items = realDb()->table('purchase_return_items')
        ->where('purchase_return_id', $prId)
        ->get();
    expect($items)->toHaveCount(1);
    expect((int) $items[0]->quantity)->toBe(2);
});

it('can submit a draft purchase return', function () {
    $prId = createPurchaseReturnInDb('draft', 'Submit PR Test');

    $page = loginAndVisit("/purchasing/purchase-returns/{$prId}");
    $page->assertSee('Draft');
    $page->assertSee('Submit');

    // Click the Submit button
    $page->click('Submit');

    // Wait for the API to process
    waitForPrStatus($prId, 'submitted');
    $page->navigate(spaUrl("/purchasing/purchase-returns/{$prId}"));

    // Status label should show Indonesian "Diajukan"
    $page->assertSee('Diajukan');

    // DB assertions
    $pr = realDb()->table('purchase_returns')->where('id', $prId)->first();
    expect($pr->status)->toBe('submitted');
    expect($pr->submitted_at)->not->toBeNull();
});

it('approving a purchase return creates correct journal entries', function () {
    $prId = createPurchaseReturnInDb('draft', 'Approve JE Test', 5, 100000);

    $page = loginAndVisit("/purchasing/purchase-returns/{$prId}");

    // Submit first
    submitPurchaseReturn($page);

    // Then approve
    approvePurchaseReturn($page);

    // DB assertions: verify journal entry was created
    $pr = realDb()->table('purchase_returns')->where('id', $prId)->first();
    expect($pr->status)->toBe('approved');
    expect($pr->journal_entry_id)->not->toBeNull();

    // Verify journal entry lines balance
    $journalLines = realDb()->table('journal_entry_lines')
        ->where('journal_entry_id', $pr->journal_entry_id)
        ->get();

    $totalDebit = $journalLines->sum('debit');
    $totalCredit = $journalLines->sum('credit');

    // Journal must balance
    expect($totalDebit)->toBe($totalCredit);

    // AP account (2-1100, id=1024) should be debited for total_amount
    $apLine = $journalLines->first(fn ($l) => $l->account_id === browserAccountIdByCode('2-1100'));
    expect($apLine)->not->toBeNull();
    expect($apLine->debit)->toBe($pr->total_amount);

    // Purchase Returns account (5-1004, id=1057) should be credited for subtotal
    $returnLine = $journalLines->first(fn ($l) => $l->account_id === browserAccountIdByCode('5-1004'));
    expect($returnLine)->not->toBeNull();
    expect((int) $returnLine->credit)->toBe((int) $pr->subtotal);

    // PPN Masukan account (1-1300, id=1007) should be credited for tax_amount
    if ((int) $pr->tax_amount > 0) {
        $taxLine = $journalLines->first(fn ($l) => $l->account_id === browserAccountIdByCode('1-1300'));
        expect($taxLine)->not->toBeNull();
        expect((int) $taxLine->credit)->toBe((int) $pr->tax_amount);
    }

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

it('can complete an approved purchase return', function () {
    $prId = createPurchaseReturnInDb('draft', 'Complete PR Test');

    $page = loginAndVisit("/purchasing/purchase-returns/{$prId}");

    // Submit and approve
    submitPurchaseReturn($page);
    approvePurchaseReturn($page);

    // Click "Complete & Update Inventory"
    $page->click('Complete & Update Inventory');

    // Wait for DB status change
    waitForPrStatus($prId, 'completed');
    $page->navigate(spaUrl("/purchasing/purchase-returns/{$prId}"));

    // Status label should show Indonesian "Selesai"
    $page->assertSee('Selesai');

    // DB assertions
    $pr = realDb()->table('purchase_returns')->where('id', $prId)->first();
    expect($pr->status)->toBe('completed');
    expect($pr->completed_at)->not->toBeNull();
});

it('shows purchase returns in the list page', function () {
    // Create a PR so the list is not empty
    createPurchaseReturnInDb('draft', 'List Page Test');

    $page = loginAndVisit('/purchasing/purchase-returns');

    $page->assertSee('Purchase Returns');
    $page->assertSee('PR-');
    $page->assertSee(getTestSupplierName());
});
