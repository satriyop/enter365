<?php

declare(strict_types=1);

use App\Contracts\Purchasing\LandedCostServiceInterface;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Accounting\Account;
use App\Models\Inventory\InventoryCostLayer;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Models\Purchasing\GoodsReceiptNote;
use App\Models\Purchasing\GoodsReceiptNoteItem;
use App\Models\Purchasing\LandedCost;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();

    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    $this->service = app(LandedCostServiceInterface::class);
    $this->inventoryAccount = Account::where('code', '1-1400')->first();
    $this->expenseAccount = Account::where('code', '5-1005')->first()
        ?? Account::factory()->create(['code' => '5-1005', 'name' => 'Ongkos Angkut Pembelian', 'type' => 'expense']);

    // Set up a completed GRN with 2 items
    $this->warehouse = Warehouse::factory()->create();
    $this->productA = Product::factory()->create(['purchase_price' => 100000]);
    $this->productB = Product::factory()->create(['purchase_price' => 200000]);

    $this->grn = GoodsReceiptNote::factory()->completed()->create([
        'warehouse_id' => $this->warehouse->id,
    ]);

    // Item A: 10 units @ 100,000 = 1,000,000
    $this->itemA = GoodsReceiptNoteItem::factory()->create([
        'goods_receipt_note_id' => $this->grn->id,
        'product_id' => $this->productA->id,
        'quantity_ordered' => 10,
        'quantity_received' => 10,
        'unit_price' => 100000,
    ]);

    // Item B: 5 units @ 200,000 = 1,000,000
    $this->itemB = GoodsReceiptNoteItem::factory()->create([
        'goods_receipt_note_id' => $this->grn->id,
        'product_id' => $this->productB->id,
        'quantity_ordered' => 5,
        'quantity_received' => 5,
        'unit_price' => 200000,
    ]);

    // Create FIFO cost layers (as if inventory was recorded)
    $this->layerA = InventoryCostLayer::create([
        'product_id' => $this->productA->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 10,
        'original_quantity' => 10,
        'unit_cost' => 100000,
        'received_date' => now()->toDateString(),
        'reference_type' => GoodsReceiptNote::class,
        'reference_id' => $this->grn->id,
    ]);

    $this->layerB = InventoryCostLayer::create([
        'product_id' => $this->productB->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 5,
        'original_quantity' => 5,
        'unit_cost' => 200000,
        'received_date' => now()->toDateString(),
        'reference_type' => GoodsReceiptNote::class,
        'reference_id' => $this->grn->id,
    ]);

    // Create product stock records (weighted average)
    $this->stockA = ProductStock::create([
        'product_id' => $this->productA->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 10,
        'average_cost' => 100000,
        'total_value' => 1000000,
    ]);

    $this->stockB = ProductStock::create([
        'product_id' => $this->productB->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 5,
        'average_cost' => 200000,
        'total_value' => 1000000,
    ]);
});

describe('LandedCostService', function () {

    it('creates a draft landed cost', function () {
        $lc = $this->service->create($this->grn, [
            'description' => 'Ongkos angkut',
            'cost_type' => 'shipping',
            'total_amount' => 500000,
            'expense_account_id' => $this->expenseAccount->id,
        ]);

        expect($lc)->toBeInstanceOf(LandedCost::class)
            ->and($lc->status)->toBe('draft')
            ->and($lc->total_amount)->toBe(500000)
            ->and($lc->allocation_method)->toBe('by_value')
            ->and($lc->goods_receipt_note_id)->toBe($this->grn->id);
    });

    it('by-value allocation distributes proportionally', function () {
        // Total GRN value: A = 1,000,000, B = 1,000,000 (50/50 split)
        $lc = $this->service->create($this->grn, [
            'description' => 'Shipping cost',
            'cost_type' => 'shipping',
            'total_amount' => 200000,
            'allocation_method' => 'by_value',
            'expense_account_id' => $this->expenseAccount->id,
        ]);

        $lc = $this->service->apply($lc);

        expect($lc->status)->toBe('applied')
            ->and($lc->allocations)->toHaveCount(2);

        $allocA = $lc->allocations->firstWhere('product_id', $this->productA->id);
        $allocB = $lc->allocations->firstWhere('product_id', $this->productB->id);

        // 50/50 split on 200,000
        expect($allocA->allocated_amount)->toBe(100000)
            ->and($allocB->allocated_amount)->toBe(100000);

        // Sum must equal total
        $sum = $lc->allocations->sum('allocated_amount');
        expect($sum)->toBe(200000);
    });

    it('by-quantity allocation distributes by qty', function () {
        // A: 10 units, B: 5 units → 2:1 ratio
        $lc = $this->service->create($this->grn, [
            'description' => 'Insurance',
            'cost_type' => 'insurance',
            'total_amount' => 150000,
            'allocation_method' => 'by_quantity',
            'expense_account_id' => $this->expenseAccount->id,
        ]);

        $lc = $this->service->apply($lc);

        $allocA = $lc->allocations->firstWhere('product_id', $this->productA->id);
        $allocB = $lc->allocations->firstWhere('product_id', $this->productB->id);

        // A gets 10/15 * 150,000 = 100,000, B gets remainder = 50,000
        expect($allocA->allocated_amount)->toBe(100000)
            ->and($allocB->allocated_amount)->toBe(50000);

        // Sum must equal total
        expect($allocA->allocated_amount + $allocB->allocated_amount)->toBe(150000);
    });

    it('FIFO cost layers updated with new unit cost', function () {
        $lc = $this->service->create($this->grn, [
            'description' => 'Freight',
            'cost_type' => 'shipping',
            'total_amount' => 200000,
            'allocation_method' => 'by_value',
            'expense_account_id' => $this->expenseAccount->id,
        ]);

        $this->service->apply($lc);

        // Layer A: 100,000 allocated / 10 units = +10,000 per unit
        $this->layerA->refresh();
        expect($this->layerA->unit_cost)->toBe(110000);

        // Layer B: 100,000 allocated / 5 units = +20,000 per unit
        $this->layerB->refresh();
        expect($this->layerB->unit_cost)->toBe(220000);
    });

    it('weighted average recalculated after apply', function () {
        $lc = $this->service->create($this->grn, [
            'description' => 'Customs duty',
            'cost_type' => 'customs',
            'total_amount' => 200000,
            'allocation_method' => 'by_value',
            'expense_account_id' => $this->expenseAccount->id,
        ]);

        $this->service->apply($lc);

        // Stock A: (10 * 100,000 + 100,000) / 10 = 110,000
        $this->stockA->refresh();
        expect($this->stockA->average_cost)->toBe(110000)
            ->and($this->stockA->total_value)->toBe(1100000);

        // Stock B: (5 * 200,000 + 100,000) / 5 = 220,000
        $this->stockB->refresh();
        expect($this->stockB->average_cost)->toBe(220000)
            ->and($this->stockB->total_value)->toBe(1100000);
    });

    it('JE balanced: Dr Inventory == Cr Expense', function () {
        $lc = $this->service->create($this->grn, [
            'description' => 'Shipping',
            'cost_type' => 'shipping',
            'total_amount' => 300000,
            'expense_account_id' => $this->expenseAccount->id,
        ]);

        $lc = $this->service->apply($lc);

        expect($lc->journal_entry_id)->not->toBeNull();

        $je = $lc->journalEntry;
        expect($je->is_posted)->toBeTrue()
            ->and($je->lines)->toHaveCount(2);

        $debitLine = $je->lines->firstWhere('debit', '>', 0);
        $creditLine = $je->lines->firstWhere('credit', '>', 0);

        expect($debitLine->account_id)->toBe($this->inventoryAccount->id)
            ->and($debitLine->debit)->toBe(300000)
            ->and($creditLine->account_id)->toBe($this->expenseAccount->id)
            ->and($creditLine->credit)->toBe(300000);
    });

    it('reverse restores original costs and creates reversal JE', function () {
        $lc = $this->service->create($this->grn, [
            'description' => 'Shipping',
            'cost_type' => 'shipping',
            'total_amount' => 200000,
            'allocation_method' => 'by_value',
            'expense_account_id' => $this->expenseAccount->id,
        ]);

        $lc = $this->service->apply($lc);

        // Verify costs were adjusted
        $this->layerA->refresh();
        expect($this->layerA->unit_cost)->toBe(110000);

        // Now reverse
        $lc = $this->service->reverse($lc);

        expect($lc->status)->toBe('reversed');

        // FIFO layers should be restored
        $this->layerA->refresh();
        $this->layerB->refresh();
        expect($this->layerA->unit_cost)->toBe(100000)
            ->and($this->layerB->unit_cost)->toBe(200000);

        // Weighted average should be restored
        $this->stockA->refresh();
        $this->stockB->refresh();
        expect($this->stockA->average_cost)->toBe(100000)
            ->and($this->stockB->average_cost)->toBe(200000);
    });

    it('rounding: allocated amounts sum to total exactly', function () {
        // Create a 3rd item to test rounding with indivisible totals
        $productC = Product::factory()->create(['purchase_price' => 150000]);
        GoodsReceiptNoteItem::factory()->create([
            'goods_receipt_note_id' => $this->grn->id,
            'product_id' => $productC->id,
            'quantity_ordered' => 3,
            'quantity_received' => 3,
            'unit_price' => 150000,
        ]);

        InventoryCostLayer::create([
            'product_id' => $productC->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 3,
            'original_quantity' => 3,
            'unit_cost' => 150000,
            'received_date' => now()->toDateString(),
            'reference_type' => GoodsReceiptNote::class,
            'reference_id' => $this->grn->id,
        ]);

        ProductStock::create([
            'product_id' => $productC->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 3,
            'average_cost' => 150000,
            'total_value' => 450000,
        ]);

        // 333,333 is not evenly divisible by 3 items
        $lc = $this->service->create($this->grn, [
            'description' => 'Odd amount',
            'cost_type' => 'other',
            'total_amount' => 333333,
            'allocation_method' => 'by_value',
            'expense_account_id' => $this->expenseAccount->id,
        ]);

        $lc = $this->service->apply($lc);

        // Rounding: sum of all allocations MUST equal total amount exactly
        $sum = $lc->allocations->sum('allocated_amount');
        expect($sum)->toBe(333333);
    });

    it('cannot apply to cancelled GRN', function () {
        // Create LC on a valid GRN first
        $lc = $this->service->create($this->grn, [
            'description' => 'Should fail on apply',
            'cost_type' => 'shipping',
            'total_amount' => 100000,
            'expense_account_id' => $this->expenseAccount->id,
        ]);

        // Now cancel the GRN
        $this->grn->update([
            'status' => DocumentStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        expect(fn () => $this->service->apply($lc))->toThrow(BusinessRuleException::class);
    });

    it('cannot apply already applied landed cost', function () {
        $lc = $this->service->create($this->grn, [
            'description' => 'Shipping',
            'cost_type' => 'shipping',
            'total_amount' => 100000,
            'expense_account_id' => $this->expenseAccount->id,
        ]);

        $lc = $this->service->apply($lc);
        expect($lc->status)->toBe('applied');

        expect(fn () => $this->service->apply($lc))->toThrow(BusinessRuleException::class);
    });

    it('cannot reverse a draft landed cost', function () {
        $lc = $this->service->create($this->grn, [
            'description' => 'Shipping',
            'cost_type' => 'shipping',
            'total_amount' => 100000,
            'expense_account_id' => $this->expenseAccount->id,
        ]);

        expect(fn () => $this->service->reverse($lc))->toThrow(BusinessRuleException::class);
    });

    it('cannot create landed cost on cancelled GRN', function () {
        $cancelledGrn = GoodsReceiptNote::factory()->cancelled()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);

        expect(fn () => $this->service->create($cancelledGrn, [
            'description' => 'Rejected',
            'cost_type' => 'shipping',
            'total_amount' => 100000,
            'expense_account_id' => $this->expenseAccount->id,
        ]))->toThrow(BusinessRuleException::class);
    });

});
