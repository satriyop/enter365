<?php

declare(strict_types=1);

/**
 * INV-PEST-02: Stock opname browser tests.
 *
 * Prerequisites:
 * - Seeded user: admin@example.com / password
 * - At least one product with track_inventory = true
 * - At least one warehouse
 *
 * Tests cover: opname creation, generate items, workflow (start → submit → approve).
 *
 * NOTE: The StockOpnameDetailPage uses confirm()/prompt() for some actions.
 * Playwright auto-dismisses these dialogs, so we use API calls for those
 * transitions (generate items, approve, reject, cancel) and UI clicks for
 * transitions that don't use dialogs (start counting, submit review).
 *
 * Shared helpers (realDb, loginAndVisit, spaUrl, etc.) are in tests/Pest.php.
 */

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Get a Sanctum token for API calls from the browser test context.
 * We use the realDb() connection to query the seeded admin user.
 */
function getApiToken(): string
{
    $db = realDb();
    $user = $db->table('users')->where('email', 'admin@example.com')->first();

    // Create a token directly
    $token = bin2hex(random_bytes(20));
    $db->table('personal_access_tokens')->insert([
        'tokenable_type' => 'App\\Models\\User',
        'tokenable_id' => $user->id,
        'name' => 'e2e-opname-test',
        'token' => hash('sha256', $token),
        'abilities' => '["*"]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $token;
}

/**
 * Call the API with a given token.
 *
 * @param  array<string, mixed>  $data
 * @return object{status: int, body: mixed}
 */
function apiCall(string $method, string $path, string $token, array $data = []): object
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
            'content' => in_array($method, ['POST', 'PUT']) ? json_encode($data) : null,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);

    $response = file_get_contents($url, false, $context);

    // Extract status code from response headers
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

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('shows stock opname list page', function () {
    $page = loginAndVisit('/inventory/opnames');

    $page->assertSee('Stock Opname');
});

it('can create a stock opname via the form', function () {
    $db = realDb();

    // Ensure we have a warehouse
    $warehouse = $db->table('warehouses')->where('is_active', true)->first();
    expect($warehouse)->not->toBeNull();

    // Record the latest opname ID before creating
    $lastId = (int) ($db->table('stock_opnames')->max('id') ?? 0);

    $page = loginAndVisit('/inventory/opnames/new');

    $page->assertSee('New Stock Opname');

    // Wait for async warehouse data to load
    usleep(2_000_000);

    // Select warehouse using the specific option data-testid
    $page->click('[data-testid="opname-warehouse"]');
    usleep(1_000_000);
    // Use the first role=option available
    $page->click('[role="option"] >> nth=0');

    // Fill optional name
    $page->fill('[data-testid="opname-name"]', 'E2E Test Opname');

    // Submit the form
    $page->click('[data-testid="opname-submit"]');

    // Wait for navigation to detail page
    usleep(3_000_000);

    // Verify a new opname was created in DB (by checking ID > lastId)
    $opname = $db->table('stock_opnames')
        ->where('id', '>', $lastId)
        ->orderByDesc('id')
        ->first();

    expect($opname)->not->toBeNull();
    expect($opname->status)->toBe('draft');
});

it('shows stock opname detail page with correct status', function () {
    $db = realDb();

    // Get the most recent opname
    $opname = $db->table('stock_opnames')
        ->orderByDesc('id')
        ->first();

    if (! $opname) {
        // Create one directly in DB
        $warehouse = $db->table('warehouses')->where('is_active', true)->first();
        $db->table('stock_opnames')->insert([
            'opname_number' => 'SO-TEST-'.now()->format('YmdHis'),
            'warehouse_id' => $warehouse->id,
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
        $opname = $db->table('stock_opnames')->orderByDesc('id')->first();
    }

    $page = loginAndVisit('/inventory/opnames/'.$opname->id);

    // Should show opname number
    $page->assertSee($opname->opname_number);
});

it('can run the full opname workflow: generate → start → count → submit → approve', function () {
    $db = realDb();

    // Setup: ensure product 1 has stock
    ensureInventorySetup();

    $warehouse = $db->table('warehouses')->where('is_active', true)->first();
    $token = getApiToken();

    // 1. Create opname via API (faster than UI for workflow test)
    $createResponse = apiCall('POST', '/stock-opnames', $token, [
        'warehouse_id' => $warehouse->id,
        'opname_date' => now()->toDateString(),
        'name' => 'E2E Full Workflow Test',
    ]);

    expect($createResponse->status)->toBe(201);
    $opnameId = $createResponse->body->data->id;

    // 2. Generate items via API (bypasses confirm dialog)
    $genResponse = apiCall('POST', "/stock-opnames/{$opnameId}/generate-items", $token);
    expect($genResponse->status)->toBeIn([200, 201]);

    // Verify items were generated
    $itemCount = $db->table('stock_opname_items')
        ->where('stock_opname_id', $opnameId)
        ->count();
    expect($itemCount)->toBeGreaterThan(0);

    // 3. Start counting via UI (no confirm dialog on this button)
    $page = loginAndVisit('/inventory/opnames/'.$opnameId);
    $page->assertSee('Start Counting');
    $page->click('[data-testid="opname-start-btn"]');
    usleep(2_000_000);

    // Verify status changed in DB
    $opname = $db->table('stock_opnames')->where('id', $opnameId)->first();
    expect($opname->status)->toBe('counting');

    // 4. Update counted quantities via API
    $items = $db->table('stock_opname_items')
        ->where('stock_opname_id', $opnameId)
        ->get();

    foreach ($items as $item) {
        // Count same as system quantity (no variance)
        apiCall('PUT', "/stock-opnames/{$opnameId}/items/{$item->id}", $token, [
            'counted_quantity' => $item->system_quantity,
        ]);
    }

    // 5. Submit for review via UI (no confirm dialog)
    $page = reloadPage($page);
    usleep(1_000_000);
    $page->click('[data-testid="opname-submit-review-btn"]');
    usleep(2_000_000);

    // Verify status changed
    $opname = $db->table('stock_opnames')->where('id', $opnameId)->first();
    expect($opname->status)->toBe('reviewed');

    // 6. Approve via API (bypasses confirm dialog)
    $approveResponse = apiCall('POST', "/stock-opnames/{$opnameId}/approve", $token);
    expect($approveResponse->status)->toBeIn([200, 201]);

    // 7. Verify final state
    $opname = $db->table('stock_opnames')->where('id', $opnameId)->first();
    expect($opname->status)->toBe('completed');
    expect($opname->approved_at)->not->toBeNull();

    // Cleanup token
    $db->table('personal_access_tokens')
        ->where('name', 'e2e-opname-test')
        ->delete();
});

it('shows variance report page for a completed opname', function () {
    $db = realDb();

    // Find a completed opname (from previous test)
    $opname = $db->table('stock_opnames')
        ->where('status', 'completed')
        ->orderByDesc('id')
        ->first();

    if (! $opname) {
        $this->markTestSkipped('No completed opname found');
    }

    $page = loginAndVisit('/inventory/opnames/'.$opname->id.'/variance');

    // The variance report should load
    $page->assertSee('Variance');
});
