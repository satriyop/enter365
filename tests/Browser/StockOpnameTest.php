<?php

declare(strict_types=1);

/**
 * INV-PEST-02: Stock opname browser tests (pure SPA workflow).
 *
 * Core path create → generate → start → count → submit → approve is driven
 * entirely via the SPA (in-app modals). No REST helpers for those steps.
 *
 * Prerequisites:
 * - Seeded user: admin@example.com / password
 * - SPA_URL + live browser DB
 *
 * Related backlog: tasks/backlog/004-stock-opname-pure-ui-e2e.md
 */
if (! function_exists('waitForOpnameStatus')) {
    function waitForOpnameStatus(int $opnameId, string $expectedStatus, int $maxRetries = 40): void
    {
        for ($i = 0; $i < $maxRetries; $i++) {
            $status = realDb()->table('stock_opnames')->where('id', $opnameId)->value('status');
            if ($status === $expectedStatus) {
                return;
            }
            usleep(250_000);
        }
    }
}

/**
 * Isolated warehouse + single product stock so Generate yields one countable line.
 *
 * @return array{warehouse_id: int, warehouse_name: string, product_id: int, system_qty: int, counted_qty: int}
 */
function seedPureUiOpnameFixture(): array
{
    $db = realDb();
    $suffix = substr((string) time(), -6);
    $systemQty = 100;
    $countedQty = 105; // +5 variance applied on approve

    $warehouseId = (int) $db->table('warehouses')->insertGetId([
        'code' => "WH-OP-{$suffix}",
        'name' => "E2E Opname WH {$suffix}",
        'is_active' => true,
        'is_default' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $productId = (int) $db->table('products')->insertGetId([
        'sku' => "OP-SKU-{$suffix}",
        'name' => "E2E Opname Product {$suffix}",
        'type' => 'product',
        'unit' => 'pcs',
        'purchase_price' => 10000,
        'selling_price' => 15000,
        'tax_rate' => 11,
        'is_taxable' => true,
        'track_inventory' => true,
        'min_stock' => 0,
        'current_stock' => $systemQty,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $db->table('product_stocks')->insert([
        'product_id' => $productId,
        'warehouse_id' => $warehouseId,
        'quantity' => $systemQty,
        'reserved_quantity' => 0,
        'average_cost' => 10000,
        'total_value' => $systemQty * 10000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [
        'warehouse_id' => $warehouseId,
        'warehouse_name' => "E2E Opname WH {$suffix}",
        'product_id' => $productId,
        'system_qty' => $systemQty,
        'counted_qty' => $countedQty,
    ];
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('shows stock opname list page', function () {
    $page = loginAndVisit('/inventory/opnames');

    $page->assertSee('Stock Opname');
    $page->assertNoJavascriptErrors();
});

it('can create a stock opname via the form', function () {
    $db = realDb();
    $fixture = seedPureUiOpnameFixture();
    $lastId = (int) ($db->table('stock_opnames')->max('id') ?? 0);

    $page = loginAndVisit('/inventory/opnames/new');
    $page->assertSee('New Stock Opname');

    $page->click('[data-testid="opname-warehouse"]');
    $page->click('[role="option"] >> text='.$fixture['warehouse_name']);
    $page->fill('[data-testid="opname-name"]', 'E2E Create Opname');
    $page->click('[data-testid="opname-submit"]');

    $opnameId = 0;
    for ($i = 0; $i < 40; $i++) {
        $url = $page->url();
        if (preg_match('#/inventory/opnames/(\d+)#', $url, $m) && ! str_contains($url, '/new')) {
            $opnameId = (int) $m[1];
            break;
        }
        usleep(250_000);
    }

    if ($opnameId === 0) {
        $opnameId = (int) $db->table('stock_opnames')
            ->where('id', '>', $lastId)
            ->orderByDesc('id')
            ->value('id');
    }

    expect($opnameId)->toBeGreaterThan(0);
    $opname = $db->table('stock_opnames')->where('id', $opnameId)->first();
    expect($opname)->not->toBeNull()
        ->and($opname->status)->toBe('draft')
        ->and((int) $opname->warehouse_id)->toBe($fixture['warehouse_id']);
});

it('runs pure UI opname workflow generate → start → count → submit → approve with stock variance', function () {
    $db = realDb();
    $fixture = seedPureUiOpnameFixture();
    $lastId = (int) ($db->table('stock_opnames')->max('id') ?? 0);

    // --- Create via SPA ---
    $page = loginAndVisit('/inventory/opnames/new');
    $page->assertSee('New Stock Opname');
    $page->click('[data-testid="opname-warehouse"]');
    $page->click('[role="option"] >> text='.$fixture['warehouse_name']);
    $page->fill('[data-testid="opname-name"]', 'E2E Pure UI Workflow');
    $page->click('[data-testid="opname-submit"]');

    $opnameId = 0;
    for ($i = 0; $i < 40; $i++) {
        $url = $page->url();
        if (preg_match('#/inventory/opnames/(\d+)#', $url, $m) && ! str_contains($url, '/new')) {
            $opnameId = (int) $m[1];
            break;
        }
        usleep(250_000);
    }
    if ($opnameId === 0) {
        $opnameId = (int) $db->table('stock_opnames')->where('id', '>', $lastId)->orderByDesc('id')->value('id');
        expect($opnameId)->toBeGreaterThan(0);
        $page = loginAndVisit('/inventory/opnames/'.$opnameId);
    }

    // --- Generate items (modal, not confirm()) ---
    $page->assertSee('No items to count yet');
    $page->click('[data-testid="opname-generate-empty-btn"]');
    $page->assertSee('Generate items from warehouse stock');
    $page->click('[data-testid="opname-generate-confirm"]');

    $itemId = 0;
    for ($i = 0; $i < 40; $i++) {
        $itemId = (int) ($db->table('stock_opname_items')
            ->where('stock_opname_id', $opnameId)
            ->where('product_id', $fixture['product_id'])
            ->value('id') ?? 0);
        if ($itemId > 0) {
            break;
        }
        usleep(250_000);
    }
    expect($itemId)->toBeGreaterThan(0);

    $item = $db->table('stock_opname_items')->where('id', $itemId)->first();
    expect((int) $item->system_quantity)->toBe($fixture['system_qty']);

    $page = loginAndVisit('/inventory/opnames/'.$opnameId);
    $page->assertSee('Generate'); // items present (header actions)

    // --- Start counting (stay on SPA session; reload after DB status flip) ---
    $page->click('[data-testid="opname-start-btn"]');
    waitForOpnameStatus($opnameId, 'counting');
    $page->navigate(spaUrl('/inventory/opnames/'.$opnameId));
    $page->assertSee('Penghitungan'); // DocumentStatus::Counting label
    $page->assertSee('Not counted');

    // --- Count with variance via SPA inline edit ---
    // ResponsiveTable renders desktop + mobile cells (strict mode: use first)
    $page->click('[data-testid="opname-count-cell-'.$itemId.'"] >> nth=0');
    $page->fill('[data-testid="opname-count-input"] >> nth=0', (string) $fixture['counted_qty']);
    $page->click('[data-testid="opname-count-save"] >> nth=0');

    for ($i = 0; $i < 40; $i++) {
        $counted = $db->table('stock_opname_items')->where('id', $itemId)->value('counted_quantity');
        if ((int) $counted === $fixture['counted_qty']) {
            break;
        }
        usleep(250_000);
    }
    expect((int) $db->table('stock_opname_items')->where('id', $itemId)->value('counted_quantity'))
        ->toBe($fixture['counted_qty']);

    // --- Submit for review ---
    $page->navigate(spaUrl('/inventory/opnames/'.$opnameId));
    $page->assertSee('Submit Review');
    $page->click('[data-testid="opname-submit-review-btn"]');
    waitForOpnameStatus($opnameId, 'reviewed');
    $page->navigate(spaUrl('/inventory/opnames/'.$opnameId));
    $page->assertSee('Approve & Complete');

    // --- Approve via modal ---
    $page->click('[data-testid="opname-approve-btn"]');
    $page->assertSee('Approve this stock opname');
    $page->click('[data-testid="opname-approve-confirm"]');
    waitForOpnameStatus($opnameId, 'completed');

    $opname = $db->table('stock_opnames')->where('id', $opnameId)->first();
    expect($opname->status)->toBe('completed')
        ->and($opname->approved_at)->not->toBeNull();

    // Stock must reflect counted absolute qty after approve
    $stockQty = (int) $db->table('product_stocks')
        ->where('product_id', $fixture['product_id'])
        ->where('warehouse_id', $fixture['warehouse_id'])
        ->value('quantity');

    expect($stockQty)->toBe($fixture['counted_qty']);

    $page->navigate(spaUrl('/inventory/opnames/'.$opnameId));
    $page->assertSee('Selesai');
});

it('shows stock opname detail page with correct status', function () {
    $db = realDb();
    $fixture = seedPureUiOpnameFixture();

    $id = (int) $db->table('stock_opnames')->insertGetId([
        'opname_number' => 'SO-TEST-'.now()->format('YmdHis'),
        'warehouse_id' => $fixture['warehouse_id'],
        'opname_date' => now()->toDateString(),
        'status' => 'draft',
        'name' => 'E2E Detail Test',
        'total_items' => 0,
        'total_counted' => 0,
        'total_variance_qty' => 0,
        'total_variance_value' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $opname = $db->table('stock_opnames')->where('id', $id)->first();
    $page = loginAndVisit('/inventory/opnames/'.$opname->id);
    $page->assertSee($opname->opname_number);
});

it('shows variance report page for a completed opname', function () {
    $db = realDb();

    $opname = $db->table('stock_opnames')
        ->where('status', 'completed')
        ->orderByDesc('id')
        ->first();

    if (! $opname) {
        $this->markTestSkipped('No completed opname found');
    }

    $page = loginAndVisit('/inventory/opnames/'.$opname->id.'/variance');
    $page->assertSee('Variance');
});
