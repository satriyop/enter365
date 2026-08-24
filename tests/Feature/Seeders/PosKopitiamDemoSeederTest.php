<?php

declare(strict_types=1);

use App\Models\Contacts\Contact;
use App\Models\Core\Role;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Sales\Invoice;
use App\Models\User;
use Database\Seeders\Demo\DemoSeeder;
use Database\Seeders\Demo\KopitiamCafeMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('seeds Kopitiam 57 stand-in till without invoices', function () {
    applyFeaturePreset('pos');
    seedDemoFoundation($this);
    seedDemoProfile($this, DemoSeeder::DEMO_POS);

    $kasir = User::query()->where('email', 'siti@kopitiam57.test')->first();
    $warehouse = Warehouse::query()->where('code', 'KT57-TOKO')->first();
    $kopi = Product::query()->where('sku', 'KT57-KOPI-O')->first();
    $hakau = Product::query()->where('sku', 'KT57-HAKAU')->first();
    $garlic = Product::query()->where('sku', 'KT57-SB-GARLIC')->first();
    $packing = Product::query()->where('sku', 'KT57-PACK')->first();
    $retiredKaya = Product::query()->where('sku', 'KT57-KAYA')->first();
    $menuCount = count(KopitiamCafeMenu::items());

    expect($kasir)->not->toBeNull()
        ->and($kasir->hasRole(Role::CASHIER))->toBeTrue()
        ->and($warehouse)->not->toBeNull()
        ->and($warehouse->is_default)->toBeTrue()
        ->and(Warehouse::query()->where('code', 'WH-001')->exists())->toBeFalse()
        ->and(Contact::query()->count())->toBe(0)
        ->and(Product::query()->where('sku', 'AC-AMMETER')->exists())->toBeFalse()
        ->and($kopi)->not->toBeNull()
        ->and($kopi->selling_price)->toBe(15_000)
        ->and($kopi->is_taxable)->toBeFalse()
        ->and($kopi->track_inventory)->toBeFalse()
        ->and($hakau)->not->toBeNull()
        ->and($hakau->selling_price)->toBe(22_000)
        ->and($garlic)->not->toBeNull()
        ->and($garlic->selling_price)->toBe(28_000)
        ->and($garlic->is_taxable)->toBeFalse()
        ->and($garlic->is_active)->toBeTrue()
        ->and($garlic->track_inventory)->toBeTrue()
        ->and(is_file(public_path('pos/kopitiam/KT57-SB-GARLIC.jpg')))->toBeTrue()
        ->and($packing === null || $packing->is_sellable === false)->toBeTrue()
        ->and(Product::query()->where('sku', 'like', 'KT57-%')->where('is_sellable', true)->count())->toBe($menuCount)
        ->and($retiredKaya === null || $retiredKaya->is_sellable === false)->toBeTrue()
        ->and(Invoice::query()->count())->toBe(0)
        ->and(User::query()->where('email', 'rina@kopitiam57.test')->first()?->hasRole(Role::ACCOUNTANT))->toBeTrue()
        ->and(User::query()->where('email', 'dewi@kopitiam57.test')->first()?->hasRole(Role::INVENTORY))->toBeTrue();

    $owner = User::query()->where('email', 'admin@example.com')->firstOrFail();
    Sanctum::actingAs($owner);

    $pastry = $this->getJson('/api/v1/products?search=KT57-SB-GARLIC');
    $pastry->assertOk();
    expect(collect($pastry->json('data'))->pluck('sku')->all())->toContain('KT57-SB-GARLIC')
        ->and(collect($pastry->json('data'))->pluck('name')->all())->toContain('Salt Bread Garlic Cheese');

    $busbars = $this->getJson('/api/v1/products?search=AC-AMMETER');
    $busbars->assertOk();
    expect(collect($busbars->json('data'))->pluck('sku')->all())->not->toContain('AC-AMMETER');
});
