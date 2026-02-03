<?php

declare(strict_types=1);

/**
 * SALES-PEST-04: Sales return workflow browser tests.
 *
 * Prerequisites:
 * - Seeded user: admin@example.com / password
 * - Seeded customer: PT Test Customer (contact_id=4)
 * - Feature flag `sales_returns` enabled
 *
 * Workflow transition tests (submit, cancel) create SRs via the SPA form
 * at /sales/sales-returns/new. Read-only display tests use DB insertion.
 *
 * Shared helpers (realDb, loginAndVisit, spaUrl, etc.) are in tests/Pest.php.
 */

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Get the admin user ID from the seeded database.
 */
function getAdminUserId(): int
{
    return (int) realDb()->table('users')
        ->where('email', 'admin@example.com')
        ->value('id');
}

/**
 * Get the PT Test Customer contact ID.
 */
function getTestCustomerId(): int
{
    return (int) realDb()->table('contacts')
        ->where('name', 'PT Test Customer')
        ->value('id');
}

/**
 * Generate a unique sales return number.
 */
function generateSrNumber(): string
{
    $prefix = 'SR-'.now()->format('Ym').'-';
    $lastNumber = realDb()->table('sales_returns')
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

/**
 * Create a sales return via DB insertion with one item.
 * Returns the SR id.
 */
function createSalesReturnInDb(
    string $status = 'draft',
    string $reason = 'E2E Test Return',
    int $qty = 2,
    int $unitPrice = 100000,
    ?int $invoiceId = null,
): int {
    $db = realDb();
    $userId = getAdminUserId();
    $contactId = getTestCustomerId();

    $lineTotal = $qty * $unitPrice;
    $taxAmount = (int) round($lineTotal * 0.11);
    $totalAmount = $lineTotal + $taxAmount;

    $srId = (int) $db->table('sales_returns')->insertGetId([
        'return_number' => generateSrNumber(),
        'invoice_id' => $invoiceId,
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

    $db->table('sales_return_items')->insert([
        'sales_return_id' => $srId,
        'invoice_item_id' => null,
        'product_id' => 1, // MCB 16A 1 Phase (seeded)
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

    return $srId;
}

/**
 * Wait for a sales return status to change in the database.
 */
function waitForSrStatus(int $srId, string $expectedStatus, int $maxRetries = 30): void
{
    for ($i = 0; $i < $maxRetries; $i++) {
        $status = realDb()->table('sales_returns')->where('id', $srId)->value('status');
        if ($status === $expectedStatus) {
            return;
        }
        usleep(200_000); // 200ms
    }
}

/**
 * Get the sales return ID from the current detail page URL.
 */
function getSrIdFromUrl($page): int
{
    $url = $page->url();
    preg_match('/sales-returns\/(\d+)/', $url, $matches);

    return (int) ($matches[1] ?? 0);
}

/**
 * Create a sales return via the SPA form and return the page on the detail view.
 */
function createSalesReturnViaUi(
    string $description = 'E2E SR Item',
    int $qty = 2,
    string $price = '100000',
) {
    $page = loginAndVisit('/sales/sales-returns/new');

    $page->assertSee('New Sales Return');

    // Select customer — Radix-Vue Select
    $page->click('Select customer...');
    $page->click('[role="option"] >> text=PT Test Customer');

    // Fill line item description
    $page->fill('input[placeholder="Item description"]', $description);

    // Fill quantity (step="any" allows fractional quantities)
    $page->fill('table input[type="number"][step="any"]', (string) $qty);

    // Fill unit price (CurrencyInput)
    $page->click('td input[inputmode="numeric"]');
    $page->type('td input[inputmode="numeric"]', $price);

    // Submit the form
    $page->click('button >> text=Create Sales Return');

    // Wait for success message and navigation to detail page
    $page->assertSee('created successfully');
    $page->assertSee('SR-');

    return $page;
}

/**
 * Submit a draft SR from its detail page and return the SR ID.
 */
function submitSalesReturn($page): int
{
    $srId = getSrIdFromUrl($page);

    $page->click('Submit');

    waitForSrStatus($srId, 'submitted');
    $page->navigate(spaUrl("/sales/sales-returns/{$srId}"));

    $page->assertSee('Diajukan');

    return $srId;
}

/**
 * Approve a submitted SR from its detail page and return the SR ID.
 */
function approveSalesReturn($page): int
{
    $srId = getSrIdFromUrl($page);

    $page->click('Approve');

    waitForSrStatus($srId, 'approved');
    $page->navigate(spaUrl("/sales/sales-returns/{$srId}"));

    $page->assertSee('Disetujui');

    return $srId;
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('can view a draft sales return detail page', function () {
    $srId = createSalesReturnInDb('draft', 'View Detail Test');

    $page = loginAndVisit("/sales/sales-returns/{$srId}");

    // Assert detail page renders with correct data
    $page->assertSee('SR-');
    $page->assertSee('Draft');
    $page->assertSee('PT Test Customer');
    $page->assertSee('View Detail Test');

    // Items table should show the item
    $page->assertSee('pcs');

    // Submit button should be visible for draft
    $page->assertSee('Submit');

    // DB assertions
    $sr = realDb()->table('sales_returns')->where('id', $srId)->first();
    expect($sr->status)->toBe('draft');
    expect($sr->reason)->toBe('View Detail Test');

    $items = realDb()->table('sales_return_items')
        ->where('sales_return_id', $srId)
        ->get();
    expect($items)->toHaveCount(1);
    expect((int) $items[0]->quantity)->toBe(2);
});

it('can submit a draft sales return via UI', function () {
    $page = createSalesReturnViaUi('Submit Workflow Test');
    $srId = getSrIdFromUrl($page);

    $page->assertSee('Draft');
    $page->assertSee('Submit');

    // Click the Submit button
    $page->click('Submit');

    // Wait for the API to process, then navigate to see updated state
    waitForSrStatus($srId, 'submitted');
    $page->navigate(spaUrl("/sales/sales-returns/{$srId}"));

    // Status label should show Indonesian "Diajukan"
    $page->assertSee('Diajukan');

    // DB assertions
    $sr = realDb()->table('sales_returns')->where('id', $srId)->first();
    expect($sr->status)->toBe('submitted');
    expect($sr->submitted_at)->not->toBeNull();
    expect($sr->submitted_by)->toBe(getAdminUserId());
});

it('can approve a submitted sales return via UI', function () {
    $page = createSalesReturnViaUi('Approve Workflow Test');
    $srId = submitSalesReturn($page);

    $page->assertSee('Approve');

    // Click the Approve button
    $page->click('Approve');

    // Wait for the API to process
    waitForSrStatus($srId, 'approved');

    // Navigate to see updated state
    $page->navigate(spaUrl("/sales/sales-returns/{$srId}"));

    // Status label should show Indonesian "Disetujui"
    $page->assertSee('Disetujui');
    $page->assertSee('PT Test Customer');
    $page->assertSee('Approve Workflow Test');

    // Complete button should be visible for approved status
    $page->assertSee('Complete');

    // DB assertions
    $sr = realDb()->table('sales_returns')->where('id', $srId)->first();
    expect($sr->status)->toBe('approved');
    expect($sr->approved_at)->not->toBeNull();
    expect($sr->approved_by)->toBe(getAdminUserId());
});

it('can complete an approved sales return via UI', function () {
    $page = createSalesReturnViaUi('Complete Workflow Test');
    submitSalesReturn($page);
    $srId = approveSalesReturn($page);

    $page->assertSee('Complete');

    // Click the Complete button
    $page->click('Complete');

    // Wait for the API to process
    waitForSrStatus($srId, 'completed');

    // Navigate to see updated state
    $page->navigate(spaUrl("/sales/sales-returns/{$srId}"));

    // Status label should show Indonesian "Selesai"
    $page->assertSee('Selesai');

    // DB assertions
    $sr = realDb()->table('sales_returns')->where('id', $srId)->first();
    expect($sr->status)->toBe('completed');
    expect($sr->completed_at)->not->toBeNull();
    expect($sr->completed_by)->toBe(getAdminUserId());
});

it('can cancel a draft sales return via dropdown menu', function () {
    $page = createSalesReturnViaUi('Cancel Workflow Test');
    $srId = getSrIdFromUrl($page);

    $page->assertSee('Draft');

    // Open the "more actions" dropdown (Radix DropdownMenuTrigger)
    $page->click('[aria-haspopup="menu"]');

    // Click Cancel in the dropdown menu
    $page->click('[role="menuitem"] >> text=Cancel');

    // Cancel modal should appear
    $page->assertSee('Cancel Sales Return');
    $page->assertSee('Are you sure you want to cancel this sales return?');

    // Fill optional reason
    $page->fill('input[placeholder="Enter cancellation reason"]', 'Testing cancel flow');

    // Click "Cancel Return" button in the modal
    $page->click('[role="dialog"] button >> text=Cancel Return');

    // Wait for the API to process
    waitForSrStatus($srId, 'cancelled');

    // Navigate to see updated state
    $page->navigate(spaUrl("/sales/sales-returns/{$srId}"));

    // Status label should show Indonesian "Dibatalkan"
    $page->assertSee('Dibatalkan');

    // DB assertions
    $sr = realDb()->table('sales_returns')->where('id', $srId)->first();
    expect($sr->status)->toBe('cancelled');
});

it('can verify completed status displays correctly with activity timeline', function () {
    $srId = createSalesReturnInDb('completed', 'Timeline Test');

    $page = loginAndVisit("/sales/sales-returns/{$srId}");

    // Status should show "Selesai"
    $page->assertSee('Selesai');

    // Activity timeline should show key events
    $page->assertSee('Activity');
    $page->assertSee('Created');
    $page->assertSee('Approved');
    $page->assertSee('Completed');
});

it('displays sales returns in the list page', function () {
    // Create a recognizable SR
    $srId = createSalesReturnInDb('draft', 'List Page Test');
    $sr = realDb()->table('sales_returns')->where('id', $srId)->first();

    $page = loginAndVisit('/sales/sales-returns');

    // List page should render
    $page->assertSee('Sales Returns');

    // Should show the SR number (or at least the prefix)
    $page->assertSee('SR-');
    $page->assertSee('PT Test Customer');
});

it('can create sales return from invoice via API and verify link', function () {
    // Create and post an invoice first
    $page = createInvoice('SR From Invoice Test', 3, '200000');
    $invoiceId = getInvoiceIdFromUrl($page);
    postInvoice($page);

    $invoice = realDb()->table('invoices')->where('id', $invoiceId)->first();

    // Create SR linked to the invoice via DB
    $srId = createSalesReturnInDb('draft', 'Defective items', 2, 200000, $invoiceId);

    // Navigate to the SR detail page
    $page->navigate(spaUrl("/sales/sales-returns/{$srId}"));

    $page->assertSee('SR-');
    $page->assertSee('Draft');
    $page->assertSee('PT Test Customer');

    // Should show the linked invoice number
    $page->assertSee($invoice->invoice_number);

    // DB assertion: SR is linked to the invoice
    $sr = realDb()->table('sales_returns')->where('id', $srId)->first();
    expect((int) $sr->invoice_id)->toBe($invoiceId);
});
