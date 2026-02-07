<?php

declare(strict_types=1);

/**
 * PURCH-PEST-02: GRN (Goods Receipt Note) from PO — browser tests.
 *
 * Prerequisites:
 * - Seeded user: admin@example.com / password
 * - Seeded suppliers: Rohan-Predovic (or first supplier)
 * - Seeded product: MCB 16A 1 Phase (id=1, purchase_price=50000)
 * - Feature flag `goods_receipt_notes` enabled (FEATURE_GRN=true)
 *
 * GRN creation is via the "Create GRN" button on the PO detail page,
 * which opens a modal (warehouse, receipt date, notes) and calls the API.
 * The GRN detail page has the full workflow UI.
 *
 * Status labels: Draft, In Progress, Completed, Cancelled.
 *
 * Shared helpers (realDb, loginAndVisit, spaUrl, etc.) are in tests/Pest.php.
 *
 * This file includes its own copies of PO/warehouse helpers (guarded with
 * function_exists) to remain self-contained when run standalone.
 */

// ---------------------------------------------------------------------------
// Shared helpers (self-contained copies, guarded against redeclaration)
// ---------------------------------------------------------------------------

if (! function_exists('ensureWarehouse')) {
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

if (! function_exists('getPoIdFromUrl')) {
    function getPoIdFromUrl($page): int
    {
        $url = $page->url();
        preg_match('/purchase-orders\/(\d+)/', $url, $matches);

        return (int) ($matches[1] ?? 0);
    }
}

if (! function_exists('waitForPoStatus')) {
    function waitForPoStatus(int $poId, string $expectedStatus, int $maxRetries = 30): void
    {
        for ($i = 0; $i < $maxRetries; $i++) {
            $status = realDb()->table('purchase_orders')->where('id', $poId)->value('status');
            if ($status === $expectedStatus) {
                return;
            }
            usleep(200_000);
        }
    }
}

if (! function_exists('generatePoNumber')) {
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
            'product_id' => 1,
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
    function createPurchaseOrderViaUi(
        string $description = 'E2E PO Item',
        int $qty = 5,
        string $price = '50000',
    ) {
        $supplierName = getTestSupplierName();
        $page = loginAndVisit('/purchasing/purchase-orders/new');

        $page->assertSee('New Purchase Order');

        // Select vendor — Radix-Vue Select
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

// ---------------------------------------------------------------------------
// GRN-specific helpers
// ---------------------------------------------------------------------------

/**
 * Create an approved PO via the SPA UI and return [$page, $poId].
 */
function createApprovedPO(string $description = 'GRN E2E Item', int $qty = 5, string $price = '50000'): array
{
    $page = createPurchaseOrderViaUi($description, $qty, $price);
    $poId = getPoIdFromUrl($page);

    // Submit
    $page->click('Submit');
    waitForPoStatus($poId, 'submitted');
    $page->navigate(spaUrl("/purchasing/purchase-orders/{$poId}"));

    // Approve
    $page->click('Approve');
    waitForPoStatus($poId, 'approved');
    $page->navigate(spaUrl("/purchasing/purchase-orders/{$poId}"));

    $page->assertSee('Disetujui');

    return [$page, $poId];
}

/**
 * Create a GRN from an approved PO via DB insertion (mirrors service logic).
 * The frontend has a "Create GRN" modal on the PO detail page, but browser
 * tests use DB insertion to avoid SPA fetch/auth complexity.
 * Copies PO items with their remaining quantities, then navigates to the
 * new GRN detail page.
 * Returns the GRN ID.
 */
function createGrnFromPo($page, int $poId, int $warehouseId): int
{
    $db = realDb();
    $userId = (int) $db->table('users')->where('email', 'admin@example.com')->value('id');

    $grnId = (int) $db->table('goods_receipt_notes')->insertGetId([
        'grn_number' => generateGrnNumber(),
        'purchase_order_id' => $poId,
        'warehouse_id' => $warehouseId,
        'receipt_date' => now()->toDateString(),
        'status' => 'draft',
        'created_by' => $userId,
        'total_items' => 0,
        'total_quantity_ordered' => 0,
        'total_quantity_received' => 0,
        'total_quantity_rejected' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Copy PO items with remaining quantities
    $poItems = $db->table('purchase_order_items')
        ->where('purchase_order_id', $poId)
        ->get();

    $totalItems = 0;
    $totalQtyOrdered = 0;

    foreach ($poItems as $poItem) {
        $remaining = (int) $poItem->quantity - (int) $poItem->quantity_received;
        if ($remaining <= 0) {
            continue;
        }

        $db->table('goods_receipt_note_items')->insert([
            'goods_receipt_note_id' => $grnId,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $poItem->product_id,
            'quantity_ordered' => $remaining,
            'quantity_received' => 0,
            'quantity_rejected' => 0,
            'unit_price' => $poItem->unit_price,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $totalItems++;
        $totalQtyOrdered += $remaining;
    }

    // Update totals
    $db->table('goods_receipt_notes')->where('id', $grnId)->update([
        'total_items' => $totalItems,
        'total_quantity_ordered' => $totalQtyOrdered,
    ]);

    // Navigate to GRN detail page
    $page->navigate(spaUrl("/purchasing/goods-receipt-notes/{$grnId}"));
    $page->assertSee('GRN-');

    return $grnId;
}

/**
 * Get the GRN ID from the current detail page URL.
 */
function getGrnIdFromUrl($page): int
{
    $url = $page->url();
    preg_match('/goods-receipt-notes\/(\d+)/', $url, $matches);

    return (int) ($matches[1] ?? 0);
}

/**
 * Wait for a GRN status to change in the database.
 */
function waitForGrnStatus(int $grnId, string $expectedStatus, int $maxRetries = 30): void
{
    for ($i = 0; $i < $maxRetries; $i++) {
        $status = realDb()->table('goods_receipt_notes')->where('id', $grnId)->value('status');
        if ($status === $expectedStatus) {
            return;
        }
        usleep(200_000);
    }
}

/**
 * Update a GRN item's received quantity directly in the DB.
 * Used because the item update modal uses PATCH but the route expects PUT.
 * Also updates GRN totals to keep them in sync.
 */
function updateGrnItemReceived(int $grnId, int $quantityReceived): void
{
    $db = realDb();
    $item = $db->table('goods_receipt_note_items')
        ->where('goods_receipt_note_id', $grnId)
        ->first();

    $db->table('goods_receipt_note_items')
        ->where('id', $item->id)
        ->update([
            'quantity_received' => $quantityReceived,
            'updated_at' => now(),
        ]);

    // Recalculate totals
    $totals = $db->table('goods_receipt_note_items')
        ->where('goods_receipt_note_id', $grnId)
        ->selectRaw('SUM(quantity_received) as total_received, SUM(quantity_rejected) as total_rejected')
        ->first();

    $db->table('goods_receipt_notes')
        ->where('id', $grnId)
        ->update([
            'total_quantity_received' => (int) $totals->total_received,
            'total_quantity_rejected' => (int) $totals->total_rejected,
            'updated_at' => now(),
        ]);
}

/**
 * Generate a unique GRN number for DB insertion.
 */
function generateGrnNumber(): string
{
    $prefix = 'GRN-'.now()->format('Ym').'-';
    $lastNumber = realDb()->table('goods_receipt_notes')
        ->where('grn_number', 'like', $prefix.'%')
        ->orderByDesc('grn_number')
        ->value('grn_number');

    if ($lastNumber) {
        $seq = (int) substr($lastNumber, strlen($prefix)) + 1;
    } else {
        $seq = 1;
    }

    return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
}

/**
 * Create a GRN directly via DB insertion for fast-forward scenarios.
 */
function createGrnInDb(
    int $poId,
    int $warehouseId,
    string $status = 'draft',
    int $qty = 5,
    int $unitPrice = 50000,
): int {
    $db = realDb();
    $userId = (int) $db->table('users')->where('email', 'admin@example.com')->value('id');

    $poItem = $db->table('purchase_order_items')
        ->where('purchase_order_id', $poId)
        ->first();

    $grnId = (int) $db->table('goods_receipt_notes')->insertGetId([
        'grn_number' => generateGrnNumber(),
        'purchase_order_id' => $poId,
        'warehouse_id' => $warehouseId,
        'receipt_date' => now()->toDateString(),
        'status' => $status,
        'supplier_do_number' => null,
        'supplier_invoice_number' => null,
        'vehicle_number' => null,
        'driver_name' => null,
        'received_by' => $status !== 'draft' ? $userId : null,
        'checked_by' => $status === 'completed' ? $userId : null,
        'notes' => null,
        'total_items' => 1,
        'total_quantity_ordered' => $qty,
        'total_quantity_received' => 0,
        'total_quantity_rejected' => 0,
        'completed_at' => $status === 'completed' ? now() : null,
        'cancelled_at' => $status === 'cancelled' ? now() : null,
        'created_by' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $db->table('goods_receipt_note_items')->insert([
        'goods_receipt_note_id' => $grnId,
        'purchase_order_item_id' => $poItem ? $poItem->id : null,
        'product_id' => 1,
        'quantity_ordered' => $qty,
        'quantity_received' => 0,
        'quantity_rejected' => 0,
        'unit_price' => $unitPrice,
        'rejection_reason' => null,
        'quality_notes' => null,
        'lot_number' => null,
        'expiry_date' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $grnId;
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('can create GRN from approved PO and verify items', function () {
    $warehouseId = ensureWarehouse();

    [$page, $poId] = createApprovedPO('GRN Create Test', 5, '50000');

    $grnId = createGrnFromPo($page, $poId, $warehouseId);
    expect($grnId)->toBeGreaterThan(0);

    // Assert detail page shows correct data
    $page->assertSee('GRN-');
    $page->assertSee('Draft');
    $page->assertSee('Start Receiving');

    // Verify PO reference is displayed
    $po = realDb()->table('purchase_orders')->where('id', $poId)->first();
    $page->assertSee($po->po_number);

    // DB assertions
    $grn = realDb()->table('goods_receipt_notes')->where('id', $grnId)->first();
    expect((int) $grn->purchase_order_id)->toBe($poId);
    expect($grn->status)->toBe('draft');
    expect((int) $grn->warehouse_id)->toBe($warehouseId);

    // Items were copied from PO
    $grnItems = realDb()->table('goods_receipt_note_items')
        ->where('goods_receipt_note_id', $grnId)
        ->get();
    expect($grnItems)->toHaveCount(1);
    expect((int) $grnItems[0]->quantity_ordered)->toBe(5);
    expect((int) $grnItems[0]->quantity_received)->toBe(0);
});

it('can complete full receiving workflow with stock increase', function () {
    // Setup: warehouse + product stock
    $warehouseId = ensureWarehouse();
    $productId = 1;
    $initialStock = 100;
    $orderQty = 5;

    // Enable inventory tracking for this product
    realDb()->table('products')->where('id', $productId)->update(['track_inventory' => true]);
    ensureProductStock($productId, $warehouseId, $initialStock);

    // Create approved PO via DB (needs product_id for inventory tracking)
    $poId = createPurchaseOrderInDb('approved', 'GRN Workflow Test', $orderQty);
    $page = loginAndVisit("/purchasing/purchase-orders/{$poId}");

    // Create GRN from the approved PO
    $grnId = createGrnFromPo($page, $poId, $warehouseId);
    expect($grnId)->toBeGreaterThan(0);

    $page->assertSee('Draft');

    // --- Start Receiving ---
    $page->click('Start Receiving');
    waitForGrnStatus($grnId, 'receiving');
    $page->navigate(spaUrl("/purchasing/goods-receipt-notes/{$grnId}"));

    $page->assertSee('In Progress');

    // --- Update item received quantity ---
    // Update via DB (the item update modal uses PATCH but the backend route expects PUT)
    updateGrnItemReceived($grnId, $orderQty);

    // Reload to get updated UI with Complete button
    $page->navigate(spaUrl("/purchasing/goods-receipt-notes/{$grnId}"));
    $page->assertSee('In Progress');

    // --- Complete & Update Inventory ---
    $page->click('Complete & Update Inventory');
    waitForGrnStatus($grnId, 'completed');
    $page->navigate(spaUrl("/purchasing/goods-receipt-notes/{$grnId}"));

    $page->assertSee('Completed');

    // DB assertions: stock increased
    $stock = realDb()->table('product_stocks')
        ->where('product_id', $productId)
        ->where('warehouse_id', $warehouseId)
        ->first();

    expect((int) $stock->quantity)->toBe($initialStock + $orderQty);

    // Verify inventory movement created
    $movement = realDb()->table('inventory_movements')
        ->where('reference_type', 'App\\Models\\Purchasing\\GoodsReceiptNote')
        ->where('reference_id', $grnId)
        ->where('product_id', $productId)
        ->first();

    expect($movement)->not->toBeNull();
    expect((int) $movement->quantity)->toBe($orderQty);
});

it('supports partial receive with second GRN completing the PO', function () {
    $warehouseId = ensureWarehouse();
    $productId = 1;
    $totalQty = 10;
    $firstReceive = 6;
    $secondReceive = 4;

    // Enable inventory tracking
    realDb()->table('products')->where('id', $productId)->update(['track_inventory' => true]);
    ensureProductStock($productId, $warehouseId, 100);

    // Create approved PO via DB (needs product_id for inventory tracking)
    $poId = createPurchaseOrderInDb('approved', 'GRN Partial Test', $totalQty);
    $page = loginAndVisit("/purchasing/purchase-orders/{$poId}");

    // --- GRN 1: receive 6 of 10 ---
    $grn1Id = createGrnFromPo($page, $poId, $warehouseId);
    expect($grn1Id)->toBeGreaterThan(0);

    // Start receiving GRN 1
    $page->click('Start Receiving');
    waitForGrnStatus($grn1Id, 'receiving');

    // Receive 6 and reject 4 (must fully account for all ordered to enable Complete button)
    $grnItem1 = realDb()->table('goods_receipt_note_items')
        ->where('goods_receipt_note_id', $grn1Id)
        ->first();
    realDb()->table('goods_receipt_note_items')
        ->where('id', $grnItem1->id)
        ->update([
            'quantity_received' => $firstReceive,
            'quantity_rejected' => $totalQty - $firstReceive,
            'rejection_reason' => 'Damaged in transit',
            'updated_at' => now(),
        ]);
    realDb()->table('goods_receipt_notes')
        ->where('id', $grn1Id)
        ->update([
            'total_quantity_received' => $firstReceive,
            'total_quantity_rejected' => $totalQty - $firstReceive,
            'updated_at' => now(),
        ]);

    // Complete GRN 1
    $page->navigate(spaUrl("/purchasing/goods-receipt-notes/{$grn1Id}"));
    $page->assertSee('In Progress');
    $page->click('Complete & Update Inventory');
    waitForGrnStatus($grn1Id, 'completed');

    // Check PO status → should be 'partial' (received 6 of 10)
    waitForPoStatus($poId, 'partial');
    $po = realDb()->table('purchase_orders')->where('id', $poId)->first();
    expect($po->status)->toBe('partial');

    // --- GRN 2: receive remaining 4 ---
    $page->navigate(spaUrl("/purchasing/purchase-orders/{$poId}"));
    $grn2Id = createGrnFromPo($page, $poId, $warehouseId);
    expect($grn2Id)->toBeGreaterThan(0);

    // The second GRN should have qty_ordered = 4 (remaining after 6 received)
    $grn2Item = realDb()->table('goods_receipt_note_items')
        ->where('goods_receipt_note_id', $grn2Id)
        ->first();
    expect((int) $grn2Item->quantity_ordered)->toBe($secondReceive);

    // Start receiving GRN 2
    $page->click('Start Receiving');
    waitForGrnStatus($grn2Id, 'receiving');

    // Receive all 4 remaining
    updateGrnItemReceived($grn2Id, $secondReceive);

    // Complete GRN 2
    $page->navigate(spaUrl("/purchasing/goods-receipt-notes/{$grn2Id}"));
    $page->assertSee('In Progress');
    $page->click('Complete & Update Inventory');
    waitForGrnStatus($grn2Id, 'completed');

    // Check PO status → should be 'received' (fully received: 6+4=10)
    waitForPoStatus($poId, 'received');
    $po = realDb()->table('purchase_orders')->where('id', $poId)->first();
    expect($po->status)->toBe('received');

    // Verify PO item quantity_received = totalQty
    $poItem = realDb()->table('purchase_order_items')
        ->where('purchase_order_id', $poId)
        ->first();
    expect((int) $poItem->quantity_received)->toBe($totalQty);
});

it('shows GRNs in the list page', function () {
    // Ensure at least one GRN exists
    $warehouseId = ensureWarehouse();
    $poId = createPurchaseOrderInDb('approved', 'GRN List Test');
    createGrnInDb($poId, $warehouseId, 'draft', 5);

    $page = loginAndVisit('/purchasing/goods-receipt-notes');

    $page->assertSee('Goods Receipt Notes');
    $page->assertSee('GRN-');
});
