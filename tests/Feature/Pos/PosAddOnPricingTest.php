<?php

declare(strict_types=1);

use App\Contracts\Pos\PosServiceInterface;
use App\Enums\Pos\PosPricingMode;
use App\Enums\Pos\PosTenderType;
use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Accounting\FiscalPeriod;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductCategory;
use App\Models\Inventory\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    FiscalPeriod::factory()->current()->create();

    config([
        'pos.pricing_mode' => PosPricingMode::Add->value,
        'pos.service_rate' => 5,
        'pos.tax_rate' => 10,
        'pos.tax_name' => 'PBJT',
    ]);

    $this->pos = app(PosServiceInterface::class);
    $this->warehouse = Warehouse::factory()->create();
    $dimsum = ProductCategory::factory()->create([
        'code' => 'POS-DIM',
        'name' => 'Dimsum',
        'is_active' => true,
    ]);
    $this->hakau = Product::factory()->create([
        'name' => 'Hakau',
        'selling_price' => 22_000,
        'tax_rate' => 11.00,
        'is_taxable' => false,
        'track_inventory' => false,
        'is_sellable' => true,
        'is_active' => true,
        'category_id' => $dimsum->id,
    ]);
    $this->kopi = Product::factory()->create([
        'name' => 'Kopi O',
        'selling_price' => 15_000,
        'is_taxable' => false,
        'track_inventory' => false,
        'is_sellable' => true,
        'is_active' => true,
    ]);
});

it('snapshots add-on rates onto the open session', function () {
    $session = test()->pos->openSession([
        'warehouse_id' => test()->warehouse->id,
        'opening_cash_amount' => 200_000,
    ]);

    expect($session->pricing_mode)->toBe(PosPricingMode::Add)
        ->and((float) $session->service_rate)->toBe(5.0)
        ->and((float) $session->tax_add_rate)->toBe(10.0)
        ->and($session->tax_add_name)->toBe('PBJT');
});

it('charges Hakau 22000 as 25410 and splits the journal', function () {
    $session = test()->pos->openSession([
        'warehouse_id' => test()->warehouse->id,
        'opening_cash_amount' => 200_000,
    ]);

    $sale = test()->pos->checkout($session, [
        'way' => PosTenderType::Cash->value,
        'cash_received_amount' => 25_410,
        'lines' => [
            ['product_id' => test()->hakau->id, 'quantity' => 1],
        ],
    ], 'hakau-add');

    expect($sale->subtotal_amount)->toBe(22_000)
        ->and($sale->service_amount)->toBe(1_100)
        ->and($sale->tax_amount)->toBe(2_310)
        ->and($sale->ppn_amount)->toBe(0)
        ->and($sale->payable_amount)->toBe(25_410)
        ->and($sale->tenders->first()->amount)->toBe(25_410);

    $credits = JournalEntryLine::query()
        ->where('journal_entry_id', $sale->journal_entry_id)
        ->with('account')
        ->get()
        ->mapWithKeys(fn (JournalEntryLine $line) => [$line->account->code => (int) $line->credit]);

    expect($credits['4-1001'])->toBe(22_000)
        ->and($credits['4-1005'])->toBe(1_100)
        ->and($credits['2-1210'])->toBe(2_310)
        ->and($credits['2-1200'] ?? 0)->toBe(0);

    $debits = (int) JournalEntryLine::query()->where('journal_entry_id', $sale->journal_entry_id)->sum('debit');
    $creditSum = (int) JournalEntryLine::query()->where('journal_entry_id', $sale->journal_entry_id)->sum('credit');
    expect($debits)->toBe($creditSum)->toBe(25_410);
});

it('adds service and PBJT on the cart header, not per SKU', function () {
    $session = test()->pos->openSession([
        'warehouse_id' => test()->warehouse->id,
        'opening_cash_amount' => 200_000,
    ]);

    $sale = test()->pos->checkout($session, [
        'way' => PosTenderType::Qris->value,
        'lines' => [
            ['product_id' => test()->hakau->id, 'quantity' => 1],
            ['product_id' => test()->kopi->id, 'quantity' => 1],
        ],
    ], 'combo-add');

    expect($sale->subtotal_amount)->toBe(37_000)
        ->and($sale->service_amount)->toBe(1_850)
        ->and($sale->tax_amount)->toBe(3_885)
        ->and($sale->payable_amount)->toBe(42_735);
});

it('rejects cash below the after-tax total, not the menu price', function () {
    $session = test()->pos->openSession([
        'warehouse_id' => test()->warehouse->id,
        'opening_cash_amount' => 200_000,
    ]);

    expect(fn () => test()->pos->checkout($session, [
        'way' => PosTenderType::Cash->value,
        'cash_received_amount' => 22_000,
        'lines' => [
            ['product_id' => test()->hakau->id, 'quantity' => 1],
        ],
    ], 'short-cafe'))->toThrow(BusinessRuleException::class, 'Uang tunai kurang');
});

it('exposes cafe button_price on the catalog when the session is add-on', function () {
    $session = test()->pos->openSession([
        'warehouse_id' => test()->warehouse->id,
        'opening_cash_amount' => 200_000,
    ]);

    $response = $this->getJson("/api/v1/pos/sessions/{$session->id}/catalog");
    $response->assertOk();

    $hakau = collect($response->json('data'))->firstWhere('id', test()->hakau->id);
    expect($hakau['button_price'])->toBe(22_000);
});
