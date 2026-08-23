<?php

declare(strict_types=1);

use App\Contracts\Inventory\InventoryServiceInterface;
use App\Models\Accounting\FiscalPeriod;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Core\Role;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductCategory;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Models\Pos\PosSale;
use App\Models\Pos\PosSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    FiscalPeriod::factory()->current()->create();

    $this->cashier = authenticatedCashier();
    $this->warehouse = Warehouse::factory()->create(['is_active' => true]);
    $this->tracked = Product::factory()->create([
        'name' => 'Aqua 600ml',
        'selling_price' => 8_000,
        'tax_rate' => 11.00,
        'is_taxable' => false,
        'track_inventory' => true,
        'is_sellable' => true,
        'is_active' => true,
        'barcode' => '899057000001',
    ]);
    $jasaCategory = ProductCategory::factory()->create([
        'code' => 'POS-JSA',
        'name' => 'Jasa',
        'is_active' => true,
    ]);
    $this->jasa = Product::factory()->service()->create([
        'name' => 'Jasa Packing',
        'selling_price' => 2_000,
        'is_taxable' => false,
        'is_sellable' => true,
        'is_active' => true,
        'track_inventory' => false,
        'category_id' => $jasaCategory->id,
    ]);
    app(InventoryServiceInterface::class)->stockIn(
        $this->tracked,
        $this->warehouse,
        10,
        2_500,
        'Modal awal tes kasir'
    );
});

function openKasirSession(): int
{
    $response = test()->postJson('/api/v1/pos/sessions', [
        'warehouse_id' => test()->warehouse->id,
        'opening_cash_amount' => 200_000,
    ]);
    $response->assertCreated();

    return (int) $response->json('data.id');
}

function checkoutKasir(int $sessionId, array $payload, string $key)
{
    return test()->postJson("/api/v1/pos/sessions/{$sessionId}/checkout", $payload, [
        'Idempotency-Key' => $key,
    ]);
}

describe('Kasir full-day journey', function () {
    it('logs in a cashier with pos permissions the till actually reads', function () {
        $plain = User::factory()->create([
            'email' => 'siti.journey@test',
            'password' => bcrypt('password'),
        ]);
        $plain->roles()->attach(Role::query()->where('name', Role::CASHIER)->firstOrFail());

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'siti.journey@test',
            'password' => 'password',
        ]);

        $login->assertOk()
            ->assertJsonPath('user.roles.0.name', Role::CASHIER);

        $names = collect($login->json('user.permissions'))->pluck('name');
        expect($names)->toContain('pos.sale.checkout')
            ->and($names)->toContain('pos.session.open')
            ->and($names)->toContain('pos.session.close')
            ->and($names)->toContain('pos.sale.void')
            ->and($names)->not->toContain('invoices.create');
    });

    it('runs buka → katalog → tunai → qris → simpan/ambil → void → tutup as one kasir', function () {
        $sessionId = openKasirSession();

        $this->getJson('/api/v1/pos/sessions/current')
            ->assertOk()
            ->assertJsonPath('data.id', $sessionId)
            ->assertJsonPath('data.status', 'open');

        $catalog = $this->getJson("/api/v1/pos/sessions/{$sessionId}/catalog");
        $catalog->assertOk();
        $sku = collect($catalog->json('data'))->firstWhere('id', test()->tracked->id);
        expect($sku)->not->toBeNull()
            ->and($sku)->not->toHaveKey('dpp_amount')
            ->and($sku)->not->toHaveKey('ppn_amount')
            ->and($sku['button_price'])->toBe(8_000)
            ->and($sku['quantity'])->toBe(10)
            ->and($sku)->toHaveKey('image_url');

        $jasa = collect($catalog->json('data'))->firstWhere('id', test()->jasa->id);
        expect($jasa)->not->toBeNull()
            ->and($jasa['track_inventory'])->toBeFalse();

        $cash = checkoutKasir($sessionId, [
            'way' => 'cash',
            'cash_received_amount' => 20_000,
            'lines' => [
                ['product_id' => test()->tracked->id, 'quantity' => 1],
            ],
        ], 'journey-cash');
        $cash->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.payable_amount', 8_000)
            ->assertJsonPath('data.change_amount', 12_000)
            ->assertJsonPath('data.tenders.0.type', 'cash');
        $cashSaleId = (int) $cash->json('data.id');

        $replay = checkoutKasir($sessionId, [
            'way' => 'cash',
            'cash_received_amount' => 20_000,
            'lines' => [
                ['product_id' => test()->tracked->id, 'quantity' => 1],
            ],
        ], 'journey-cash');
        $replay->assertCreated()
            ->assertJsonPath('data.id', $cashSaleId);
        expect(PosSale::query()->count())->toBe(1);

        $qris = checkoutKasir($sessionId, [
            'way' => 'qris',
            'lines' => [
                ['product_id' => test()->jasa->id, 'quantity' => 1],
            ],
        ], 'journey-qris');
        $qris->assertCreated()
            ->assertJsonPath('data.payable_amount', 2_000)
            ->assertJsonPath('data.change_amount', 0)
            ->assertJsonPath('data.tenders.0.type', 'qris');

        $hold = $this->postJson("/api/v1/pos/sessions/{$sessionId}/holds", [
            'lines' => [
                ['product_id' => test()->tracked->id, 'quantity' => 2],
            ],
        ]);
        $hold->assertCreated();
        $holdId = (int) $hold->json('data.id');

        $this->getJson("/api/v1/pos/sessions/{$sessionId}/holds")
            ->assertOk()
            ->assertJsonPath('data.0.id', $holdId);

        $taken = $this->postJson("/api/v1/pos/sessions/{$sessionId}/holds/{$holdId}/take");
        $taken->assertOk()
            ->assertJsonPath('data.lines.0.product_id', test()->tracked->id)
            ->assertJsonPath('data.lines.0.quantity', 2);

        $fromHold = checkoutKasir($sessionId, [
            'way' => 'cash',
            'cash_received_amount' => 16_000,
            'lines' => [
                ['product_id' => test()->tracked->id, 'quantity' => 2],
            ],
        ], 'journey-hold-pay');
        $fromHold->assertCreated()
            ->assertJsonPath('data.payable_amount', 16_000);

        $this->postJson("/api/v1/pos/sessions/{$sessionId}/sales/{$cashSaleId}/void", [
            'reason' => 'Salah barang',
        ])->assertOk()
            ->assertJsonPath('data.status', 'voided');

        $stock = ProductStock::query()
            ->where('product_id', test()->tracked->id)
            ->where('warehouse_id', test()->warehouse->id)
            ->value('quantity');
        expect((int) $stock)->toBe(8);

        $show = $this->getJson("/api/v1/pos/sessions/{$sessionId}");
        $show->assertOk();
        $completedCash = collect($show->json('data.sales'))
            ->where('status', 'completed')
            ->flatMap(fn ($sale) => $sale['tenders'] ?? [])
            ->where('type', 'cash')
            ->sum('amount');
        expect($completedCash)->toBe(16_000);

        $close = $this->postJson("/api/v1/pos/sessions/{$sessionId}/close", [
            'counted_cash_amount' => 216_000,
        ]);
        $close->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.expected_cash_amount', 216_000)
            ->assertJsonPath('data.cash_difference_amount', 0);

        $this->getJson('/api/v1/pos/sessions/current')
            ->assertNotFound();

        $this->postJson("/api/v1/pos/sessions/{$sessionId}/sales/{$fromHold->json('data.id')}/void", [
            'reason' => 'Terlambat',
        ])->assertConflict();

        $debits = (int) JournalEntryLine::query()->sum('debit');
        $credits = (int) JournalEntryLine::query()->sum('credit');
        expect($debits)->toBe($credits);

        expect(PosSession::query()->where('status', 'open')->count())->toBe(0);
    });

    it('forbids another cashier from the same open session', function () {
        $sessionId = openKasirSession();

        $other = User::factory()->create();
        $other->roles()->attach(Role::query()->where('name', Role::CASHIER)->firstOrFail());
        Sanctum::actingAs($other);

        $this->postJson("/api/v1/pos/sessions/{$sessionId}/checkout", [
            'way' => 'qris',
            'lines' => [
                ['product_id' => test()->jasa->id, 'quantity' => 1],
            ],
        ], [
            'Idempotency-Key' => 'other-kasir',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Sesi kasir milik kasir lain.');
    });

    it('rejects short cash and oversell with Indonesian 409/422, not a blank body', function () {
        $sessionId = openKasirSession();

        $short = checkoutKasir($sessionId, [
            'way' => 'cash',
            'cash_received_amount' => 1_000,
            'lines' => [
                ['product_id' => test()->tracked->id, 'quantity' => 1],
            ],
        ], 'short-cash');
        $short->assertConflict();
        expect((string) $short->json('message'))->toContain('Uang tunai kurang');

        $oversell = checkoutKasir($sessionId, [
            'way' => 'qris',
            'lines' => [
                ['product_id' => test()->tracked->id, 'quantity' => 99],
            ],
        ], 'oversell');
        expect($oversell->status())->toBeIn([409, 422]);
        expect((string) $oversell->json('message'))->not->toBe('');
    });
});
