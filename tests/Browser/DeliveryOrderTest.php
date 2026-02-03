<?php

declare(strict_types=1);

/**
 * SALES-PEST-03: Delivery order from invoice browser tests.
 *
 * Prerequisites:
 * - Seeded user: admin@example.com / password
 * - Seeded customer: PT Test Customer
 * - Seeded product: MCB 16A 1 Phase (id=1)
 *
 * DO creation is tested via the "Create Delivery Order" button on the
 * invoice detail page, which opens a modal and calls the API.
 *
 * Shared helpers (realDb, createInvoice, postInvoice, etc.) are in tests/Pest.php.
 *
 * NOTE: After modal action buttons (Ship, Deliver, etc.), the frontend
 * uses TanStack Query's setQueryData to update the UI reactively. We
 * rely on assertSee (which waits up to timeout) for the status change,
 * rather than navigate/reload which can cause race conditions.
 */
if (! function_exists('createDOFromInvoiceUI')) {
    /**
     * Create a DO from the invoice detail page via the UI modal.
     * Expects $page to be on a posted invoice detail page.
     * Returns the page, now on the DO detail page after navigation.
     */
    function createDOFromInvoiceUI($page, ?string $warehouseName = null)
    {
        // Click "Create Delivery Order" button in Quick Actions
        $page->click('Create Delivery Order');

        // Modal opens — assert title
        $page->assertSee('Items will be copied automatically');

        // Optionally select a warehouse
        if ($warehouseName) {
            $page->click('Select warehouse...');
            $page->click("[role=\"option\"] >> text={$warehouseName}");
        }

        // Click submit button inside the modal
        $page->click('[role="dialog"] button >> text=Create Delivery Order');

        // Wait for navigation to DO detail page
        $page->assertSee('Delivery order created');
        $page->assertSee('DO-');

        return $page;
    }
}

if (! function_exists('getDOIdFromUrl')) {
    /**
     * Get the delivery order ID from the current detail page URL.
     */
    function getDOIdFromUrl($page): int
    {
        $url = $page->url();
        preg_match('/delivery-orders\/(\d+)/', $url, $matches);

        return (int) ($matches[1] ?? 0);
    }
}

if (! function_exists('waitForDoStatus')) {
    /**
     * Wait for a delivery order status to change in the database.
     * This ensures the API action has completed before asserting UI state.
     */
    function waitForDoStatus(int $doId, string $expectedStatus, int $maxRetries = 30): void
    {
        for ($i = 0; $i < $maxRetries; $i++) {
            $status = realDb()->table('delivery_orders')->where('id', $doId)->value('status');
            if ($status === $expectedStatus) {
                return;
            }
            usleep(200_000); // 200ms
        }
    }
}

if (! function_exists('ensureWarehouse')) {
    /**
     * Ensure a warehouse exists in the database, creating one if needed.
     */
    function ensureWarehouse(): int
    {
        $db = realDb();
        $warehouse = $db->table('warehouses')->where('is_active', true)->first();

        if ($warehouse) {
            return (int) $warehouse->id;
        }

        return (int) $db->table('warehouses')->insertGetId([
            'name' => 'Gudang Utama',
            'code' => 'WH-001',
            'address' => 'Jl. Test No. 1',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

if (! function_exists('ensureProductStock')) {
    /**
     * Ensure product stock exists for a given product and warehouse.
     */
    function ensureProductStock(int $productId, int $warehouseId, int $quantity = 100): void
    {
        $db = realDb();

        $existing = $db->table('product_stocks')
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        if ($existing) {
            $db->table('product_stocks')
                ->where('id', $existing->id)
                ->update([
                    'quantity' => $quantity,
                    'total_value' => $quantity * ((int) ($existing->average_cost ?: 50000)),
                    'updated_at' => now(),
                ]);

            return;
        }

        $db->table('product_stocks')->insert([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'quantity' => $quantity,
            'reserved_quantity' => 0,
            'average_cost' => 50000,
            'total_value' => $quantity * 50000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('can create DO from invoice and verify items match', function () {
    // Create and post an invoice via SPA
    $page = createInvoice('DO Items Match Test', 5, '200000');
    $invoiceId = getInvoiceIdFromUrl($page);
    postInvoice($page);

    // Create DO from the invoice detail page via UI modal
    createDOFromInvoiceUI($page);

    $doId = getDOIdFromUrl($page);
    expect($doId)->toBeGreaterThan(0);

    // Assert DO detail page shows correct data
    $page->assertSee('Draft');
    $page->assertSee('PT Test Customer');
    $page->assertSee('DO Items Match Test');

    // DB assertion: DO linked to invoice
    $do = realDb()->table('delivery_orders')->where('id', $doId)->first();
    expect((int) $do->invoice_id)->toBe($invoiceId);
    expect($do->status)->toBe('draft');

    // Items were copied from invoice
    $doItems = realDb()->table('delivery_order_items')
        ->where('delivery_order_id', $doId)
        ->get();
    expect($doItems)->toHaveCount(1);
    expect((int) $doItems[0]->quantity)->toBe(5);
});

it('can confirm and ship a delivery order with status transitions', function () {
    // Create and post an invoice, then create DO via UI
    $page = createInvoice('DO Workflow Test', 3, '150000');
    $invoiceId = getInvoiceIdFromUrl($page);
    postInvoice($page);

    createDOFromInvoiceUI($page);
    $doId = getDOIdFromUrl($page);

    $page->assertSee('Draft');

    // --- Confirm ---
    $page->click('Confirm');

    // Wait for backend to process, then navigate to see updated state
    waitForDoStatus($doId, 'confirmed');
    $page->navigate(spaUrl("/sales/delivery-orders/{$doId}"));
    $page->assertSee('Confirmed');

    // DB assertion
    $doRecord = realDb()->table('delivery_orders')->where('id', $doId)->first();
    expect($doRecord->status)->toBe('confirmed');
    expect($doRecord->confirmed_at)->not->toBeNull();

    // --- Ship (opens modal) ---
    $page->click('Ship');
    $page->assertSee('Ship Delivery Order');

    // Fill ship modal fields
    $page->fill('input[placeholder="Enter tracking number"]', 'TRK-E2E-001');
    $page->fill('input[placeholder="Enter driver name"]', 'Test Driver');
    $page->fill('input[placeholder="Enter vehicle number"]', 'B 1234 XY');

    // Click Ship button inside the modal
    $page->click('[role="dialog"] button >> text=Ship');

    // Wait for backend to process, then navigate to see updated state
    waitForDoStatus($doId, 'shipped');
    $page->navigate(spaUrl("/sales/delivery-orders/{$doId}"));
    $page->assertSee('Shipped');

    // DB assertions
    $doRecord = realDb()->table('delivery_orders')->where('id', $doId)->first();
    expect($doRecord->status)->toBe('shipped');
    expect($doRecord->shipped_at)->not->toBeNull();
    expect($doRecord->tracking_number)->toBe('TRK-E2E-001');
    expect($doRecord->driver_name)->toBe('Test Driver');
    expect($doRecord->vehicle_number)->toBe('B 1234 XY');
});

it('can mark a shipped delivery order as delivered', function () {
    // Create and post an invoice, then create DO via UI
    $page = createInvoice('DO Deliver Test', 2, '250000');
    postInvoice($page);

    createDOFromInvoiceUI($page);
    $doId = getDOIdFromUrl($page);

    $page->assertSee('Draft');

    // --- Confirm ---
    $page->click('Confirm');
    waitForDoStatus($doId, 'confirmed');
    $page->navigate(spaUrl("/sales/delivery-orders/{$doId}"));
    $page->assertSee('Confirmed');

    // --- Ship (opens modal) ---
    $page->click('Ship');
    $page->assertSee('Ship Delivery Order');
    $page->fill('input[placeholder="Enter tracking number"]', 'TRK-DELIVER-001');
    $page->fill('input[placeholder="Enter driver name"]', 'Test Driver');
    $page->fill('input[placeholder="Enter vehicle number"]', 'B 5678 AB');
    $page->click('[role="dialog"] button >> text=Ship');

    waitForDoStatus($doId, 'shipped');
    $page->navigate(spaUrl("/sales/delivery-orders/{$doId}"));
    $page->assertSee('Shipped');

    // --- Mark Delivered (opens modal) ---
    $page->click('Mark Delivered');
    $page->assertSee('Mark as Delivered');

    // Fill deliver modal fields
    $page->fill('input[placeholder="Name of person who received"]', 'John Doe');
    $page->fill('input[placeholder="Any notes about the delivery"]', 'Received in good condition');

    // Click Mark Delivered button inside the modal
    $page->click('[role="dialog"] button >> text=Mark Delivered');

    // Wait for backend to process, then navigate to see updated state
    waitForDoStatus($doId, 'delivered');
    $page->navigate(spaUrl("/sales/delivery-orders/{$doId}"));
    $page->assertSee('Delivered');

    // DB assertions
    $doRecord = realDb()->table('delivery_orders')->where('id', $doId)->first();
    expect($doRecord->status)->toBe('delivered');
    expect($doRecord->delivered_at)->not->toBeNull();
    expect($doRecord->received_by)->toBe('John Doe');

    // All items should be fully delivered
    $doItems = realDb()->table('delivery_order_items')
        ->where('delivery_order_id', $doId)
        ->get();

    foreach ($doItems as $item) {
        expect((float) $item->quantity_delivered)->toBe((float) $item->quantity);
    }
});

it('decreases stock correctly when shipping a delivery order', function () {
    // Setup: ensure warehouse and product stock exist
    $warehouseId = ensureWarehouse();
    $productId = 1; // MCB 16A 1 Phase (seeded product)
    $initialStock = 100;
    $shipQty = 5;
    ensureProductStock($productId, $warehouseId, $initialStock);

    // Get warehouse name for UI selection
    $warehouseName = realDb()->table('warehouses')
        ->where('id', $warehouseId)
        ->value('name');

    // Create and post an invoice via SPA
    $page = createInvoice('Stock Deduction Test', $shipQty, '100000');
    $invoiceId = getInvoiceIdFromUrl($page);
    postInvoice($page);

    // Create DO via UI with warehouse selected
    createDOFromInvoiceUI($page, $warehouseName);
    $doId = getDOIdFromUrl($page);

    // Link DO items to the seeded product (SPA invoices don't have product_id)
    realDb()->table('delivery_order_items')
        ->where('delivery_order_id', $doId)
        ->update(['product_id' => $productId]);

    realDb()->table('invoice_items')
        ->where('invoice_id', $invoiceId)
        ->update(['product_id' => $productId]);

    // Navigate to DO detail explicitly to ensure fresh page state
    $page->navigate(spaUrl("/sales/delivery-orders/{$doId}"));
    $page->assertSee('Draft');

    // Confirm via UI
    $page->click('Confirm');
    waitForDoStatus($doId, 'confirmed');
    $page->navigate(spaUrl("/sales/delivery-orders/{$doId}"));
    $page->assertSee('Confirmed');

    // Ship via UI
    $page->click('Ship');
    $page->assertSee('Ship Delivery Order');
    $page->fill('input[placeholder="Enter tracking number"]', 'TRK-STOCK-001');
    $page->click('[role="dialog"] button >> text=Ship');

    // Wait for backend to process (including stock deduction), then navigate
    waitForDoStatus($doId, 'shipped');
    $page->navigate(spaUrl("/sales/delivery-orders/{$doId}"));
    $page->assertSee('Shipped');

    // DB assertion: stock should have decreased
    $stock = realDb()->table('product_stocks')
        ->where('product_id', $productId)
        ->where('warehouse_id', $warehouseId)
        ->first();

    expect((int) $stock->quantity)->toBe($initialStock - $shipQty);

    // Verify inventory movement was created
    $movement = realDb()->table('inventory_movements')
        ->where('reference_type', 'App\\Models\\Sales\\DeliveryOrder')
        ->where('reference_id', $doId)
        ->where('product_id', $productId)
        ->first();

    expect($movement)->not->toBeNull();
    expect((int) $movement->quantity)->toBe(-$shipQty);
});
