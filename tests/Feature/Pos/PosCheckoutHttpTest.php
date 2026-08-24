<?php

declare(strict_types=1);

use App\Contracts\Inventory\InventoryServiceInterface;
use App\Models\Accounting\FiscalPeriod;
use App\Models\Core\Role;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Pos\PosSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    FiscalPeriod::factory()->current()->create();

    $this->user = authenticatedAdmin();
    $this->warehouse = Warehouse::factory()->create();
    $this->product = Product::factory()->create([
        'selling_price' => 100_00,
        'tax_rate' => 11.00,
        'is_taxable' => true,
        'track_inventory' => true,
        'is_sellable' => true,
        'is_active' => true,
    ]);
    app(InventoryServiceInterface::class)->stockIn(
        $this->product,
        $this->warehouse,
        10,
        50_00,
        'Modal awal tes POS'
    );
});

function openSessionHttp(): int
{
    $response = test()->postJson('/api/v1/pos/sessions', [
        'warehouse_id' => test()->warehouse->id,
        'opening_cash_amount' => 200_000_00,
    ]);

    $response->assertCreated();

    return (int) $response->json('data.id');
}

/**
 * Optional capture for verification (POS_HTTP_CAPTURE_LOG). No-op in normal runs.
 *
 * @param  array<string, mixed>  $requestBody
 * @param  array<string, string>  $headers
 */
function capturePosCheckoutHttp(string $title, string $uri, $response, array $requestBody, array $headers = []): void
{
    $path = getenv('POS_HTTP_CAPTURE_LOG');
    if (! is_string($path) || $path === '') {
        return;
    }

    file_put_contents(
        $path,
        json_encode([
            'title' => $title,
            'method' => 'POST',
            'uri' => $uri,
            'request_body' => $requestBody,
            'request_headers' => $headers,
            'status' => $response->status(),
            'response_body' => $response->json(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n",
        FILE_APPEND
    );
}

describe('POS HTTP', function () {
    it('opens a session, checks out cash, and replays the same idempotency key', function () {
        $sessionId = openSessionHttp();

        $payload = [
            'way' => 'cash',
            'cash_received_amount' => 200_00,
            'lines' => [
                ['product_id' => test()->product->id, 'quantity' => 1],
            ],
        ];

        $headers = ['Idempotency-Key' => 'http-key-1'];
        $uri = "/api/v1/pos/sessions/{$sessionId}/checkout";
        $first = $this->postJson($uri, $payload, $headers);
        capturePosCheckoutHttp('checkout with Idempotency-Key (first)', $uri, $first, $payload, $headers);
        $first->assertCreated()
            ->assertJsonPath('data.payable_amount', 111_00)
            ->assertJsonPath('data.change_amount', 89_00);

        $replay = $this->postJson($uri, $payload, $headers);
        capturePosCheckoutHttp('checkout with same Idempotency-Key (replay)', $uri, $replay, $payload, $headers);
        $replay->assertCreated()
            ->assertJsonPath('data.id', $first->json('data.id'));

        expect(PosSale::query()->count())->toBe(1);
    });

    it('requires an Idempotency-Key header', function () {
        $sessionId = openSessionHttp();

        $payload = [
            'way' => 'qris',
            'lines' => [
                ['product_id' => test()->product->id, 'quantity' => 1],
            ],
        ];
        $uri = "/api/v1/pos/sessions/{$sessionId}/checkout";
        $missing = $this->postJson($uri, $payload);
        capturePosCheckoutHttp('checkout without Idempotency-Key', $uri, $missing, $payload);
        $missing->assertUnprocessable()
            ->assertJsonValidationErrors(['Idempotency-Key']);
    });

    it('returns 404 when the pos pack is off', function () {
        withoutFeatures(['pos']);

        $this->postJson('/api/v1/pos/sessions', [
            'warehouse_id' => $this->warehouse->id,
            'opening_cash_amount' => 0,
        ])->assertNotFound();
    });

    it('resumes the current open session', function () {
        $sessionId = openSessionHttp();

        $this->getJson('/api/v1/pos/sessions/current')
            ->assertOk()
            ->assertJsonPath('data.id', $sessionId);
    });

    it('lets a cashier checkout their own session and forbids another cashier', function () {
        $cashierRole = Role::query()->where('name', Role::CASHIER)->first();

        $siti = User::factory()->create();
        $siti->roles()->attach($cashierRole);
        Sanctum::actingAs($siti);

        $open = $this->postJson('/api/v1/pos/sessions', [
            'warehouse_id' => $this->warehouse->id,
            'opening_cash_amount' => 50_000_00,
        ]);
        $open->assertCreated();
        $sessionId = (int) $open->json('data.id');

        $this->postJson("/api/v1/pos/sessions/{$sessionId}/checkout", [
            'way' => 'qris',
            'lines' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
        ], [
            'Idempotency-Key' => 'siti-1',
        ])->assertCreated();

        $budi = User::factory()->create();
        $budi->roles()->attach($cashierRole);
        Sanctum::actingAs($budi);

        $this->postJson("/api/v1/pos/sessions/{$sessionId}/checkout", [
            'way' => 'qris',
            'lines' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
        ], [
            'Idempotency-Key' => 'budi-1',
        ])->assertForbidden();
    });

    it('holds at most five carts then takes one back', function () {
        $sessionId = openSessionHttp();
        $line = ['lines' => [['product_id' => $this->product->id, 'quantity' => 1]]];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson("/api/v1/pos/sessions/{$sessionId}/holds", $line)->assertCreated();
        }

        $this->postJson("/api/v1/pos/sessions/{$sessionId}/holds", $line)
            ->assertConflict()
            ->assertJsonPath('context.reason', 'Maksimal 5 pesanan ditahan — ambil atau kosongkan dulu.');

        $list = $this->getJson("/api/v1/pos/sessions/{$sessionId}/holds");
        $list->assertOk();
        $holdId = $list->json('data.0.id');

        $this->postJson("/api/v1/pos/sessions/{$sessionId}/holds/{$holdId}/take")
            ->assertOk();
    });

    it('voids a sale on the open session', function () {
        $sessionId = openSessionHttp();
        $sale = $this->postJson("/api/v1/pos/sessions/{$sessionId}/checkout", [
            'way' => 'cash',
            'cash_received_amount' => 111_00,
            'lines' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
        ], [
            'Idempotency-Key' => 'void-http',
        ]);
        $sale->assertCreated();

        $this->postJson("/api/v1/pos/sessions/{$sessionId}/sales/{$sale->json('data.id')}/void", [
            'reason' => 'Salah jumlah',
        ])->assertOk()
            ->assertJsonPath('data.status', 'voided');
    });

    it('closes the session with counted cash', function () {
        $sessionId = openSessionHttp();

        $this->postJson("/api/v1/pos/sessions/{$sessionId}/close", [
            'counted_cash_amount' => 199_000_00,
        ])->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.cash_difference_amount', -100_000);
    });

    it('lets the till refresh session and catalog after checkout', function () {
        $sessionId = openSessionHttp();

        $this->postJson("/api/v1/pos/sessions/{$sessionId}/checkout", [
            'way' => 'cash',
            'cash_received_amount' => 200_00,
            'lines' => [
                ['product_id' => test()->product->id, 'quantity' => 1],
            ],
        ], [
            'Idempotency-Key' => 'refresh-after-pay',
        ])->assertCreated();

        $show = $this->getJson("/api/v1/pos/sessions/{$sessionId}");
        $show->assertOk()
            ->assertJsonMissingPath('data.sales');

        $sales = $this->getJson("/api/v1/pos/sessions/{$sessionId}/sales");
        $sales->assertOk()
            ->assertJsonPath('data.0.status', 'completed')
            ->assertJsonPath('data.0.tenders.0.type', 'cash')
            ->assertJsonPath('data.0.payable_amount', 111_00);

        expect($sales->json('data.0.sold_at'))->toBeString()
            ->and($sales->json('data.0.sold_at'))->toContain('T');

        $this->getJson("/api/v1/pos/sessions/{$sessionId}/catalog")
            ->assertOk()
            ->assertJsonFragment(['id' => test()->product->id]);
    });

    it('returns catalog products with button prices', function () {
        $sessionId = openSessionHttp();

        $catalog = $this->getJson("/api/v1/pos/sessions/{$sessionId}/catalog");
        $catalog->assertOk()
            ->assertJsonFragment(['id' => $this->product->id])
            ->assertJsonFragment(['button_price' => 111_00]);

        expect($catalog->json('data.0'))->toHaveKey('image_url');
    });

    it('omits sellable products that are not in the session warehouse', function () {
        $sessionId = openSessionHttp();
        $otherWarehouse = Warehouse::factory()->create();
        $other = Product::factory()->create([
            'is_sellable' => true,
            'track_inventory' => true,
            'is_active' => true,
        ]);
        app(InventoryServiceInterface::class)->stockIn($other, $otherWarehouse, 5, 1_000);

        $ids = collect($this->getJson("/api/v1/pos/sessions/{$sessionId}/catalog")->json('data'))->pluck('id');

        expect($ids)->toContain($this->product->id)
            ->and($ids)->not->toContain($other->id);
    });

    it('forbids a user without kasir permission from opening a session', function () {
        $viewer = User::factory()->create();
        $role = Role::query()->where('name', Role::VIEWER)->first();
        $viewer->roles()->attach($role);
        Sanctum::actingAs($viewer);

        $this->postJson('/api/v1/pos/sessions', [
            'warehouse_id' => $this->warehouse->id,
            'opening_cash_amount' => 0,
        ])->assertForbidden()
            ->assertJsonPath('message', 'Anda tidak boleh membuka sesi kasir.');
    });

    it('lists till outlets without test warehouses even when the code looks real', function () {
        Warehouse::factory()->testFixture()->create([
            'code' => 'WH-E2E-XXXX',
            'name' => 'E2E junk',
            'is_active' => true,
        ]);
        Warehouse::factory()->create([
            'code' => 'WH-E2E-SHOP',
            'name' => 'Real E2E-named outlet',
            'is_active' => true,
            'is_test' => false,
        ]);

        $codes = collect($this->getJson('/api/v1/pos/outlets')->assertOk()->json('data'))->pluck('code');

        expect($codes)->toContain('WH-E2E-SHOP')
            ->and($codes)->not->toContain('WH-E2E-XXXX');
    });

    it('does not serialize every sale of the shift on current or show', function () {
        $sessionId = openSessionHttp();
        PosSale::factory()->count(25)->create([
            'pos_session_id' => $sessionId,
            'created_by' => $this->user->id,
        ]);

        $current = $this->getJson('/api/v1/pos/sessions/current')->assertOk();
        expect($current->json('data'))->not->toHaveKey('sales');

        $show = $this->getJson("/api/v1/pos/sessions/{$sessionId}")->assertOk();
        expect($show->json('data'))->not->toHaveKey('sales');

        $page = $this->getJson("/api/v1/pos/sessions/{$sessionId}/sales?per_page=20")->assertOk();
        expect($page->json('data'))->toHaveCount(20)
            ->and($page->json('meta.total'))->toBe(25)
            ->and($page->json('meta.per_page'))->toBe(20);
    });

    it('does not resolve catalog images from a SKU that leaves pos/kopitiam', function () {
        $sessionId = openSessionHttp();
        $escapeDir = public_path('pos');
        if (! is_dir($escapeDir)) {
            mkdir($escapeDir, 0755, true);
        }
        $bait = $escapeDir.'/escape.jpg';
        file_put_contents($bait, 'not-an-image');

        $this->product->update(['sku' => '../escape']);

        try {
            $catalog = $this->getJson("/api/v1/pos/sessions/{$sessionId}/catalog")->assertOk();
            $row = collect($catalog->json('data'))->firstWhere('id', $this->product->id);

            expect($row)->not->toBeNull()
                ->and($row['image_url'])->toBeNull();
        } finally {
            if (is_file($bait)) {
                unlink($bait);
            }
        }
    });
});
