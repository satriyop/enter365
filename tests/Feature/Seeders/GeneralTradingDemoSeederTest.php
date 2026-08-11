<?php

declare(strict_types=1);

use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Purchasing\Bill;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Sales\Invoice;
use App\Models\Sales\Quotation;
use App\Models\Shared\Payment;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\Demo\GeneralTradingDemoSeeder;
use Database\Seeders\Demo\MasterDataSeeder;
use Database\Seeders\FiscalPeriodSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // General company packs (vertical off) — seeder must still work
    withFeatures([
        'quotations' => true,
        'delivery_orders' => true,
        'purchase_orders' => true,
        'goods_receipt_notes' => true,
        'inventory' => true,
        'down_payments' => true,
        'stock_opname' => true,
        'solar_proposals' => false,
        'electrical_panel' => false,
        'projects' => false,
        'bom' => false,
        'work_orders' => false,
        'mrp' => false,
        'manufacturing' => false,
        'subcontracting' => false,
    ]);

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
    $this->seed(FiscalPeriodSeeder::class);
    $this->seed(MasterDataSeeder::class);
});

it('seeds a complete general trading purchase and sales cycle', function () {
    $this->seed(GeneralTradingDemoSeeder::class);

    expect(Contact::where('code', 'C-GEN-01')->exists())->toBeTrue()
        ->and(Contact::where('code', 'S-GEN-01')->exists())->toBeTrue()
        ->and(Product::where('sku', 'GEN-ITEM-A')->exists())->toBeTrue()
        ->and(Product::where('sku', 'GEN-ITEM-B')->exists())->toBeTrue();

    expect(PurchaseOrder::query()->count())->toBeGreaterThan(0)
        ->and(Bill::query()->count())->toBeGreaterThan(0)
        ->and(Quotation::query()->count())->toBeGreaterThan(0)
        ->and(Invoice::query()->count())->toBeGreaterThan(0)
        ->and(Payment::query()->count())->toBeGreaterThan(0);

    // Stock should exist after GRN (purchase of GEN-ITEM-A)
    $productA = Product::where('sku', 'GEN-ITEM-A')->first();
    $stock = ProductStock::where('product_id', $productA->id)->sum('quantity');
    expect((int) $stock)->toBeGreaterThan(0);

    // At least one posted invoice with a journal entry
    $posted = Invoice::query()->whereNotNull('journal_entry_id')->count();
    expect($posted)->toBeGreaterThan(0);
});
