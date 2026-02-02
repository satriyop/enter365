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
 * Note: DOs are created via direct DB insertion because:
 * 1. No "Create DO" button exists on the invoice detail page
 * 2. Frontend API URL mismatch: sends POST /delivery-order but backend expects /create-delivery-order
 *
 * Shared helpers (realDb, createInvoice, postInvoice, etc.) are in tests/Pest.php.
 */

/**
 * Create a delivery order from an invoice via direct DB access.
 * Bypasses the service layer but creates valid data for UI workflow testing.
 */
function createDeliveryOrderForInvoice(int $invoiceId, ?int $warehouseId = null): object
{
    $db = realDb();
    $invoice = $db->table('invoices')->where('id', $invoiceId)->first();

    $now = now();
    $prefix = 'DO-'.$now->format('Ym').'-';
    $lastSeq = $db->table('delivery_orders')
        ->where('do_number', 'like', $prefix.'%')
        ->count();
    $doNumber = $prefix.str_pad((string) ($lastSeq + 1), 4, '0', STR_PAD_LEFT);

    $doId = $db->table('delivery_orders')->insertGetId([
        'do_number' => $doNumber,
        'invoice_id' => $invoiceId,
        'contact_id' => $invoice->contact_id,
        'warehouse_id' => $warehouseId,
        'do_date' => $now->format('Y-m-d'),
        'status' => 'draft',
        'created_by' => $invoice->created_by,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    // Copy invoice items to delivery order items
    $items = $db->table('invoice_items')->where('invoice_id', $invoiceId)->get();
    foreach ($items as $item) {
        $db->table('delivery_order_items')->insert([
            'delivery_order_id' => $doId,
            'invoice_item_id' => $item->id,
            'product_id' => $item->product_id,
            'description' => $item->description,
            'quantity' => $item->quantity,
            'quantity_delivered' => 0,
            'unit' => $item->unit ?? 'pcs',
            'unit_price' => $item->unit_price ?? 0,
            'line_total' => $item->line_total ?? 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    return $db->table('delivery_orders')->where('id', $doId)->first();
}

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

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('can create DO from invoice and verify items match', function () {
    // Create and post an invoice via SPA
    $page = createInvoice('DO Items Match Test', 5, '200000');
    $invoiceId = getInvoiceIdFromUrl($page);
    postInvoice($page);

    // Create DO from the posted invoice via DB
    $do = createDeliveryOrderForInvoice($invoiceId);

    // Navigate to DO detail page
    $page->navigate(spaUrl('/sales/delivery-orders/'.$do->id));

    // Assert DO detail page loads with correct data
    $page->assertSee($do->do_number);
    $page->assertSee('Draft');
    $page->assertSee('PT Test Customer');

    // Assert items table shows the item from the invoice
    $page->assertSee('DO Items Match Test');

    // Verify in list page
    $page->navigate(spaUrl('/sales/delivery-orders'));
    $page->assertSee('Delivery Orders');
    $page->assertSee($do->do_number);
});

it('can confirm and ship a delivery order with status transitions', function () {
    // Create and post an invoice, then create DO
    $page = createInvoice('DO Workflow Test', 3, '150000');
    $invoiceId = getInvoiceIdFromUrl($page);
    postInvoice($page);

    $do = createDeliveryOrderForInvoice($invoiceId);

    // Navigate to DO detail page
    $page->navigate(spaUrl('/sales/delivery-orders/'.$do->id));
    $page->assertSee('Draft');

    // --- Confirm ---
    $page->click('Confirm');

    // Wait for API response and reload
    reloadPage($page);
    $page->assertSee('Confirmed');

    // DB assertion
    $doRecord = realDb()->table('delivery_orders')->where('id', $do->id)->first();
    expect($doRecord->status)->toBe('confirmed');
    expect($doRecord->confirmed_at)->not->toBeNull();

    // --- Ship (opens modal) ---
    $page->click('Ship');
    $page->assertSee('Ship Delivery Order'); // Modal title

    // Fill ship modal fields
    $page->fill('input[placeholder="Enter tracking number"]', 'TRK-E2E-001');
    $page->fill('input[placeholder="Enter driver name"]', 'Test Driver');
    $page->fill('input[placeholder="Enter vehicle number"]', 'B 1234 XY');

    // Click Ship button inside the modal (scoped to dialog)
    $page->click('[role="dialog"] button >> text=Ship');

    // Wait for API response and reload
    reloadPage($page);
    $page->assertSee('Shipped');

    // DB assertions
    $doRecord = realDb()->table('delivery_orders')->where('id', $do->id)->first();
    expect($doRecord->status)->toBe('shipped');
    expect($doRecord->shipped_at)->not->toBeNull();
    expect($doRecord->tracking_number)->toBe('TRK-E2E-001');
    expect($doRecord->driver_name)->toBe('Test Driver');
    expect($doRecord->vehicle_number)->toBe('B 1234 XY');
});

it('can mark a shipped delivery order as delivered', function () {
    // Create and post an invoice, then create DO
    $page = createInvoice('DO Deliver Test', 2, '250000');
    $invoiceId = getInvoiceIdFromUrl($page);
    postInvoice($page);

    $do = createDeliveryOrderForInvoice($invoiceId);

    // Pre-set status to shipped via DB (skip Confirm and Ship UI to speed up test)
    realDb()->table('delivery_orders')->where('id', $do->id)->update([
        'status' => 'shipped',
        'confirmed_at' => now(),
        'confirmed_by' => $do->created_by,
        'shipped_at' => now(),
        'shipped_by' => $do->created_by,
    ]);

    // Navigate to DO detail page
    $page->navigate(spaUrl('/sales/delivery-orders/'.$do->id));
    $page->assertSee('Shipped');

    // --- Mark Delivered (opens modal) ---
    $page->click('Mark Delivered');
    $page->assertSee('Mark as Delivered'); // Modal title

    // Fill deliver modal fields
    $page->fill('input[placeholder="Name of person who received"]', 'John Doe');
    $page->fill('input[placeholder="Any notes about the delivery"]', 'Received in good condition');

    // Click Mark Delivered button inside the modal
    $page->click('[role="dialog"] button >> text=Mark Delivered');

    // Wait for API response and reload
    reloadPage($page);
    $page->assertSee('Delivered');

    // DB assertions
    $doRecord = realDb()->table('delivery_orders')->where('id', $do->id)->first();
    expect($doRecord->status)->toBe('delivered');
    expect($doRecord->delivered_at)->not->toBeNull();
    expect($doRecord->received_by)->toBe('John Doe');

    // All items should be fully delivered
    $doItems = realDb()->table('delivery_order_items')
        ->where('delivery_order_id', $do->id)
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

    // Create and post an invoice via SPA
    $page = createInvoice('Stock Deduction Test', $shipQty, '100000');
    $invoiceId = getInvoiceIdFromUrl($page);
    postInvoice($page);

    // Create DO with warehouse association
    $do = createDeliveryOrderForInvoice($invoiceId, $warehouseId);

    // Link DO items and invoice items to the seeded product
    realDb()->table('delivery_order_items')
        ->where('delivery_order_id', $do->id)
        ->update(['product_id' => $productId]);

    realDb()->table('invoice_items')
        ->where('invoice_id', $invoiceId)
        ->update(['product_id' => $productId]);

    // Confirm the DO via DB so Ship button shows
    realDb()->table('delivery_orders')->where('id', $do->id)->update([
        'status' => 'confirmed',
        'confirmed_at' => now(),
        'confirmed_by' => $do->created_by,
    ]);

    // Navigate to DO detail page
    $page->navigate(spaUrl('/sales/delivery-orders/'.$do->id));
    $page->assertSee('Confirmed');

    // Ship via UI
    $page->click('Ship');
    $page->assertSee('Ship Delivery Order');
    $page->fill('input[placeholder="Enter tracking number"]', 'TRK-STOCK-001');
    $page->click('[role="dialog"] button >> text=Ship');

    // Wait for API response
    reloadPage($page);
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
        ->where('reference_id', $do->id)
        ->where('product_id', $productId)
        ->first();

    expect($movement)->not->toBeNull();
    expect((int) $movement->quantity)->toBe(-$shipQty);
});
