<?php

declare(strict_types=1);

/**
 * INV-PEST-01: Inventory browser tests.
 *
 * Prerequisites:
 * - Seeded user: admin@example.com / password
 * - At least one product with track_inventory = true
 * - At least one warehouse
 *
 * Tests cover: stock levels, stock adjustments, stock transfers, movements.
 *
 * Shared helpers (realDb, loginAndVisit, spaUrl, etc.) are in tests/Pest.php.
 */

// ---------------------------------------------------------------------------
// Tests (ensureInventorySetup helper is in tests/Pest.php)
// ---------------------------------------------------------------------------

it('shows inventory list with stock levels', function () {
    ensureInventorySetup();

    $page = loginAndVisit('/inventory');

    $page->assertSee('Inventory');

    // Should show at least one product with stock
    $product = realDb()->table('products')->where('id', 1)->first();
    $page->assertSee($product->name);
});

it('shows stock movements page with movement history', function () {
    $page = loginAndVisit('/inventory/movements');

    $page->assertSee('Stock Movements');
});

it('can perform stock adjustment (increase)', function () {
    $setup = ensureInventorySetup();
    $db = realDb();

    // Record current stock
    $beforeStock = (int) $db->table('product_stocks')
        ->where('product_id', $setup['product_id'])
        ->where('warehouse_id', $setup['warehouse_id'])
        ->value('quantity');

    // Navigate to stock adjustment page
    $page = loginAndVisit('/inventory/adjust');

    $page->assertSee('Stock Adjustment');

    // Select type = Stock In (+)
    $page->click('[data-testid="adjust-type-in"]');

    // Fill product select (option label includes SKU + name + stock, use substring)
    $page->click('[data-testid="adjust-product"]');
    usleep(500_000);

    $product = $db->table('products')->where('id', $setup['product_id'])->first();
    $page->click('[role="option"]:has-text("'.$product->sku.'")');

    // Fill warehouse select (option label: "CODE - Name")
    $page->click('[data-testid="adjust-warehouse"]');
    usleep(500_000);

    $warehouse = $db->table('warehouses')
        ->where('id', $setup['warehouse_id'])
        ->first();
    $page->click('[role="option"]:has-text("'.$warehouse->code.'")');
    usleep(500_000);

    // Fill quantity
    $page->fill('[data-testid="adjust-quantity"]', '10');

    // Fill unit cost (required for Stock In)
    $page->fill('[data-testid="adjust-unit-cost"]', '50000');

    // Fill reason
    $page->fill('[data-testid="adjust-notes"]', 'E2E Test Adjustment');

    // Submit
    $page->click('[data-testid="adjust-submit"]');

    // Wait for success feedback
    usleep(1_000_000);

    // Verify stock increased in DB
    $afterStock = (int) $db->table('product_stocks')
        ->where('product_id', $setup['product_id'])
        ->where('warehouse_id', $setup['warehouse_id'])
        ->value('quantity');

    expect($afterStock)->toBe($beforeStock + 10);

    // Verify inventory movement created (Stock In uses type='in')
    $movement = $db->table('inventory_movements')
        ->where('product_id', $setup['product_id'])
        ->where('warehouse_id', $setup['warehouse_id'])
        ->where('type', 'in')
        ->orderByDesc('id')
        ->first();

    expect($movement)->not->toBeNull();
    expect((int) $movement->quantity)->toBe(10);
});

it('can perform stock transfer between warehouses', function () {
    $db = realDb();
    $productId = 1;

    // Ensure we have 2 warehouses
    $warehouses = $db->table('warehouses')
        ->where('is_active', true)
        ->orderBy('id')
        ->limit(2)
        ->get();

    if ($warehouses->count() < 2) {
        // Create a second warehouse
        $db->table('warehouses')->insert([
            'code' => 'WH-TRANSFER',
            'name' => 'Transfer Target Warehouse',
            'is_active' => true,
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $warehouses = $db->table('warehouses')
            ->where('is_active', true)
            ->orderBy('id')
            ->limit(2)
            ->get();
    }

    $sourceId = (int) $warehouses[0]->id;
    $targetId = (int) $warehouses[1]->id;

    // Ensure source has stock
    $sourceStock = $db->table('product_stocks')
        ->where('product_id', $productId)
        ->where('warehouse_id', $sourceId)
        ->first();

    if (! $sourceStock) {
        $db->table('product_stocks')->insert([
            'product_id' => $productId,
            'warehouse_id' => $sourceId,
            'quantity' => 100,
            'reserved_quantity' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $beforeSource = (int) ($sourceStock->quantity ?? 100);

    // Ensure target has a stock record
    $targetStock = $db->table('product_stocks')
        ->where('product_id', $productId)
        ->where('warehouse_id', $targetId)
        ->first();

    if (! $targetStock) {
        $db->table('product_stocks')->insert([
            'product_id' => $productId,
            'warehouse_id' => $targetId,
            'quantity' => 0,
            'reserved_quantity' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $beforeTarget = (int) ($targetStock->quantity ?? 0);

    // Navigate to stock transfer page
    $page = loginAndVisit('/inventory/transfer');

    $page->assertSee('Stock Transfer');

    // Select product (option label: "SKU - Name")
    $page->click('[data-testid="transfer-product"]');
    usleep(500_000);
    $product = $db->table('products')->where('id', $productId)->first();
    $page->click('[role="option"]:has-text("'.$product->sku.'")');

    // Select source warehouse (option label: "CODE - Name")
    $page->click('[data-testid="transfer-source"]');
    usleep(500_000);
    $page->click('[role="option"]:has-text("'.$warehouses[0]->code.'")');

    // Select target warehouse
    $page->click('[data-testid="transfer-target"]');
    usleep(500_000);
    $page->click('[role="option"]:has-text("'.$warehouses[1]->code.'")');

    // Fill quantity
    $page->fill('[data-testid="transfer-quantity"]', '5');

    // Submit
    $page->click('[data-testid="transfer-submit"]');

    usleep(1_000_000);

    // Verify stock changes
    $afterSource = (int) $db->table('product_stocks')
        ->where('product_id', $productId)
        ->where('warehouse_id', $sourceId)
        ->value('quantity');

    $afterTarget = (int) $db->table('product_stocks')
        ->where('product_id', $productId)
        ->where('warehouse_id', $targetId)
        ->value('quantity');

    expect($afterSource)->toBe($beforeSource - 5);
    expect($afterTarget)->toBe($beforeTarget + 5);
});

it('shows stock card for a product', function () {
    ensureInventorySetup();

    $page = loginAndVisit('/inventory/stock-card/1');

    // Stock card should show product info and movement history
    $product = realDb()->table('products')->where('id', 1)->first();
    $page->assertSee($product->name);
});

it('shows movement summary grouped by type', function () {
    $page = loginAndVisit('/inventory/movement-summary');

    $page->assertSee('Movement Summary');
});
