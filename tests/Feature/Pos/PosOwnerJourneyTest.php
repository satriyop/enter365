<?php

declare(strict_types=1);

use App\Contracts\Inventory\InventoryServiceInterface;
use App\Models\Accounting\FiscalPeriod;
use App\Models\Core\Role;
use App\Models\Inventory\Product;
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

    $this->siti = authenticatedCashier();
    $this->owner = User::factory()->admin()->create();
    $this->warehouse = Warehouse::factory()->create(['is_active' => true]);
    $this->tracked = Product::factory()->create([
        'name' => 'Salt Bread Garlic Cheese',
        'sku' => 'KT57-SB-GARLIC',
        'selling_price' => 28_000,
        'tax_rate' => 11.00,
        'is_taxable' => false,
        'track_inventory' => true,
        'is_sellable' => true,
        'is_active' => true,
    ]);
    app(InventoryServiceInterface::class)->stockIn(
        $this->tracked,
        $this->warehouse,
        10,
        11_000,
        'Modal awal tes owner'
    );
});

function openSitiTill(): int
{
    Sanctum::actingAs(test()->siti);

    $response = test()->postJson('/api/v1/pos/sessions', [
        'warehouse_id' => test()->warehouse->id,
        'opening_cash_amount' => 200_000,
    ]);
    $response->assertCreated();

    return (int) $response->json('data.id');
}

function sitiSellGarlic(int $sessionId, string $key): int
{
    Sanctum::actingAs(test()->siti);

    $response = test()->postJson("/api/v1/pos/sessions/{$sessionId}/checkout", [
        'way' => 'cash',
        'cash_received_amount' => 28_000,
        'lines' => [
            ['product_id' => test()->tracked->id, 'quantity' => 1],
        ],
    ], [
        'Idempotency-Key' => $key,
    ]);
    $response->assertCreated();

    return (int) $response->json('data.id');
}

describe('Owner POS journeys', function () {
    it('lets the owner open a till that is not Siti\'s open session', function () {
        $sitiSessionId = openSitiTill();

        Sanctum::actingAs($this->owner);
        $ownerOpen = $this->postJson('/api/v1/pos/sessions', [
            'warehouse_id' => $this->warehouse->id,
            'opening_cash_amount' => 150_000,
        ]);
        $ownerOpen->assertCreated()
            ->assertJsonPath('data.status', 'open');

        $ownerSessionId = (int) $ownerOpen->json('data.id');
        expect($ownerSessionId)->not->toBe($sitiSessionId)
            ->and($ownerOpen->json('data.opened_by'))->toBe($this->owner->id);

        $this->getJson('/api/v1/pos/sessions/current')
            ->assertOk()
            ->assertJsonPath('data.id', $ownerSessionId);

        expect(PosSession::query()->where('status', 'open')->count())->toBe(2);
    });

    it('blocks another cashier from Siti\'s session but lets the owner inspect it', function () {
        $sessionId = openSitiTill();

        $budi = User::factory()->create();
        $budi->roles()->attach(Role::query()->where('name', Role::CASHIER)->firstOrFail());
        Sanctum::actingAs($budi);

        $this->postJson("/api/v1/pos/sessions/{$sessionId}/checkout", [
            'way' => 'qris',
            'lines' => [
                ['product_id' => $this->tracked->id, 'quantity' => 1],
            ],
        ], [
            'Idempotency-Key' => 'budi-on-siti',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Sesi kasir milik kasir lain.');

        $this->getJson("/api/v1/pos/sessions/{$sessionId}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Sesi kasir milik kasir lain.');

        $this->getJson("/api/v1/pos/sessions/{$sessionId}/catalog")
            ->assertForbidden()
            ->assertJsonPath('message', 'Sesi kasir milik kasir lain.');

        Sanctum::actingAs($this->owner);
        $this->getJson("/api/v1/pos/sessions/{$sessionId}")
            ->assertOk()
            ->assertJsonPath('data.id', $sessionId)
            ->assertJsonPath('data.opened_by', $this->siti->id)
            ->assertJsonPath('data.status', 'open');
    });

    it('lets the owner close Siti\'s forgotten till', function () {
        $sessionId = openSitiTill();

        Sanctum::actingAs($this->owner);
        $this->postJson("/api/v1/pos/sessions/{$sessionId}/close", [
            'counted_cash_amount' => 200_000,
        ])->assertOk()
            ->assertJsonPath('data.status', 'closed');

        Sanctum::actingAs($this->siti);
        $this->getJson('/api/v1/pos/sessions/current')
            ->assertNotFound();
    });

    it('lets the owner read stock and the kasir journal after Siti sells', function () {
        $sessionId = openSitiTill();
        $saleId = sitiSellGarlic($sessionId, 'siti-garlic-for-owner');

        $sale = PosSale::query()->findOrFail($saleId);
        expect($sale->journal_entry_id)->not->toBeNull();

        Sanctum::actingAs($this->owner);

        $product = $this->getJson("/api/v1/products/{$this->tracked->id}");
        $product->assertOk()
            ->assertJsonPath('data.sku', 'KT57-SB-GARLIC');
        expect((int) $product->json('data.current_stock'))->toBe(9);

        $levels = $this->getJson('/api/v1/inventory/stock-levels');
        $levels->assertOk();
        $row = collect($levels->json('data'))->firstWhere('product_id', $this->tracked->id);
        expect($row)->not->toBeNull()
            ->and((int) $row['quantity'])->toBe(9);

        $journal = $this->getJson("/api/v1/journal-entries/{$sale->journal_entry_id}");
        $journal->assertOk()
            ->assertJsonPath('data.is_posted', true)
            ->assertJsonPath('data.is_balanced', true);
        expect((string) $journal->json('data.description'))->toContain('Penjualan kasir')
            ->and((int) $journal->json('data.total_debit'))->toBe((int) $journal->json('data.total_credit'))
            ->and((int) $journal->json('data.total_debit'))->toBe(28_000);
    });
});
