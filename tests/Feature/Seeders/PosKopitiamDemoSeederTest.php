<?php

declare(strict_types=1);

use App\Models\Core\Role;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Models\Sales\Invoice;
use App\Models\User;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds Kopitiam 57 stand-in till without invoices', function () {
    applyFeaturePreset('pos');
    seedDemoFoundation($this);
    seedDemoProfile($this, DemoSeeder::DEMO_POS);

    $kasir = User::query()->where('email', 'siti@kopitiam57.test')->first();
    $warehouse = Warehouse::query()->where('code', 'KT57-TOKO')->first();
    $kopi = Product::query()->where('sku', 'KT57-KOPI-O')->first();
    $packing = Product::query()->where('sku', 'KT57-PACK')->first();

    expect($kasir)->not->toBeNull()
        ->and($kasir->hasRole(Role::CASHIER))->toBeTrue()
        ->and($warehouse)->not->toBeNull()
        ->and($warehouse->is_default)->toBeFalse()
        ->and($kopi)->not->toBeNull()
        ->and($kopi->selling_price)->toBe(8_000)
        ->and($kopi->is_taxable)->toBeFalse()
        ->and($kopi->track_inventory)->toBeTrue()
        ->and($packing->track_inventory)->toBeFalse()
        ->and(Product::query()->where('sku', 'like', 'KT57-%')->count())->toBe(15)
        ->and(Invoice::query()->count())->toBe(0);

    $stock = ProductStock::query()
        ->where('product_id', $kopi->id)
        ->where('warehouse_id', $warehouse->id)
        ->first();

    expect($stock)->not->toBeNull()
        ->and($stock->quantity)->toBeGreaterThan(0);
});
