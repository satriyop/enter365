<?php

declare(strict_types=1);

use App\Enums\Pos\PosPricingMode;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Core\Role;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Models\Pos\PosSale;
use App\Models\User;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    applyFeaturePreset('pos');
    config([
        'pos.pricing_mode' => PosPricingMode::Add->value,
        'pos.service_rate' => 5,
        'pos.tax_rate' => 10,
        'pos.tax_name' => 'PBJT',
    ]);
    seedDemoFoundation($this);
    seedDemoProfile($this, DemoSeeder::DEMO_POS);

    $this->owner = User::query()->where('email', 'admin@example.com')->firstOrFail();
    $this->kasir = User::query()->where('email', 'siti@kopitiam57.test')->firstOrFail();
    $this->akuntan = User::query()->where('email', 'rina@kopitiam57.test')->firstOrFail();
    $this->gudang = User::query()->where('email', 'dewi@kopitiam57.test')->firstOrFail();
    $this->warehouse = Warehouse::query()->where('code', 'KT57-TOKO')->firstOrFail();
    $this->hakau = Product::query()->where('sku', 'KT57-HAKAU')->firstOrFail();
    $this->garlic = Product::query()->where('sku', 'KT57-SB-GARLIC')->firstOrFail();
});

describe('Kopitiam coffee-shop feature set', function () {
    it('hides document sales and purchasing from the pos preset', function () {
        Sanctum::actingAs($this->owner);

        $this->getJson('/api/v1/features')->assertOk()
            ->assertJsonPath('data.modules.pos', true)
            ->assertJsonPath('data.modules.invoices', false)
            ->assertJsonPath('data.modules.quotations', false)
            ->assertJsonPath('data.modules.purchase_orders', false);

        $this->getJson('/api/v1/invoices')->assertNotFound();
        $this->getJson('/api/v1/quotations')->assertNotFound();
        $this->getJson('/api/v1/purchase-orders')->assertNotFound();
    });

    it('lets Siti kasir sell Hakau at cafe price with service and PBJT, not Faktur', function () {
        Sanctum::actingAs($this->kasir);

        expect($this->kasir->hasRole(Role::CASHIER))->toBeTrue();

        $this->getJson('/api/v1/invoices')->assertNotFound();
        $this->postJson('/api/v1/inventory/stock-in', [
            'product_id' => $this->garlic->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 1,
            'unit_cost' => 11_000,
        ])->assertForbidden();

        $open = $this->postJson('/api/v1/pos/sessions', [
            'warehouse_id' => $this->warehouse->id,
            'opening_cash_amount' => 200_000,
        ]);
        $open->assertCreated();
        $sessionId = (int) $open->json('data.id');

        $sale = $this->postJson("/api/v1/pos/sessions/{$sessionId}/checkout", [
            'way' => 'cash',
            'cash_received_amount' => 25_410,
            'lines' => [
                ['product_id' => $this->hakau->id, 'quantity' => 1],
            ],
        ], ['Idempotency-Key' => 'kopitiam-siti-hakau']);

        $sale->assertCreated()
            ->assertJsonPath('data.subtotal_amount', 22_000)
            ->assertJsonPath('data.service_amount', 1_100)
            ->assertJsonPath('data.tax_amount', 2_310)
            ->assertJsonPath('data.payable_amount', 25_410);
    });

    it('lets the owner supervise the till, restock pastry, and read the PBJT journal', function () {
        Sanctum::actingAs($this->kasir);
        $sessionId = (int) $this->postJson('/api/v1/pos/sessions', [
            'warehouse_id' => $this->warehouse->id,
            'opening_cash_amount' => 200_000,
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/pos/sessions/{$sessionId}/checkout", [
            'way' => 'qris',
            'lines' => [
                ['product_id' => $this->hakau->id, 'quantity' => 1],
            ],
        ], ['Idempotency-Key' => 'kopitiam-owner-watch'])->assertCreated();

        $sale = PosSale::query()->latest('id')->firstOrFail();

        Sanctum::actingAs($this->owner);
        $this->getJson("/api/v1/pos/sessions/{$sessionId}")
            ->assertOk()
            ->assertJsonPath('data.opened_by', $this->kasir->id);

        $journal = $this->getJson("/api/v1/journal-entries/{$sale->journal_entry_id}");
        $journal->assertOk()->assertJsonPath('data.is_balanced', true);

        $credits = JournalEntryLine::query()
            ->where('journal_entry_id', $sale->journal_entry_id)
            ->with('account')
            ->get()
            ->mapWithKeys(fn (JournalEntryLine $line) => [$line->account->code => (int) $line->credit]);

        expect($credits['4-1001'])->toBe(22_000)
            ->and($credits['4-1005'])->toBe(1_100)
            ->and($credits['2-1210'])->toBe(2_310);

        $before = (int) ProductStock::query()
            ->where('product_id', $this->garlic->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->value('quantity');

        $this->postJson('/api/v1/inventory/stock-in', [
            'product_id' => $this->garlic->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 12,
            'unit_cost' => 11_000,
            'notes' => 'Restock pastry pagi',
        ])->assertCreated();

        expect((int) ProductStock::query()
            ->where('product_id', $this->garlic->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->value('quantity'))->toBe($before + 12);

        $this->getJson('/api/v1/products?search=KT57-SB-GARLIC')->assertOk();
        $this->getJson('/api/v1/invoices')->assertNotFound();
    });

    it('lets the akuntan read neraca saldo and the kasir journal, but never checkout', function () {
        Sanctum::actingAs($this->kasir);
        $sessionId = (int) $this->postJson('/api/v1/pos/sessions', [
            'warehouse_id' => $this->warehouse->id,
            'opening_cash_amount' => 200_000,
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/pos/sessions/{$sessionId}/checkout", [
            'way' => 'qris',
            'lines' => [['product_id' => $this->hakau->id, 'quantity' => 1]],
        ], ['Idempotency-Key' => 'kopitiam-akuntan-see'])->assertCreated();
        $sale = PosSale::query()->latest('id')->firstOrFail();

        Sanctum::actingAs($this->akuntan);
        expect($this->akuntan->hasRole(Role::ACCOUNTANT))->toBeTrue();

        $this->postJson('/api/v1/pos/sessions', [
            'warehouse_id' => $this->warehouse->id,
            'opening_cash_amount' => 50_000,
        ])->assertForbidden();

        $this->getJson('/api/v1/reports/trial-balance')
            ->assertOk()
            ->assertJsonPath('data.is_balanced', true);

        $this->getJson("/api/v1/journal-entries/{$sale->journal_entry_id}")
            ->assertOk()
            ->assertJsonPath('data.is_posted', true);
    });

    it('lets gudang restock pastry and forbids the till', function () {
        Sanctum::actingAs($this->gudang);
        expect($this->gudang->hasRole(Role::INVENTORY))->toBeTrue();

        $this->postJson('/api/v1/pos/sessions', [
            'warehouse_id' => $this->warehouse->id,
            'opening_cash_amount' => 50_000,
        ])->assertForbidden();

        $before = (int) ProductStock::query()
            ->where('product_id', $this->garlic->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->value('quantity');

        $this->postJson('/api/v1/inventory/stock-in', [
            'product_id' => $this->garlic->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 8,
            'unit_cost' => 11_000,
            'notes' => 'Roti dari dapur',
        ])->assertCreated();

        expect((int) ProductStock::query()
            ->where('product_id', $this->garlic->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->value('quantity'))->toBe($before + 8);
    });
});
