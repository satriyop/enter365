<?php

declare(strict_types=1);

/**
 * MFG browser chain: BOM → WO → MR issue → WO complete with stock asserts.
 *
 * Hybrid: seeds BOM/materials/stock via realDb + API create-from-BOM,
 * drives Confirm / Start / Request Materials / Approve / Issue / Complete in the SPA,
 * then asserts raw stock ↓ and finished-goods stock ↑ in the live browser DB.
 *
 * Prerequisites:
 * - SPA_URL (default http://localhost:3000), API_URL (default https://enter365.test)
 * - Seeded admin@example.com / password
 * - FEATURE_PRESET with manufacturing pack enabled
 *
 * Related backlog: tasks/backlog/001-assert-fg-stock-on-wo-complete.md,
 * tasks/backlog/002-browser-mfg-chain.md
 */

// ---------------------------------------------------------------------------
// Helpers (guarded — StockOpnameTest may also define these)
// ---------------------------------------------------------------------------

if (! function_exists('mfgGetApiToken')) {
    function mfgGetApiToken(): string
    {
        $db = realDb();
        $user = $db->table('users')->where('email', 'admin@example.com')->first();

        $token = bin2hex(random_bytes(20));
        $db->table('personal_access_tokens')->insert([
            'tokenable_type' => 'App\\Models\\User',
            'tokenable_id' => $user->id,
            'name' => 'e2e-mfg-chain-test',
            'token' => hash('sha256', $token),
            'abilities' => '["*"]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $token;
    }
}

if (! function_exists('mfgApiCall')) {
    /**
     * @param  array<string, mixed>  $data
     * @return object{status: int, body: mixed}
     */
    function mfgApiCall(string $method, string $path, string $token, array $data = []): object
    {
        $baseUrl = env('API_URL', 'https://enter365.test');
        $url = $baseUrl.'/api/v1'.$path;

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", [
                    'Authorization: Bearer '.$token,
                    'Content-Type: application/json',
                    'Accept: application/json',
                ]),
                'content' => in_array($method, ['POST', 'PUT', 'PATCH'], true) ? json_encode($data) : null,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $response = file_get_contents($url, false, $context);

        $status = 200;
        if (isset($http_response_header[0])) {
            preg_match('/HTTP\/\d+\.?\d*\s+(\d+)/', $http_response_header[0], $matches);
            $status = (int) ($matches[1] ?? 200);
        }

        return (object) [
            'status' => $status,
            'body' => json_decode($response ?: '{}'),
        ];
    }
}

if (! function_exists('waitForWorkOrderStatus')) {
    function waitForWorkOrderStatus(int $woId, string $expectedStatus, int $maxRetries = 40): void
    {
        for ($i = 0; $i < $maxRetries; $i++) {
            $status = realDb()->table('work_orders')->where('id', $woId)->value('status');
            if ($status === $expectedStatus) {
                return;
            }
            usleep(250_000);
        }
    }
}

if (! function_exists('waitForMaterialRequisitionStatus')) {
    function waitForMaterialRequisitionStatus(int $mrId, string $expectedStatus, int $maxRetries = 40): void
    {
        for ($i = 0; $i < $maxRetries; $i++) {
            $status = realDb()->table('material_requisitions')->where('id', $mrId)->value('status');
            if ($status === $expectedStatus) {
                return;
            }
            usleep(250_000);
        }
    }
}

if (! function_exists('mfgEnsureWarehouse')) {
    function mfgEnsureWarehouse(): int
    {
        $db = realDb();
        $warehouse = $db->table('warehouses')->where('is_active', true)->orderBy('id')->first();

        if ($warehouse) {
            return (int) $warehouse->id;
        }

        return (int) $db->table('warehouses')->insertGetId([
            'code' => 'WH-MFG-E2E',
            'name' => 'MFG E2E Warehouse',
            'is_active' => true,
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

if (! function_exists('mfgSeedProduct')) {
    function mfgSeedProduct(string $sku, string $name, int $purchasePrice = 10000): int
    {
        $db = realDb();
        $existing = $db->table('products')->where('sku', $sku)->whereNull('deleted_at')->first();

        if ($existing) {
            $db->table('products')->where('id', $existing->id)->update([
                'name' => $name,
                'track_inventory' => true,
                'is_active' => true,
                'purchase_price' => $purchasePrice,
                'updated_at' => now(),
            ]);

            return (int) $existing->id;
        }

        return (int) $db->table('products')->insertGetId([
            'sku' => $sku,
            'name' => $name,
            'type' => 'product',
            'unit' => 'pcs',
            'purchase_price' => $purchasePrice,
            'selling_price' => $purchasePrice * 2,
            'tax_rate' => 11,
            'is_taxable' => true,
            'track_inventory' => true,
            'min_stock' => 0,
            'current_stock' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

if (! function_exists('mfgEnsureProductStock')) {
    function mfgEnsureProductStock(int $productId, int $warehouseId, int $quantity, int $averageCost = 10000): void
    {
        $db = realDb();
        $existing = $db->table('product_stocks')
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        if ($existing) {
            $db->table('product_stocks')->where('id', $existing->id)->update([
                'quantity' => $quantity,
                'reserved_quantity' => 0,
                'average_cost' => $averageCost,
                'total_value' => $quantity * $averageCost,
                'updated_at' => now(),
            ]);

            return;
        }

        $db->table('product_stocks')->insert([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'quantity' => $quantity,
            'reserved_quantity' => 0,
            'average_cost' => $averageCost,
            'total_value' => $quantity * $averageCost,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

/**
 * Seed active BOM with one material line for the MFG browser chain.
 *
 * @return array{
 *   warehouse_id: int,
 *   finished_product_id: int,
 *   raw_product_id: int,
 *   bom_id: int,
 *   wo_qty: int,
 *   material_per_unit: int,
 *   raw_starting_qty: int
 * }
 */
function seedMfgChainBom(): array
{
    $db = realDb();
    $suffix = substr((string) time(), -6);
    $warehouseId = mfgEnsureWarehouse();
    $woQty = 2;
    $materialPerUnit = 3;
    $rawStartingQty = 50;

    $finishedId = mfgSeedProduct("MFG-FG-{$suffix}", "E2E FG Panel {$suffix}", 100000);
    $rawId = mfgSeedProduct("MFG-RAW-{$suffix}", "E2E Raw Material {$suffix}", 10000);

    mfgEnsureProductStock($rawId, $warehouseId, $rawStartingQty, 10000);
    // FG starts with zero / no row
    $db->table('product_stocks')
        ->where('product_id', $finishedId)
        ->where('warehouse_id', $warehouseId)
        ->delete();

    $adminId = $db->table('users')->where('email', 'admin@example.com')->value('id');

    $bomId = (int) $db->table('boms')->insertGetId([
        'bom_number' => "BOM-E2E-{$suffix}",
        'name' => "E2E BOM Panel {$suffix}",
        'description' => 'Browser chain BOM',
        'product_id' => $finishedId,
        'output_quantity' => 1,
        'output_unit' => 'pcs',
        'total_material_cost' => $materialPerUnit * 10000,
        'total_labor_cost' => 0,
        'total_overhead_cost' => 0,
        'total_cost' => $materialPerUnit * 10000,
        'unit_cost' => $materialPerUnit * 10000,
        'status' => 'active',
        'version' => '1.0',
        'created_by' => $adminId,
        'approved_by' => $adminId,
        'approved_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $db->table('bom_items')->insert([
        'bom_id' => $bomId,
        'type' => 'material',
        'product_id' => $rawId,
        'description' => "E2E Raw Material {$suffix}",
        'quantity' => $materialPerUnit,
        'unit' => 'pcs',
        'unit_cost' => 10000,
        'total_cost' => $materialPerUnit * 10000,
        'waste_percentage' => 0,
        'sort_order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [
        'warehouse_id' => $warehouseId,
        'finished_product_id' => $finishedId,
        'raw_product_id' => $rawId,
        'bom_id' => $bomId,
        'wo_qty' => $woQty,
        'material_per_unit' => $materialPerUnit,
        'raw_starting_qty' => $rawStartingQty,
    ];
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('runs full manufacturing browser chain with stock asserts', function () {
    $token = mfgGetApiToken();

    // Live API feature packs must include work_orders / bom / material_requisitions
    $features = mfgApiCall('GET', '/features', $token);
    $modules = (array) ($features->body->data->modules ?? []);
    foreach (['bom', 'work_orders', 'material_requisitions'] as $feature) {
        if (! ($modules[$feature] ?? false)) {
            test()->markTestSkipped(
                "FEATURE pack '{$feature}' is disabled on live API. Set FEATURE_PRESET=manufacturing (or full/enterprise) in .env."
            );
        }
    }

    $setup = seedMfgChainBom();
    $requiredMaterial = $setup['wo_qty'] * $setup['material_per_unit'];

    // Hybrid: create WO from BOM via API (UI create-from-BOM is multi-step form)
    $createResponse = mfgApiCall(
        'POST',
        "/boms/{$setup['bom_id']}/create-work-order",
        $token,
        [
            'quantity' => $setup['wo_qty'],
            'warehouse_id' => $setup['warehouse_id'],
            'name' => 'E2E MFG Chain WO',
        ]
    );

    expect($createResponse->status)->toBe(201)
        ->and($createResponse->body->data->id ?? null)->not->toBeNull();

    $woId = (int) $createResponse->body->data->id;
    $woNumber = (string) $createResponse->body->data->wo_number;

    $wo = realDb()->table('work_orders')->where('id', $woId)->first();
    expect($wo->status)->toBe('draft')
        ->and((int) $wo->product_id)->toBe($setup['finished_product_id'])
        ->and((int) $wo->bom_id)->toBe($setup['bom_id']);

    // --- UI: Confirm → Start ---
    $page = loginAndVisit("/work-orders/{$woId}");
    $page->assertSee($woNumber);
    $page->script('window.confirm = () => true');

    $page->click('Confirm');
    waitForWorkOrderStatus($woId, 'confirmed');
    $page->navigate(spaUrl("/work-orders/{$woId}"));
    $page->assertSee('Dikonfirmasi'); // DocumentStatus::Confirmed label
    $page->script('window.confirm = () => true');

    $page->click('Start');
    waitForWorkOrderStatus($woId, 'in_progress');
    $page->navigate(spaUrl("/work-orders/{$woId}"));
    $page->assertSee('Dalam Proses'); // DocumentStatus::InProgress label
    $page->script('window.confirm = () => true');

    // --- UI: Request Materials → navigates to MR detail ---
    $page->click('Request Materials');
    $page->assertSee('Material requisition');
    // Wait until SPA routes to MR detail
    for ($i = 0; $i < 40; $i++) {
        if (str_contains($page->url(), '/manufacturing/material-requisitions/')) {
            break;
        }
        usleep(250_000);
    }
    expect($page->url())->toContain('/manufacturing/material-requisitions/');

    $mrUrl = $page->url();
    preg_match('/material-requisitions\/(\d+)/', $mrUrl, $mrMatches);
    $mrId = (int) ($mrMatches[1] ?? 0);
    expect($mrId)->toBeGreaterThan(0);

    $mr = realDb()->table('material_requisitions')->where('id', $mrId)->first();
    expect($mr->status)->toBe('draft')
        ->and((int) $mr->work_order_id)->toBe($woId);

    // --- UI: Approve MR ---
    $page->click('Approve');
    $page->assertSee('Approve Requisition');
    $page->click('[role="dialog"] button >> text=Approve');
    waitForMaterialRequisitionStatus($mrId, 'approved');
    // FE status map uses English labels (not DocumentStatus::label())
    $page = loginAndVisit("/manufacturing/material-requisitions/{$mrId}");
    $page->assertSee('Approved');

    // --- UI: Issue Materials (reduces stock immediately) ---
    $page->click('Issue Materials');
    $page->assertSee('Enter the quantities to issue');
    $page->click('[role="dialog"] button >> text=Max');
    $page->click('[role="dialog"] button >> text=Issue Materials');
    waitForMaterialRequisitionStatus($mrId, 'issued');

    $mrItem = realDb()->table('material_requisition_items')
        ->where('material_requisition_id', $mrId)
        ->where('product_id', $setup['raw_product_id'])
        ->first();
    expect((float) $mrItem->quantity_issued)->toBe((float) $requiredMaterial);

    $rawStockAfterIssue = realDb()->table('product_stocks')
        ->where('product_id', $setup['raw_product_id'])
        ->where('warehouse_id', $setup['warehouse_id'])
        ->first();
    expect((int) $rawStockAfterIssue->quantity)->toBe($setup['raw_starting_qty'] - $requiredMaterial);

    $mrMovement = realDb()->table('inventory_movements')
        ->where('reference_type', 'App\\Models\\Manufacturing\\MaterialRequisition')
        ->where('reference_id', $mrId)
        ->where('product_id', $setup['raw_product_id'])
        ->where('type', 'out')
        ->first();
    expect($mrMovement)->not->toBeNull()
        ->and((int) $mrMovement->quantity)->toBe(-$requiredMaterial);

    // --- UI: Complete WO ---
    $page = loginAndVisit("/work-orders/{$woId}");
    $page->assertSee($woNumber);
    $page->script('window.confirm = () => true');
    $page->click('Complete');
    waitForWorkOrderStatus($woId, 'completed');
    $page = loginAndVisit("/work-orders/{$woId}");
    $page->assertSee('Selesai'); // DocumentStatus::Completed label

    // --- DB: raw stock unchanged on complete (already issued); FG stock increased ---
    $rawStock = realDb()->table('product_stocks')
        ->where('product_id', $setup['raw_product_id'])
        ->where('warehouse_id', $setup['warehouse_id'])
        ->first();

    expect($rawStock)->not->toBeNull()
        ->and((int) $rawStock->quantity)->toBe($setup['raw_starting_qty'] - $requiredMaterial)
        ->and((int) $rawStock->reserved_quantity)->toBe(0);

    $fgStock = realDb()->table('product_stocks')
        ->where('product_id', $setup['finished_product_id'])
        ->where('warehouse_id', $setup['warehouse_id'])
        ->first();

    expect($fgStock)->not->toBeNull()
        ->and((int) $fgStock->quantity)->toBe($setup['wo_qty']);

    $fgMovement = realDb()->table('inventory_movements')
        ->where('reference_type', 'App\\Models\\Manufacturing\\WorkOrder')
        ->where('reference_id', $woId)
        ->where('product_id', $setup['finished_product_id'])
        ->where('type', 'production')
        ->first();

    expect($fgMovement)->not->toBeNull()
        ->and((int) $fgMovement->quantity)->toBe($setup['wo_qty']);

    // No second raw OUT on WO complete when MR already issued full qty
    $rawWoMovement = realDb()->table('inventory_movements')
        ->where('reference_type', 'App\\Models\\Manufacturing\\WorkOrder')
        ->where('reference_id', $woId)
        ->where('product_id', $setup['raw_product_id'])
        ->where('type', 'out')
        ->first();
    expect($rawWoMovement)->toBeNull();
});
