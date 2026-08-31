<?php

declare(strict_types=1);

use App\Contracts\Inventory\InventoryServiceInterface;
use App\Enums\Pos\PosSaleStatus;
use App\Enums\Pos\PosSessionStatus;
use App\Models\Accounting\FiscalPeriod;
use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Pos\PosSale;
use App\Models\Pos\PosSession;
use App\Models\Pos\PosSessionHold;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    FiscalPeriod::factory()->current()->create();

    $this->owner = authenticatedAdmin();
    $this->kasir = User::factory()->create();
    $this->kasir->roles()->attach(\App\Models\Core\Role::query()->where('name', \App\Models\Core\Role::CASHIER)->firstOrFail());
    $this->warehouse = Warehouse::factory()->create(['name' => 'Toko Depan', 'is_active' => true]);
    $this->pastry = Product::factory()->create([
        'name' => 'Salt Bread Garlic Cheese',
        'sku' => 'KT57-SB-GARLIC',
        'track_inventory' => true,
        'is_sellable' => true,
        'is_active' => true,
        'selling_price' => 28_000,
    ]);
});

it('lists open tills, holds, today omzet, and low pastry without closing the session', function () {
    $session = PosSession::factory()->create([
        'session_number' => 'PSS-202608-0007',
        'status' => PosSessionStatus::Open,
        'warehouse_id' => $this->warehouse->id,
        'opened_by' => $this->kasir->id,
    ]);
    PosSessionHold::factory()->create([
        'pos_session_id' => $session->id,
        'taken_at' => null,
        'lines' => [['product_id' => $this->pastry->id, 'quantity' => 1]],
    ]);
    PosSale::factory()->create([
        'pos_session_id' => $session->id,
        'status' => PosSaleStatus::Completed,
        'payable_amount' => 25_410,
        'sold_at' => now(),
        'sale_number' => 'POS-202608-0099',
        'created_by' => $this->kasir->id,
    ]);
    PosSale::factory()->voided()->create([
        'pos_session_id' => $session->id,
        'payable_amount' => 99_000,
        'sold_at' => now(),
        'created_by' => $this->kasir->id,
    ]);
    app(InventoryServiceInterface::class)->stockIn(
        $this->pastry,
        $this->warehouse,
        3,
        11_000,
        'Sisa pastry tes'
    );
    JournalEntry::factory()->create(['is_posted' => false]);

    Sanctum::actingAs($this->owner);
    $response = $this->getJson('/api/v1/pos/shop-home');

    $response->assertOk()
        ->assertJsonPath('data.open_hold_count', 1)
        ->assertJsonPath('data.today.sale_count', 1)
        ->assertJsonPath('data.today.omzet_amount', 25_410)
        ->assertJsonPath('data.today.last_sale_number', 'POS-202608-0099')
        ->assertJsonPath('data.draft_journal_count', 1)
        ->assertJsonPath('data.open_sessions.0.session_number', 'PSS-202608-0007')
        ->assertJsonPath('data.open_sessions.0.hold_count', 1)
        ->assertJsonPath('data.open_sessions.0.cashier_name', $this->kasir->name)
        ->assertJsonPath('data.recent.last_sale_number', 'POS-202608-0099')
        ->assertJsonPath('data.low_stock.0.sku', 'KT57-SB-GARLIC')
        ->assertJsonPath('data.low_stock.0.quantity', 3);

    expect($response->json('data.open_sessions.0.opened_at'))->not->toBeEmpty();

    expect($session->fresh()->status)->toBe(PosSessionStatus::Open)
        ->and(PosSessionHold::query()->whereNull('taken_at')->count())->toBe(1);
});

it('is all-caught-up when tills, holds, low stock, and drafts are empty', function () {
    Sanctum::actingAs($this->owner);

    $this->getJson('/api/v1/pos/shop-home')
        ->assertOk()
        ->assertJsonPath('data.open_hold_count', 0)
        ->assertJsonPath('data.today.sale_count', 0)
        ->assertJsonPath('data.today.omzet_amount', 0)
        ->assertJsonPath('data.draft_journal_count', 0)
        ->assertJsonPath('data.open_sessions', [])
        ->assertJsonPath('data.low_stock', []);
});

it('reports yesterday omzet when today is quiet and does not close the till', function () {
    $session = PosSession::factory()->create([
        'status' => PosSessionStatus::Open,
        'warehouse_id' => $this->warehouse->id,
        'opened_by' => $this->kasir->id,
        'opened_at' => now()->subDay(),
    ]);
    PosSale::factory()->create([
        'pos_session_id' => $session->id,
        'status' => PosSaleStatus::Completed,
        'payable_amount' => 40_000,
        'sold_at' => now()->subDay(),
        'sale_number' => 'POS-202608-0001',
        'created_by' => $this->kasir->id,
    ]);

    Sanctum::actingAs($this->owner);
    $this->getJson('/api/v1/pos/shop-home')
        ->assertOk()
        ->assertJsonPath('data.today.omzet_amount', 0)
        ->assertJsonPath('data.recent.yesterday_omzet_amount', 40_000)
        ->assertJsonPath('data.recent.last_sale_number', 'POS-202608-0001')
        ->assertJsonPath('data.open_sessions.0.id', $session->id);

    expect($session->fresh()->status)->toBe(PosSessionStatus::Open);
});

it('forbids the cashier from the owner shop home', function () {
    Sanctum::actingAs($this->kasir);

    $this->getJson('/api/v1/pos/shop-home')
        ->assertForbidden();
});
