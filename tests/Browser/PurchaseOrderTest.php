<?php

declare(strict_types=1);

/**
 * PURCH-PEST-01: Purchase order CRUD + workflow browser tests.
 *
 * Prerequisites:
 * - Seeded user: admin@example.com / password
 * - Seeded suppliers: Rohan-Predovic (id=1), Goldner Group (id=2)
 * - Seeded product: MCB 16A 1 Phase (id=1, purchase_price=50000)
 *
 * Tests cover: create via SPA form, submit, approve, reject with reason.
 * The detail page uses backend-computed `can_*` flags for button visibility.
 *
 * Status labels (Indonesian): Draft, Diajukan, Disetujui, Ditolak, Dibatalkan.
 *
 * Shared helpers (realDb, loginAndVisit, spaUrl, etc.) are in tests/Pest.php.
 */

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

if (! function_exists('getTestSupplierName')) {
    /**
     * Get a seeded supplier name for PO tests.
     */
    function getTestSupplierName(): string
    {
        $name = realDb()->table('contacts')
            ->where('type', 'supplier')
            ->orderBy('id')
            ->value('name');

        return $name ?: 'Rohan-Predovic';
    }
}

if (! function_exists('getPoIdFromUrl')) {
    /**
     * Get the PO ID from the current detail page URL.
     */
    function getPoIdFromUrl($page): int
    {
        $url = $page->url();
        preg_match('/purchase-orders\/(\d+)/', $url, $matches);

        return (int) ($matches[1] ?? 0);
    }
}

if (! function_exists('waitForPoStatus')) {
    /**
     * Wait for a PO status to change in the database.
     */
    function waitForPoStatus(int $poId, string $expectedStatus, int $maxRetries = 30): void
    {
        for ($i = 0; $i < $maxRetries; $i++) {
            $status = realDb()->table('purchase_orders')->where('id', $poId)->value('status');
            if ($status === $expectedStatus) {
                return;
            }
            usleep(200_000); // 200ms
        }
    }
}

if (! function_exists('generatePoNumber')) {
    /**
     * Generate a unique PO number for DB insertion.
     */
    function generatePoNumber(): string
    {
        $prefix = 'PO-'.now()->format('Ym').'-';
        $lastNumber = realDb()->table('purchase_orders')
            ->where('po_number', 'like', $prefix.'%')
            ->orderByDesc('po_number')
            ->value('po_number');

        if ($lastNumber) {
            $seq = (int) substr($lastNumber, strlen($prefix)) + 1;
        } else {
            $seq = 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}

if (! function_exists('createPurchaseOrderInDb')) {
    /**
     * Create a PO via DB insertion with one item.
     * Used to fast-forward to specific statuses for workflow tests.
     */
    function createPurchaseOrderInDb(
        string $status = 'draft',
        string $description = 'E2E PO Item',
        int $qty = 5,
        int $unitPrice = 50000,
    ): int {
        $db = realDb();
        $userId = (int) $db->table('users')->where('email', 'admin@example.com')->value('id');
        $contactId = (int) $db->table('contacts')->where('type', 'supplier')->orderBy('id')->value('id');

        $lineTotal = $qty * $unitPrice;
        $taxAmount = (int) round($lineTotal * 0.11);
        $totalAmount = $lineTotal + $taxAmount;

        $poId = (int) $db->table('purchase_orders')->insertGetId([
            'po_number' => generatePoNumber(),
            'revision' => 0,
            'contact_id' => $contactId,
            'po_date' => now()->toDateString(),
            'expected_date' => now()->addDays(7)->toDateString(),
            'reference' => null,
            'subject' => $description,
            'status' => $status,
            'currency' => 'IDR',
            'exchange_rate' => 1,
            'subtotal' => $lineTotal,
            'discount_type' => 'percentage',
            'discount_value' => 0,
            'discount_amount' => 0,
            'tax_rate' => 11,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'base_currency_total' => $totalAmount,
            'notes' => null,
            'terms_conditions' => null,
            'shipping_address' => null,
            'created_by' => $userId,
            'submitted_by' => $status !== 'draft' ? $userId : null,
            'submitted_at' => $status !== 'draft' ? now() : null,
            'approved_by' => in_array($status, ['approved', 'partial', 'received']) ? $userId : null,
            'approved_at' => in_array($status, ['approved', 'partial', 'received']) ? now() : null,
            'rejected_by' => $status === 'rejected' ? $userId : null,
            'rejected_at' => $status === 'rejected' ? now() : null,
            'rejection_reason' => $status === 'rejected' ? 'Test rejection' : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $db->table('purchase_order_items')->insert([
            'purchase_order_id' => $poId,
            'product_id' => browserProductId(),
            'description' => $description,
            'quantity' => $qty,
            'quantity_received' => 0,
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

        return $poId;
    }
}

if (! function_exists('createPurchaseOrderViaUi')) {
    /**
     * Create a PO via the SPA form and return the page on the detail view.
     */
    function createPurchaseOrderViaUi(
        string $description = 'E2E PO Item',
        int $qty = 5,
        string $price = '50000',
    ) {
        $supplierName = getTestSupplierName();
        $page = loginAndVisit('/purchasing/purchase-orders/new');

        $page->assertSee('New Purchase Order');

        // Select vendor — Radix-Vue Select (same pattern as createInvoice)
        $page->click('[data-testid="po-vendor"]');
        $page->click('[role="option"] >> text='.$supplierName);

        // Fill line item description
        $page->fill('[data-testid="po-item-0-description"]', $description);

        // Fill quantity (step="any" allows fractional quantities)
        $page->fill('[data-testid="po-item-0-quantity"]', (string) $qty);

        // Fill unit price (CurrencyInput)
        $page->click('[data-testid="po-item-0-price"]');
        $page->type('[data-testid="po-item-0-price"]', $price);

        // Submit the form
        $page->click('[data-testid="po-submit"]');

        // Wait for success message and navigation to detail page
        $page->assertSee('created successfully');
        $page->assertSee('PO-');

        return $page;
    }
}

if (! function_exists('submitPurchaseOrder')) {
    /**
     * Submit a draft PO from its detail page and return the PO ID.
     * Mirrors the manual flow: click Submit → wait for status → reload.
     */
    function submitPurchaseOrder($page): int
    {
        $poId = getPoIdFromUrl($page);

        $page->click('Submit');

        waitForPoStatus($poId, 'submitted');
        $page->navigate(spaUrl("/purchasing/purchase-orders/{$poId}"));

        $page->assertSee('Diajukan');

        return $poId;
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('can create a purchase order via SPA form', function () {
    $page = createPurchaseOrderViaUi('PO Create Test', 10, '50000');

    $poId = getPoIdFromUrl($page);
    expect($poId)->toBeGreaterThan(0);

    // Detail page should show Draft status and correct data
    $page->assertSee('Draft');
    $page->assertSee(getTestSupplierName());
    $page->assertSee('PO Create Test');

    // Submit button should be visible for draft
    $page->assertSee('Submit');

    // DB assertions
    $po = realDb()->table('purchase_orders')->where('id', $poId)->first();
    expect($po->status)->toBe('draft');

    $items = realDb()->table('purchase_order_items')
        ->where('purchase_order_id', $poId)
        ->get();
    expect($items)->toHaveCount(1);
    expect((int) $items[0]->quantity)->toBe(10);
    expect((int) $items[0]->unit_price)->toBe(50000);
});

it('can submit a purchase order for approval', function () {
    $page = createPurchaseOrderViaUi('PO Submit Test', 5, '50000');
    $poId = getPoIdFromUrl($page);

    $page->assertSee('Draft');
    $page->assertSee('Submit');

    // Click Submit
    $page->click('Submit');

    // Wait for backend to process, then navigate
    waitForPoStatus($poId, 'submitted');
    $page->navigate(spaUrl("/purchasing/purchase-orders/{$poId}"));

    // Status should show "Diajukan" (Indonesian for Submitted)
    $page->assertSee('Diajukan');

    // Approve and Reject buttons should now be visible
    $page->assertSee('Approve');
    $page->assertSee('Reject');

    // DB assertions
    $po = realDb()->table('purchase_orders')->where('id', $poId)->first();
    expect($po->status)->toBe('submitted');
    expect($po->submitted_at)->not->toBeNull();
});

it('can approve a submitted purchase order', function () {
    $page = createPurchaseOrderViaUi('PO Approve Test');
    $poId = submitPurchaseOrder($page);

    $page->assertSee('Approve');

    // Click Approve
    $page->click('Approve');

    // Wait for backend to process, then navigate
    waitForPoStatus($poId, 'approved');
    $page->navigate(spaUrl("/purchasing/purchase-orders/{$poId}"));

    // Status should show "Disetujui" (Indonesian for Approved)
    $page->assertSee('Disetujui');

    // Create GRN button should now be visible
    $page->assertSee('Create GRN');

    // DB assertions
    $po = realDb()->table('purchase_orders')->where('id', $poId)->first();
    expect($po->status)->toBe('approved');
    expect($po->approved_at)->not->toBeNull();
});

it('can reject a submitted purchase order with reason', function () {
    $page = createPurchaseOrderViaUi('PO Reject Test');
    $poId = submitPurchaseOrder($page);

    $page->assertSee('Reject');

    // Click Reject — opens modal
    $page->click('Reject');

    // Reject modal should appear
    $page->assertSee('Reject Purchase Order');
    $page->assertSee('Please provide a reason');

    // Fill rejection reason
    $page->fill('textarea[placeholder="Enter rejection reason..."]', 'Price too high, need renegotiation');

    // Click Reject button in modal
    $page->click('[role="dialog"] button >> text=Reject');

    // Wait for backend to process, then navigate
    waitForPoStatus($poId, 'rejected');
    $page->navigate(spaUrl("/purchasing/purchase-orders/{$poId}"));

    // Status should show "Ditolak" (Indonesian for Rejected)
    $page->assertSee('Ditolak');

    // Rejection reason should be displayed
    $page->assertSee('Price too high, need renegotiation');

    // DB assertions
    $po = realDb()->table('purchase_orders')->where('id', $poId)->first();
    expect($po->status)->toBe('rejected');
    expect($po->rejected_at)->not->toBeNull();
    expect($po->rejection_reason)->toBe('Price too high, need renegotiation');
});

it('shows purchase orders in the list page', function () {
    // Create a PO so the list is not empty
    createPurchaseOrderInDb('draft', 'List Page Test');

    $page = loginAndVisit('/purchasing/purchase-orders');

    $page->assertSee('Purchase Orders');
    $page->assertSee('PO-');
    $page->assertSee(getTestSupplierName());
});

it('can cancel a draft purchase order with reason', function () {
    $page = createPurchaseOrderViaUi('PO Cancel Test');
    $poId = getPoIdFromUrl($page);

    $page->assertSee('Draft');
    $page->assertSee('Cancel');

    // Click Cancel button
    $page->click('Cancel');

    // Cancel modal should appear
    $page->assertSee('Cancel Purchase Order');

    // Fill cancellation reason
    $page->fill('textarea[placeholder="Enter cancellation reason..."]', 'No longer needed');

    // Click "Cancel PO" button in modal
    $page->click('[role="dialog"] button >> text=Cancel PO');

    // Wait for backend to process, then navigate
    waitForPoStatus($poId, 'cancelled');
    $page->navigate(spaUrl("/purchasing/purchase-orders/{$poId}"));

    // Status should show "Dibatalkan" (Indonesian for Cancelled)
    $page->assertSee('Dibatalkan');

    // DB assertions
    $po = realDb()->table('purchase_orders')->where('id', $poId)->first();
    expect($po->status)->toBe('cancelled');
    expect($po->cancellation_reason)->toBe('No longer needed');
});

it('displays correct workflow buttons at each status', function () {
    // Approved PO should show Create GRN and Cancel, not Submit/Approve/Reject
    $poId = createPurchaseOrderInDb('approved', 'Button Visibility Test');

    $page = loginAndVisit("/purchasing/purchase-orders/{$poId}");

    $page->assertSee('Disetujui');
    $page->assertSee('Create GRN');
    $page->assertSee('Cancel');

    // DB assertion
    $po = realDb()->table('purchase_orders')->where('id', $poId)->first();
    expect($po->status)->toBe('approved');
});
