<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Contracts\Inventory\InventoryServiceInterface;
use App\Models\Core\Role;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductCategory;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use App\Support\Features;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Stand-in till catalog for first-customer day: Kopitiam 57.
 *
 * Prices are typical Indonesian kopitiam board amounts (rupiah integers),
 * not a scraped menu. PPN is off until the shop confirms they are PKP —
 * the button is exactly the seeded selling_price.
 */
class PosKopitiamDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Features::disabled('pos')) {
            $this->command?->warn('  ⚠  FEATURE_POS is off. Seed still writes catalog; /kasir stays hidden until FEATURE_POS=true.');
        }

        $this->call(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $admin = User::query()->where('email', 'admin@demo.com')->first()
            ?? User::query()->where('email', 'admin@example.com')->first()
            ?? User::query()->first();

        if ($admin === null) {
            throw new \RuntimeException('POS kopitiam demo needs a user. Run foundation seeders first.');
        }

        $this->seedKasir($admin);
        $warehouse = $this->seedOutlet();
        $categories = $this->seedCategories();
        $this->seedCatalog($categories, $warehouse, $admin);

        $this->command?->info('  ✓ Kopitiam 57 stand-in till (DEMO — bukan harga toko)');
    }

    private function seedKasir(User $admin): void
    {
        $kasir = User::query()->updateOrCreate(
            ['email' => 'siti@kopitiam57.test'],
            [
                'name' => 'Siti Kasir',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );
        $kasir->assignRole(Role::CASHIER);

        if ($admin->email !== $kasir->email) {
            $admin->assignRole(Role::ADMIN);
        }
    }

    private function seedOutlet(): Warehouse
    {
        $warehouse = Warehouse::query()->updateOrCreate(
            ['code' => 'KT57-TOKO'],
            [
                'name' => 'Kopitiam 57 — Toko Depan',
                'address' => 'Stand-in alamat demo (bukan alamat toko)',
                'phone' => '021-000057',
                'contact_person' => 'Siti Kasir',
                'is_default' => false,
                'is_active' => true,
                'notes' => 'DEMO POS: outlet till Kopitiam 57. Bukan data toko asli. Must not steal the ERP default gudang.',
            ]
        );

        if (Warehouse::query()->where('is_default', true)->doesntExist()) {
            Warehouse::query()->where('code', 'WH-001')->update(['is_default' => true]);
        }

        return $warehouse;
    }

    /**
     * @return array<string, ProductCategory>
     */
    private function seedCategories(): array
    {
        $parent = ProductCategory::query()->updateOrCreate(
            ['code' => 'POS'],
            [
                'name' => 'Kasir',
                'description' => 'Kategori till Kopitiam 57 (demo)',
                'is_active' => true,
                'sort_order' => 90,
            ]
        );

        $children = [
            'POS-MIN' => 'Minuman',
            'POS-MAK' => 'Makanan',
            'POS-ROT' => 'Roti',
            'POS-JSA' => 'Jasa',
        ];

        $map = [];
        $order = 1;
        foreach ($children as $code => $name) {
            $map[$code] = ProductCategory::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'parent_id' => $parent->id,
                    'is_active' => true,
                    'sort_order' => $order++,
                ]
            );
        }

        return $map;
    }

    /**
     * @param  array<string, ProductCategory>  $categories
     */
    private function seedCatalog(array $categories, Warehouse $warehouse, User $admin): void
    {
        $inventory = app(InventoryServiceInterface::class);

        Auth::guard('web')->login($admin);

        try {
            foreach ($this->skus() as $row) {
                $product = Product::query()->updateOrCreate(
                    ['sku' => $row['sku']],
                    [
                        'name' => $row['name'],
                        'description' => 'DEMO Kopitiam 57 — harga papan stand-in, bukan harga toko.',
                        'type' => $row['track'] ? Product::TYPE_PRODUCT : Product::TYPE_SERVICE,
                        'category_id' => $categories[$row['category']]->id,
                        'unit' => 'pcs',
                        'purchase_price' => $row['cost'],
                        'selling_price' => $row['price'],
                        'tax_rate' => 11.00,
                        'is_taxable' => $row['taxable'],
                        'track_inventory' => $row['track'],
                        'is_active' => true,
                        'is_purchasable' => $row['track'],
                        'is_sellable' => true,
                        'barcode' => $row['barcode'],
                    ]
                );

                if (! $row['track']) {
                    continue;
                }

                $existing = ProductStock::query()
                    ->where('product_id', $product->id)
                    ->where('warehouse_id', $warehouse->id)
                    ->value('quantity');

                if ((int) $existing > 0) {
                    continue;
                }

                $inventory->stockIn(
                    $product,
                    $warehouse,
                    $row['qty'],
                    $row['cost'],
                    'Stok awal DEMO Kopitiam 57',
                );
            }
        } finally {
            Auth::guard('web')->logout();
        }
    }

    /**
     * Typical kedai board (rupiah integers). Taxable flag false so the till
     * button equals the papan; confirm PKP before turning PPN on.
     *
     * @return list<array{sku: string, name: string, category: string, price: int, cost: int, qty: int, track: bool, taxable: bool, barcode: string}>
     */
    private function skus(): array
    {
        return [
            ['sku' => 'KT57-KOPI-O', 'name' => 'Kopi O', 'category' => 'POS-MIN', 'price' => 8_000, 'cost' => 2_500, 'qty' => 80, 'track' => true, 'taxable' => false, 'barcode' => '899057000001'],
            ['sku' => 'KT57-KOPI-TRK', 'name' => 'Kopi Tarik', 'category' => 'POS-MIN', 'price' => 12_000, 'cost' => 4_000, 'qty' => 80, 'track' => true, 'taxable' => false, 'barcode' => '899057000002'],
            ['sku' => 'KT57-TEH-TRK', 'name' => 'Teh Tarik', 'category' => 'POS-MIN', 'price' => 12_000, 'cost' => 3_500, 'qty' => 80, 'track' => true, 'taxable' => false, 'barcode' => '899057000003'],
            ['sku' => 'KT57-MILO', 'name' => 'Milo', 'category' => 'POS-MIN', 'price' => 14_000, 'cost' => 5_000, 'qty' => 60, 'track' => true, 'taxable' => false, 'barcode' => '899057000004'],
            ['sku' => 'KT57-ESTEH', 'name' => 'Es Teh Manis', 'category' => 'POS-MIN', 'price' => 6_000, 'cost' => 1_500, 'qty' => 100, 'track' => true, 'taxable' => false, 'barcode' => '899057000005'],
            ['sku' => 'KT57-KAYA', 'name' => 'Kaya Butter Toast', 'category' => 'POS-ROT', 'price' => 18_000, 'cost' => 7_000, 'qty' => 40, 'track' => true, 'taxable' => false, 'barcode' => '899057000006'],
            ['sku' => 'KT57-TELUR', 'name' => 'Telur ½ Matang (2)', 'category' => 'POS-ROT', 'price' => 10_000, 'cost' => 4_000, 'qty' => 50, 'track' => true, 'taxable' => false, 'barcode' => '899057000007'],
            ['sku' => 'KT57-ROTI-CK', 'name' => 'Roti Bakar Cokelat', 'category' => 'POS-ROT', 'price' => 15_000, 'cost' => 6_000, 'qty' => 40, 'track' => true, 'taxable' => false, 'barcode' => '899057000008'],
            ['sku' => 'KT57-NLEM', 'name' => 'Nasi Lemak', 'category' => 'POS-MAK', 'price' => 22_000, 'cost' => 10_000, 'qty' => 30, 'track' => true, 'taxable' => false, 'barcode' => '899057000009'],
            ['sku' => 'KT57-HAINAN', 'name' => 'Nasi Ayam Hainan', 'category' => 'POS-MAK', 'price' => 28_000, 'cost' => 14_000, 'qty' => 30, 'track' => true, 'taxable' => false, 'barcode' => '899057000010'],
            ['sku' => 'KT57-INDO', 'name' => 'Indomie Goreng', 'category' => 'POS-MAK', 'price' => 12_000, 'cost' => 5_000, 'qty' => 60, 'track' => true, 'taxable' => false, 'barcode' => '899057000011'],
            ['sku' => 'KT57-KWET', 'name' => 'Kwetiau', 'category' => 'POS-MAK', 'price' => 25_000, 'cost' => 11_000, 'qty' => 25, 'track' => true, 'taxable' => false, 'barcode' => '899057000012'],
            ['sku' => 'KT57-SIOMAY', 'name' => 'Siomay', 'category' => 'POS-MAK', 'price' => 18_000, 'cost' => 8_000, 'qty' => 25, 'track' => true, 'taxable' => false, 'barcode' => '899057000013'],
            ['sku' => 'KT57-KANTONG', 'name' => 'Kantong Plastik', 'category' => 'POS-JSA', 'price' => 500, 'cost' => 0, 'qty' => 0, 'track' => false, 'taxable' => false, 'barcode' => '899057000014'],
            ['sku' => 'KT57-PACK', 'name' => 'Jasa Packing', 'category' => 'POS-JSA', 'price' => 2_000, 'cost' => 0, 'qty' => 0, 'track' => false, 'taxable' => false, 'barcode' => '899057000015'],
        ];
    }
}
