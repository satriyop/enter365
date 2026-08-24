<?php

declare(strict_types=1);

use App\Contracts\Inventory\InventoryServiceInterface;
use App\Contracts\Pos\PosServiceInterface;
use App\Domain\Accounting\FiscalPeriods\Enums\FiscalPeriodStatus;
use App\Enums\Pos\PosSaleStatus;
use App\Enums\Pos\PosSessionStatus;
use App\Enums\Pos\PosTenderType;
use App\Exceptions\Domain\BusinessRuleException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingPolicy;
use App\Models\Accounting\FiscalPeriod;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Inventory\InventoryCostLayer;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Models\Pos\PosSale;
use App\Models\Pos\PosSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Once;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    FiscalPeriod::factory()->current()->create();

    $this->pos = app(PosServiceInterface::class);
    $this->inventory = app(InventoryServiceInterface::class);
    $this->warehouse = Warehouse::factory()->create();

    $this->product = Product::factory()->create([
        'name' => 'Aqua 600ml',
        'selling_price' => 100_00,
        'tax_rate' => 11.00,
        'is_taxable' => true,
        'track_inventory' => true,
        'is_sellable' => true,
        'is_active' => true,
    ]);
    $this->service = Product::factory()->service()->create([
        'name' => 'Jasa Packing',
        'selling_price' => 20_00,
        'is_taxable' => false,
        'is_sellable' => true,
        'is_active' => true,
    ]);

    $this->inventory->stockIn($this->product, $this->warehouse, 10, 50_00, 'Modal awal tes POS');
});

function openTill(): PosSession
{
    return test()->pos->openSession([
        'warehouse_id' => test()->warehouse->id,
        'opening_cash_amount' => 200_000_00,
    ]);
}

describe('PosService session', function () {
    it('reuses an already open session for the same kasir', function () {
        $first = openTill();
        $second = openTill();

        expect($second->id)->toBe($first->id)
            ->and(\App\Models\Pos\PosSession::query()->count())->toBe(1);
    });

    it('refuses to reopen the same kasir at a different warehouse', function () {
        $first = openTill();
        $otherWarehouse = Warehouse::factory()->create();

        expect(fn () => test()->pos->openSession([
            'warehouse_id' => $otherWarehouse->id,
            'opening_cash_amount' => 50_000_00,
        ]))->toThrow(BusinessRuleException::class, 'gudang lain');

        expect(\App\Models\Pos\PosSession::query()->where('status', PosSessionStatus::Open)->count())->toBe(1)
            ->and($first->fresh()->warehouse_id)->toBe(test()->warehouse->id);
    });

    it('opens a session with cash and qris accounts snapshotted', function () {
        $session = openTill();

        expect($session->status)->toBe(PosSessionStatus::Open)
            ->and($session->session_number)->toStartWith('PSS-')
            ->and($session->opening_cash_amount)->toBe(200_000_00)
            ->and($session->cash_account_id)->not->toBeNull()
            ->and($session->qris_account_id)->not->toBeNull()
            ->and($session->warehouse_id)->toBe(test()->warehouse->id);
    });

    it('refuses to open when the fiscal period is locked', function () {
        FiscalPeriod::query()->delete();
        FiscalPeriod::factory()->current()->locked()->create();

        expect(fn () => openTill())
            ->toThrow(BusinessRuleException::class, 'sedang dikunci');
    });

    it('refuses to open when the fiscal period is closed', function () {
        FiscalPeriod::query()->delete();
        FiscalPeriod::factory()->current()->closed()->create();

        expect(fn () => openTill())
            ->toThrow(BusinessRuleException::class);
    });

    it('closes with expected cash and discards holds', function () {
        $session = openTill();
        test()->pos->hold($session, [
            ['product_id' => test()->product->id, 'quantity' => 1],
        ]);

        $closed = test()->pos->closeSession($session, [
            'counted_cash_amount' => 200_000_00,
        ]);

        expect($closed->status)->toBe(PosSessionStatus::Closed)
            ->and($closed->expected_cash_amount)->toBe(200_000_00)
            ->and($closed->counted_cash_amount)->toBe(200_000_00)
            ->and($closed->cash_difference_amount)->toBe(0)
            ->and($closed->holds()->count())->toBe(0);

        expect(JournalEntry::query()
            ->where('source_type', PosSession::class)
            ->where('source_id', $closed->id)
            ->exists())->toBeFalse();
    });

    it('journals a cash shortage so GL kas matches the counted drawer', function () {
        $session = openTill();

        $closed = test()->pos->closeSession($session, [
            'counted_cash_amount' => 150_000_00,
        ]);

        expect($closed->cash_difference_amount)->toBe(-50_000_00);

        $entry = JournalEntry::query()
            ->where('source_type', PosSession::class)
            ->where('source_id', $closed->id)
            ->with('lines.account')
            ->first();

        expect($entry)->not->toBeNull()
            ->and($entry->is_posted)->toBeTrue();

        $cashLine = $entry->lines->firstWhere('account_id', $closed->cash_account_id);
        $overShort = $entry->lines->first(fn ($line) => $line->account?->code === '5-2910');

        expect($cashLine->credit)->toBe(50_000_00)
            ->and($cashLine->debit)->toBe(0)
            ->and($overShort->debit)->toBe(50_000_00)
            ->and($overShort->credit)->toBe(0);
    });
});

describe('PosService checkout', function () {
    it('posts cash sale with stock out, revenue journal, and cogs journal', function () {
        $session = openTill();

        $sale = test()->pos->checkout($session, [
            'way' => PosTenderType::Cash->value,
            'cash_received_amount' => 200_00,
            'lines' => [
                ['product_id' => test()->product->id, 'quantity' => 1],
            ],
        ], 'pos_key_cash_1');

        expect($sale->status)->toBe(PosSaleStatus::Completed)
            ->and($sale->sale_number)->toStartWith('POS-')
            ->and($sale->payable_amount)->toBe(111_00)
            ->and($sale->dpp_amount)->toBe(100_00)
            ->and($sale->ppn_amount)->toBe(11_00)
            ->and($sale->cash_received_amount)->toBe(200_00)
            ->and($sale->change_amount)->toBe(89_00)
            ->and($sale->journal_entry_id)->not->toBeNull()
            ->and($sale->cogs_journal_entry_id)->not->toBeNull()
            ->and($sale->tenders)->toHaveCount(1)
            ->and($sale->tenders->first()->type)->toBe(PosTenderType::Cash)
            ->and($sale->tenders->first()->amount)->toBe(111_00);

        $stock = ProductStock::query()
            ->where('product_id', test()->product->id)
            ->where('warehouse_id', test()->warehouse->id)
            ->first();
        expect($stock->quantity)->toBe(9);

        $revenueDebit = JournalEntryLine::query()
            ->where('journal_entry_id', $sale->journal_entry_id)
            ->sum('debit');
        $revenueCredit = JournalEntryLine::query()
            ->where('journal_entry_id', $sale->journal_entry_id)
            ->sum('credit');
        expect((int) $revenueDebit)->toBe((int) $revenueCredit);

        $cogsDebit = JournalEntryLine::query()
            ->where('journal_entry_id', $sale->cogs_journal_entry_id)
            ->sum('debit');
        expect((int) $cogsDebit)->toBe(50_00);

        expect(test()->pos->expectedCash($session->fresh()))->toBe(200_000_00 + 111_00);
    });

    it('posts qris without changing expected cash and without change', function () {
        $session = openTill();

        $sale = test()->pos->checkout($session, [
            'way' => PosTenderType::Qris->value,
            'lines' => [
                ['product_id' => test()->product->id, 'quantity' => 1],
            ],
        ], 'pos_key_qris_1');

        $qrisAccount = Account::query()->findOrFail($session->qris_account_id);
        $debited = JournalEntryLine::query()
            ->where('journal_entry_id', $sale->journal_entry_id)
            ->where('debit', '>', 0)
            ->with('account')
            ->get()
            ->pluck('account.code');

        expect($sale->change_amount)->toBe(0)
            ->and($sale->tenders->first()->type)->toBe(PosTenderType::Qris)
            ->and(test()->pos->expectedCash($session->fresh()))->toBe(200_000_00)
            ->and($qrisAccount->code)->toBe('1-1112')
            ->and($debited->all())->toContain('1-1112')
            ->and($debited->all())->not->toContain('1-1002');
    });

    it('does not move stock or post cogs for untracked jasa', function () {
        $session = openTill();

        $sale = test()->pos->checkout($session, [
            'way' => PosTenderType::Cash->value,
            'cash_received_amount' => 20_00,
            'lines' => [
                ['product_id' => test()->service->id, 'quantity' => 1],
            ],
        ], 'pos_key_jasa_1');

        expect($sale->payable_amount)->toBe(20_00)
            ->and($sale->ppn_amount)->toBe(0)
            ->and($sale->cogs_journal_entry_id)->toBeNull()
            ->and($sale->items->first()->track_inventory)->toBeFalse()
            ->and($sale->items->first()->inventory_movement_id)->toBeNull();
    });

    it('replays the same sale when the idempotency key is reused', function () {
        $session = openTill();
        $payload = [
            'way' => PosTenderType::Cash->value,
            'cash_received_amount' => 111_00,
            'lines' => [
                ['product_id' => test()->product->id, 'quantity' => 1],
            ],
        ];

        $first = test()->pos->checkout($session, $payload, 'same-key');
        $second = test()->pos->checkout($session->fresh(), $payload, 'same-key');

        expect($second->id)->toBe($first->id)
            ->and(PosSale::query()->count())->toBe(1);
    });

    it('rejects checkout when cash received is short', function () {
        $session = openTill();

        expect(fn () => test()->pos->checkout($session, [
            'way' => PosTenderType::Cash->value,
            'cash_received_amount' => 100_00,
            'lines' => [
                ['product_id' => test()->product->id, 'quantity' => 1],
            ],
        ], 'short-cash'))->toThrow(BusinessRuleException::class, 'Uang tunai kurang');
    });

    it('rejects checkout below available stock', function () {
        $session = openTill();

        expect(fn () => test()->pos->checkout($session, [
            'way' => PosTenderType::Cash->value,
            'cash_received_amount' => 2_000_00,
            'lines' => [
                ['product_id' => test()->product->id, 'quantity' => 11],
            ],
        ], 'over-stock'))->toThrow(InsufficientStockException::class);
    });

    it('rejects an empty idempotency key', function () {
        $session = openTill();

        expect(fn () => test()->pos->checkout($session, [
            'way' => PosTenderType::Qris->value,
            'lines' => [
                ['product_id' => test()->product->id, 'quantity' => 1],
            ],
        ], ''))->toThrow(BusinessRuleException::class, 'Idempotency-Key wajib diisi');
    });

    it('rejects checkout of an inactive product', function () {
        $session = openTill();
        test()->product->update(['is_active' => false]);

        expect(fn () => test()->pos->checkout($session, [
            'way' => PosTenderType::Qris->value,
            'lines' => [
                ['product_id' => test()->product->id, 'quantity' => 1],
            ],
        ], 'inactive-sku'))->toThrow(BusinessRuleException::class, 'tidak bisa dijual');
    });

    it('rejects checkout of a zero-priced tracked product without moving stock', function () {
        $session = openTill();
        $free = Product::factory()->create([
            'name' => 'Sample gratis',
            'selling_price' => 0,
            'is_taxable' => false,
            'track_inventory' => true,
            'is_sellable' => true,
            'is_active' => true,
        ]);
        test()->inventory->stockIn($free, test()->warehouse, 5, 50_00, 'Modal sample');

        expect(fn () => test()->pos->checkout($session, [
            'way' => PosTenderType::Cash->value,
            'cash_received_amount' => 0,
            'lines' => [
                ['product_id' => $free->id, 'quantity' => 1],
            ],
        ], 'zero-price'))->toThrow(BusinessRuleException::class, 'Sample gratis');

        $stock = ProductStock::query()
            ->where('product_id', $free->id)
            ->where('warehouse_id', test()->warehouse->id)
            ->first();

        expect((int) $stock->quantity)->toBe(5)
            ->and(InventoryMovement::query()->where('product_id', $free->id)->where('type', InventoryMovement::TYPE_OUT)->count())->toBe(0);
    });
});

describe('PosService void', function () {
    it('voids a completed sale in the same session and restores stock', function () {
        $session = openTill();
        $sale = test()->pos->checkout($session, [
            'way' => PosTenderType::Cash->value,
            'cash_received_amount' => 111_00,
            'lines' => [
                ['product_id' => test()->product->id, 'quantity' => 1],
            ],
        ], 'to-void');

        $voided = test()->pos->voidSale($session->fresh(), $sale, 'Salah barang');

        expect($voided->status)->toBe(PosSaleStatus::Voided)
            ->and($voided->void_reason)->toBe('Salah barang');

        $stock = ProductStock::query()
            ->where('product_id', test()->product->id)
            ->where('warehouse_id', test()->warehouse->id)
            ->first();
        expect($stock->quantity)->toBe(10)
            ->and(test()->pos->expectedCash($session->fresh()))->toBe(200_000_00);
    });

    it('voids an uneven FIFO sale without leaking a sen of inventory value', function () {
        AccountingPolicy::query()->update(['costing_method' => 'fifo']);
        Once::flush();

        $product = Product::factory()->create([
            'name' => 'Kopi FIFO',
            'selling_price' => 100_00,
            'tax_rate' => 11.00,
            'is_taxable' => true,
            'track_inventory' => true,
            'is_sellable' => true,
            'is_active' => true,
        ]);
        test()->inventory->stockIn($product, test()->warehouse, 2, 333);
        test()->inventory->stockIn($product, test()->warehouse, 1, 334);

        $session = openTill();
        $sale = test()->pos->checkout($session, [
            'way' => PosTenderType::Cash->value,
            'cash_received_amount' => 333_00,
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
        ], 'fifo-uneven-void');

        $outMovement = $sale->items->first()->inventoryMovement;
        expect(abs((int) $outMovement->total_cost))->toBe(1000);

        test()->pos->voidSale($session->fresh(), $sale, 'Salah barang');

        $restock = InventoryMovement::query()
            ->where('reference_type', PosSale::class)
            ->where('reference_id', $sale->id)
            ->where('type', InventoryMovement::TYPE_IN)
            ->first();

        $layerValue = (int) InventoryCostLayer::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', test()->warehouse->id)
            ->get()
            ->sum(fn (InventoryCostLayer $layer) => $layer->quantity * $layer->unit_cost);

        expect($restock)->not->toBeNull()
            ->and((int) $restock->total_cost)->toBe(1000)
            ->and($layerValue)->toBe(1000);
    });

    it('voids a sale from a closed period by reversing into the current open period', function () {
        FiscalPeriod::query()->delete();

        $lastMonth = now()->subMonth();
        $pastPeriod = FiscalPeriod::factory()->create([
            'name' => 'Bulan lalu',
            'start_date' => $lastMonth->copy()->startOfMonth()->toDateString(),
            'end_date' => $lastMonth->copy()->endOfMonth()->toDateString(),
            'status' => FiscalPeriodStatus::Open,
            'is_closed' => false,
            'is_locked' => false,
        ]);
        FiscalPeriod::factory()->create([
            'name' => 'Bulan ini',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'status' => FiscalPeriodStatus::Open,
            'is_closed' => false,
            'is_locked' => false,
        ]);

        Carbon::setTestNow($lastMonth->copy()->startOfMonth()->addDays(10));

        try {
            $session = openTill();
            $sale = test()->pos->checkout($session, [
                'way' => PosTenderType::Cash->value,
                'cash_received_amount' => 111_00,
                'lines' => [
                    ['product_id' => test()->product->id, 'quantity' => 1],
                ],
            ], 'void-closed-period');

            $pastPeriod->update([
                'status' => FiscalPeriodStatus::Closed,
                'is_closed' => true,
                'is_locked' => true,
            ]);

            Carbon::setTestNow();

            $voided = test()->pos->voidSale($session->fresh(), $sale, 'Salah input bulan lalu');

            $reversal = JournalEntry::query()
                ->where('reversal_of_id', $sale->journal_entry_id)
                ->first();

            expect($voided->status)->toBe(PosSaleStatus::Voided)
                ->and($reversal)->not->toBeNull()
                ->and($reversal->entry_date->toDateString())->toBe(now()->toDateString());
        } finally {
            Carbon::setTestNow();
        }
    });

    it('rejects void when the fiscal period is locked', function () {
        $session = openTill();
        $sale = test()->pos->checkout($session, [
            'way' => PosTenderType::Cash->value,
            'cash_received_amount' => 111_00,
            'lines' => [
                ['product_id' => test()->product->id, 'quantity' => 1],
            ],
        ], 'void-locked');

        FiscalPeriod::query()->delete();
        FiscalPeriod::factory()->current()->locked()->create();

        expect(fn () => test()->pos->voidSale($session->fresh(), $sale, 'Salah barang'))
            ->toThrow(BusinessRuleException::class, 'sedang dikunci');
    });

    it('rejects void after the session is closed', function () {
        $session = openTill();
        $sale = test()->pos->checkout($session, [
            'way' => PosTenderType::Cash->value,
            'cash_received_amount' => 111_00,
            'lines' => [
                ['product_id' => test()->product->id, 'quantity' => 1],
            ],
        ], 'void-closed');
        $closed = test()->pos->closeSession($session, ['counted_cash_amount' => 200_111_00]);

        expect(fn () => test()->pos->voidSale($closed, $sale, 'Terlambat'))
            ->toThrow(BusinessRuleException::class, 'Sesi kasir sudah ditutup');
    });
});

describe('PosService holds', function () {
    it('saves and takes a held cart', function () {
        $session = openTill();
        $hold = test()->pos->hold($session, [
            ['product_id' => test()->product->id, 'quantity' => 2],
        ]);

        expect(test()->pos->listHolds($session))->toHaveCount(1);

        $taken = test()->pos->takeHold($session, $hold);

        expect($taken->id)->toBe($hold->id)
            ->and($taken->lines[0]['product_id'])->toBe(test()->product->id)
            ->and($taken->lines[0]['quantity'])->toBe(2)
            ->and($taken->taken_at)->not->toBeNull()
            ->and(test()->pos->listHolds($session->fresh()))->toHaveCount(0);

        $retried = test()->pos->takeHold($session, $hold);
        expect($retried->id)->toBe($hold->id)
            ->and($retried->taken_at)->not->toBeNull();
    });

    it('rejects a hold with non-positive quantity', function () {
        $session = openTill();

        expect(fn () => test()->pos->hold($session, [
            ['product_id' => test()->product->id, 'quantity' => 0],
        ]))->toThrow(BusinessRuleException::class, 'Kuantitas');
    });

    it('rejects a sixth hold', function () {
        $session = openTill();
        $line = [['product_id' => test()->product->id, 'quantity' => 1]];
        for ($i = 0; $i < 5; $i++) {
            test()->pos->hold($session, $line);
        }

        expect(fn () => test()->pos->hold($session, $line))
            ->toThrow(BusinessRuleException::class, 'Maksimal 5 pesanan ditahan');
    });
});
