<?php

declare(strict_types=1);

use App\Enums\Pos\PosPricingMode;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Core\Role;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Models\Pos\PosSale;
use App\Models\Pos\PosSession;
use App\Models\User;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
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
    $this->air = Product::query()->where('sku', 'KT57-AIR')->firstOrFail();
});

function kopitiamOpenTill(): int
{
    $open = test()->postJson('/api/v1/pos/sessions', [
        'warehouse_id' => test()->warehouse->id,
        'opening_cash_amount' => 200_000,
    ]);
    $open->assertCreated();

    return (int) $open->json('data.id');
}

function kopitiamCheckout(int $sessionId, array $payload, string $key): TestResponse
{
    return test()->postJson("/api/v1/pos/sessions/{$sessionId}/checkout", $payload, [
        'Idempotency-Key' => $key,
    ]);
}

function kopitiamSellHakau(int $sessionId, string $key, int $cashReceived = 25_410): TestResponse
{
    return kopitiamCheckout($sessionId, [
        'way' => 'cash',
        'cash_received_amount' => $cashReceived,
        'lines' => [['product_id' => test()->hakau->id, 'quantity' => 1]],
    ], $key);
}

function kopitiamAssertHakauBill(TestResponse $sale): void
{
    $sale->assertCreated()
        ->assertJsonPath('data.subtotal_amount', 22_000)
        ->assertJsonPath('data.service_amount', 1_100)
        ->assertJsonPath('data.tax_amount', 2_310)
        ->assertJsonPath('data.payable_amount', 25_410);
}

function kopitiamAssertJournalsBalanced(): void
{
    $debits = (int) JournalEntryLine::query()->sum('debit');
    $credits = (int) JournalEntryLine::query()->sum('credit');
    expect($debits)->toBe($credits);
}

function kopitiamNewProductPayload(): array
{
    return [
        'name' => 'Should Not Exist',
        'type' => 'product',
        'unit' => 'pcs',
        'purchase_price' => 1_000,
        'selling_price' => 2_000,
    ];
}

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

describe('Kasir Siti — happy path', function () {
    it('sells Hakau tunai with kembalian and posts a posted balanced journal', function () {
        Sanctum::actingAs($this->kasir);
        $sessionId = kopitiamOpenTill();

        $sale = kopitiamSellHakau($sessionId, 'siti-hakau-change', 100_000);
        kopitiamAssertHakauBill($sale);
        $sale->assertJsonPath('data.cash_received_amount', 100_000)
            ->assertJsonPath('data.change_amount', 74_590)
            ->assertJsonPath('data.status', 'completed');

        $posted = PosSale::query()->findOrFail((int) $sale->json('data.id'));
        expect($posted->journal_entry_id)->not->toBeNull();

        $journal = $this->getJson("/api/v1/journal-entries/{$posted->journal_entry_id}");
        $journal->assertForbidden();

        Sanctum::actingAs($this->owner);
        $this->getJson("/api/v1/journal-entries/{$posted->journal_entry_id}")
            ->assertOk()
            ->assertJsonPath('data.is_posted', true)
            ->assertJsonPath('data.is_balanced', true);

        kopitiamAssertJournalsBalanced();
    });

    it('sells Air Mineral by QRIS without touching garlic stock', function () {
        Sanctum::actingAs($this->kasir);
        $sessionId = kopitiamOpenTill();
        $before = (int) ProductStock::query()
            ->where('product_id', $this->garlic->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->value('quantity');

        kopitiamCheckout($sessionId, [
            'way' => 'qris',
            'lines' => [['product_id' => $this->air->id, 'quantity' => 1]],
        ], 'siti-air-qris')->assertCreated()
            ->assertJsonPath('data.payable_amount', 9_240)
            ->assertJsonPath('data.change_amount', 0);

        expect((int) ProductStock::query()
            ->where('product_id', $this->garlic->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->value('quantity'))->toBe($before);
        kopitiamAssertJournalsBalanced();
    });

    it('holds a cart, takes it back, then voids a completed sale on the same session', function () {
        Sanctum::actingAs($this->kasir);
        $sessionId = kopitiamOpenTill();

        $this->postJson("/api/v1/pos/sessions/{$sessionId}/holds", [
            'lines' => [['product_id' => $this->hakau->id, 'quantity' => 2]],
        ])->assertCreated();

        $holds = $this->getJson("/api/v1/pos/sessions/{$sessionId}/holds")->assertOk();
        $holdId = (int) $holds->json('data.0.id');
        $this->postJson("/api/v1/pos/sessions/{$sessionId}/holds/{$holdId}/take")
            ->assertOk()
            ->assertJsonPath('data.lines.0.product_id', $this->hakau->id);

        $sale = kopitiamSellHakau($sessionId, 'siti-hakau-void');
        kopitiamAssertHakauBill($sale);

        $this->postJson("/api/v1/pos/sessions/{$sessionId}/sales/{$sale->json('data.id')}/void", [
            'reason' => 'Salah barang',
        ])->assertOk()->assertJsonPath('data.status', 'voided');

        kopitiamAssertJournalsBalanced();
    });

    it('closes her own till after a cash sale', function () {
        Sanctum::actingAs($this->kasir);
        $sessionId = kopitiamOpenTill();
        kopitiamSellHakau($sessionId, 'siti-hakau-close');

        $this->postJson("/api/v1/pos/sessions/{$sessionId}/close", [
            'counted_cash_amount' => 225_410,
        ])->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.expected_cash_amount', 225_410);

        $this->getJson('/api/v1/pos/sessions/current')->assertNotFound();
        kopitiamAssertJournalsBalanced();
    });
});

describe('Kasir Siti — must not', function () {
    it('cannot open the document pack, stock, journals, or reports', function () {
        Sanctum::actingAs($this->kasir);

        $this->getJson('/api/v1/invoices')->assertNotFound();
        $this->getJson('/api/v1/quotations')->assertNotFound();
        $this->getJson('/api/v1/purchase-orders')->assertNotFound();
        $this->getJson('/api/v1/bills')->assertForbidden();
        $this->getJson('/api/v1/reports/trial-balance')->assertForbidden();
        $this->getJson('/api/v1/journal-entries')->assertForbidden();
        $cash = Account::query()->where('code', '1-1010')->firstOrFail();
        $expense = Account::query()->where('code', '5-1002')->firstOrFail();
        $this->postJson('/api/v1/journal-entries', [
            'entry_date' => now()->toDateString(),
            'description' => 'Kasir tidak boleh jurnal',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 1_000, 'credit' => 0],
                ['account_id' => $expense->id, 'debit' => 0, 'credit' => 1_000],
            ],
        ])->assertForbidden();
        $this->postJson('/api/v1/products', kopitiamNewProductPayload())->assertForbidden();
        $this->postJson('/api/v1/inventory/stock-in', [
            'product_id' => $this->garlic->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 1,
            'unit_cost' => 11_000,
        ])->assertForbidden();
        $this->postJson('/api/v1/stock-opnames', [
            'warehouse_id' => $this->warehouse->id,
            'opname_date' => now()->toDateString(),
            'name' => 'Kasir opname',
        ])->assertForbidden();
    });

    it('cannot checkout another cashier\'s session', function () {
        Sanctum::actingAs($this->kasir);
        $sessionId = kopitiamOpenTill();

        $budi = User::factory()->create();
        $budi->roles()->attach(Role::query()->where('name', Role::CASHIER)->firstOrFail());
        Sanctum::actingAs($budi);

        kopitiamSellHakau($sessionId, 'budi-on-siti')
            ->assertForbidden()
            ->assertJsonPath('message', 'Sesi kasir milik kasir lain.');
    });
});

describe('Kasir Siti — edges', function () {
    it('rejects short cash against the after-tax total, not the cafe tile', function () {
        Sanctum::actingAs($this->kasir);
        $sessionId = kopitiamOpenTill();

        $short = kopitiamSellHakau($sessionId, 'siti-short-cafe', 22_000);
        $short->assertConflict();
        expect((string) $short->json('message'))->toContain('Uang tunai kurang');
        expect(PosSale::query()->count())->toBe(0);
    });

    it('rejects checkout without an idempotency key and with no lines', function () {
        Sanctum::actingAs($this->kasir);
        $sessionId = kopitiamOpenTill();

        $this->postJson("/api/v1/pos/sessions/{$sessionId}/checkout", [
            'way' => 'qris',
            'lines' => [['product_id' => $this->hakau->id, 'quantity' => 1]],
        ])->assertStatus(422);

        kopitiamCheckout($sessionId, [
            'way' => 'qris',
            'lines' => [],
        ], 'siti-empty-cart')->assertStatus(422);
    });

    it('rejects a sixth held cart', function () {
        Sanctum::actingAs($this->kasir);
        $sessionId = kopitiamOpenTill();
        $line = ['lines' => [['product_id' => $this->hakau->id, 'quantity' => 1]]];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson("/api/v1/pos/sessions/{$sessionId}/holds", $line)->assertCreated();
        }

        $this->postJson("/api/v1/pos/sessions/{$sessionId}/holds", $line)->assertConflict();
    });

    it('rejects overselling tracked pastry', function () {
        Sanctum::actingAs($this->kasir);
        $sessionId = kopitiamOpenTill();
        $onHand = (int) ProductStock::query()
            ->where('product_id', $this->garlic->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->value('quantity');

        $oversell = kopitiamCheckout($sessionId, [
            'way' => 'qris',
            'lines' => [['product_id' => $this->garlic->id, 'quantity' => $onHand + 20]],
        ], 'siti-oversell');

        expect($oversell->status())->toBeIn([409, 422]);
        expect((string) $oversell->json('message'))->not->toBe('');
    });

    it('rejects void after the session is closed', function () {
        Sanctum::actingAs($this->kasir);
        $sessionId = kopitiamOpenTill();
        $sale = kopitiamSellHakau($sessionId, 'siti-void-after-close');
        $this->postJson("/api/v1/pos/sessions/{$sessionId}/close", [
            'counted_cash_amount' => 225_410,
        ])->assertOk();

        $this->postJson("/api/v1/pos/sessions/{$sessionId}/sales/{$sale->json('data.id')}/void", [
            'reason' => 'Terlambat',
        ])->assertConflict();
    });
});

describe('Owner — happy path', function () {
    it('opens a second till while Siti is selling and checks out Hakau at 25410', function () {
        Sanctum::actingAs($this->kasir);
        $sitiSession = kopitiamOpenTill();

        Sanctum::actingAs($this->owner);
        $ownerSession = kopitiamOpenTill();
        expect($ownerSession)->not->toBe($sitiSession)
            ->and(PosSession::query()->where('status', 'open')->count())->toBe(2);

        $sale = kopitiamSellHakau($ownerSession, 'owner-hakau');
        kopitiamAssertHakauBill($sale);

        $this->getJson("/api/v1/pos/sessions/{$sitiSession}")
            ->assertOk()
            ->assertJsonPath('data.opened_by', $this->kasir->id);

        kopitiamAssertJournalsBalanced();
    });

    it('voids Siti\'s open-session sale and closes her forgotten till', function () {
        Sanctum::actingAs($this->kasir);
        $sessionId = kopitiamOpenTill();
        $sale = kopitiamSellHakau($sessionId, 'owner-voids-siti');

        Sanctum::actingAs($this->owner);
        $this->postJson("/api/v1/pos/sessions/{$sessionId}/sales/{$sale->json('data.id')}/void", [
            'reason' => 'Salah barang',
        ])->assertOk()->assertJsonPath('data.status', 'voided');

        $this->postJson("/api/v1/pos/sessions/{$sessionId}/close", [
            'counted_cash_amount' => 200_000,
        ])->assertOk()->assertJsonPath('data.status', 'closed');

        Sanctum::actingAs($this->kasir);
        $this->getJson('/api/v1/pos/sessions/current')->assertNotFound();
        kopitiamAssertJournalsBalanced();
    });

    it('restocks pastry and still cannot mint a Faktur on the pos pack', function () {
        Sanctum::actingAs($this->owner);
        $this->postJson('/api/v1/inventory/stock-in', [
            'product_id' => $this->garlic->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 6,
            'unit_cost' => 11_000,
            'notes' => 'Owner restock',
        ])->assertCreated();

        $this->getJson('/api/v1/invoices')->assertNotFound();
        $this->getJson('/api/v1/quotations')->assertNotFound();
        $this->getJson('/api/v1/purchase-orders')->assertNotFound();
        $this->getJson('/api/v1/reports/trial-balance')
            ->assertOk()
            ->assertJsonPath('data.is_balanced', true);
    });
});

describe('Akuntan Rina — happy path and must not', function () {
    it('reads neraca saldo and the kasir journal after a sale', function () {
        Sanctum::actingAs($this->kasir);
        $sessionId = kopitiamOpenTill();
        $sale = kopitiamSellHakau($sessionId, 'rina-reads-je');
        $posted = PosSale::query()->findOrFail((int) $sale->json('data.id'));

        Sanctum::actingAs($this->akuntan);
        $this->getJson('/api/v1/reports/trial-balance')
            ->assertOk()
            ->assertJsonPath('data.is_balanced', true);
        $this->getJson("/api/v1/journal-entries/{$posted->journal_entry_id}")
            ->assertOk()
            ->assertJsonPath('data.is_posted', true)
            ->assertJsonPath('data.is_balanced', true);
    });

    it('cannot run the till, restock, create products, or approve opname', function () {
        Sanctum::actingAs($this->kasir);
        $sessionId = kopitiamOpenTill();
        $sale = kopitiamSellHakau($sessionId, 'rina-cannot-void');

        Sanctum::actingAs($this->akuntan);
        $this->postJson('/api/v1/pos/sessions', [
            'warehouse_id' => $this->warehouse->id,
            'opening_cash_amount' => 50_000,
        ])->assertForbidden();
        kopitiamSellHakau($sessionId, 'rina-checkout')->assertForbidden();
        $this->postJson("/api/v1/pos/sessions/{$sessionId}/sales/{$sale->json('data.id')}/void", [
            'reason' => 'Salah barang',
        ])->assertForbidden();
        $this->postJson('/api/v1/inventory/stock-in', [
            'product_id' => $this->garlic->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 1,
            'unit_cost' => 11_000,
        ])->assertForbidden();
        $this->postJson('/api/v1/products', kopitiamNewProductPayload())->assertForbidden();
        $this->postJson('/api/v1/stock-opnames', [
            'warehouse_id' => $this->warehouse->id,
            'opname_date' => now()->toDateString(),
            'name' => 'Rina opname',
        ])->assertForbidden();
        $this->getJson('/api/v1/invoices')->assertNotFound();
    });
});

describe('Gudang Dewi — happy path and must not', function () {
    it('creates a pastry SKU and restocks garlic', function () {
        Sanctum::actingAs($this->gudang);
        $this->postJson('/api/v1/products', [
            'name' => 'Test Kitchen Extra',
            'type' => 'product',
            'unit' => 'pcs',
            'purchase_price' => 8_000,
            'selling_price' => 12_000,
        ])->assertCreated();

        $before = (int) ProductStock::query()
            ->where('product_id', $this->garlic->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->value('quantity');

        $this->postJson('/api/v1/inventory/stock-in', [
            'product_id' => $this->garlic->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 4,
            'unit_cost' => 11_000,
            'notes' => 'Roti dari dapur',
        ])->assertCreated();

        expect((int) ProductStock::query()
            ->where('product_id', $this->garlic->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->value('quantity'))->toBe($before + 4);
    });

    it('cannot open the till, void, or read journals', function () {
        Sanctum::actingAs($this->kasir);
        $sessionId = kopitiamOpenTill();
        $sale = kopitiamSellHakau($sessionId, 'dewi-cannot-void');

        Sanctum::actingAs($this->gudang);
        $this->postJson('/api/v1/pos/sessions', [
            'warehouse_id' => $this->warehouse->id,
            'opening_cash_amount' => 50_000,
        ])->assertForbidden();
        kopitiamSellHakau($sessionId, 'dewi-checkout')->assertForbidden();
        $this->postJson("/api/v1/pos/sessions/{$sessionId}/sales/{$sale->json('data.id')}/void", [
            'reason' => 'Salah barang',
        ])->assertForbidden();
        $this->getJson('/api/v1/journal-entries')->assertForbidden();
        $this->getJson('/api/v1/reports/trial-balance')->assertForbidden();
        $this->getJson('/api/v1/invoices')->assertNotFound();
    });
});

describe('Stock opname coffee-shop handoff', function () {
    it('lets Dewi count, forbids Siti and Rina from approving, and lets the owner apply variance', function () {
        $before = (int) ProductStock::query()
            ->where('product_id', $this->garlic->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->value('quantity');

        Sanctum::actingAs($this->gudang);
        $opname = $this->postJson('/api/v1/stock-opnames', [
            'warehouse_id' => $this->warehouse->id,
            'opname_date' => now()->toDateString(),
            'name' => 'Opname pastry pagi',
        ]);
        $opname->assertCreated();
        $opnameId = (int) $opname->json('data.id');

        $this->postJson("/api/v1/stock-opnames/{$opnameId}/items", [
            'product_id' => $this->garlic->id,
        ])->assertCreated();
        $this->postJson("/api/v1/stock-opnames/{$opnameId}/start-counting")->assertOk();

        $show = $this->getJson("/api/v1/stock-opnames/{$opnameId}")->assertOk();
        $itemId = (int) collect($show->json('data.items'))->first()['id'];

        $this->putJson("/api/v1/stock-opnames/{$opnameId}/items/{$itemId}", [
            'counted_quantity' => $before + 2,
        ])->assertOk();
        $this->postJson("/api/v1/stock-opnames/{$opnameId}/submit-review")->assertOk();

        $this->postJson("/api/v1/stock-opnames/{$opnameId}/approve")->assertForbidden();

        Sanctum::actingAs($this->kasir);
        $this->postJson("/api/v1/stock-opnames/{$opnameId}/approve")->assertForbidden();

        Sanctum::actingAs($this->akuntan);
        $this->postJson("/api/v1/stock-opnames/{$opnameId}/approve")->assertForbidden();

        Sanctum::actingAs($this->owner);
        $this->postJson("/api/v1/stock-opnames/{$opnameId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status.value', 'completed');

        expect((int) ProductStock::query()
            ->where('product_id', $this->garlic->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->value('quantity'))->toBe($before + 2);
        kopitiamAssertJournalsBalanced();
    });
});

describe('Checkout survives a stale journal sequence', function () {
    it('still posts Hakau when JE numbers already exist ahead of the counter', function () {
        $prefix = 'JE-'.now()->format('Ym').'-';
        foreach ([67, 72] as $suffix) {
            DB::table('journal_entries')->insert([
                'entry_number' => $prefix.str_pad((string) $suffix, 4, '0', STR_PAD_LEFT),
                'entry_date' => now()->toDateString(),
                'description' => 'stale sequence fixture',
                'is_posted' => false,
                'is_reversed' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        DB::table('document_sequences')->insert([
            'prefix' => $prefix,
            'next_value' => 66,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($this->kasir);
        $sessionId = kopitiamOpenTill();
        $sale = kopitiamSellHakau($sessionId, 'siti-stale-je');
        kopitiamAssertHakauBill($sale);

        $posted = PosSale::query()->findOrFail((int) $sale->json('data.id'));
        $number = JournalEntry::query()->whereKey($posted->journal_entry_id)->value('entry_number');
        expect($number)->toBe($prefix.'0073');
        kopitiamAssertJournalsBalanced();
    });
});
