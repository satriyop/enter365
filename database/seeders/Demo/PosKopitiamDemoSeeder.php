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
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

/**
 * Kopitiam 57 till catalog.
 *
 * Pastry & Bread SKUs follow the shop board (Menu Pastry Bread.pdf).
 * The board says "Harga belum termasuk tax & service". V1 has no service
 * line and the till button is the amount paid (ADR-0056), so we seed the
 * printed K number with PPN off until they confirm PKP + service.
 * Drinks / makanan / jasa stay stand-in until those menus arrive.
 */
class PosKopitiamDemoSeeder extends Seeder
{
    /** @var list<string> */
    private const RETIRED_STANDIN_SKUS = [
        'KT57-KAYA',
        'KT57-TELUR',
        'KT57-ROTI-CK',
    ];

    public function run(): void
    {
        if (Features::disabled('pos')) {
            $this->command?->warn('  ⚠  FEATURE_POS is off. Seed still writes catalog; /kasir stays hidden until FEATURE_POS=true.');
        }

        $this->call(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $admin = $this->ensureOwner();
        $this->seedKasir($admin);
        $warehouse = $this->seedOutlet();
        $categories = $this->seedCategories();
        $this->seedCatalog($categories, $warehouse, $admin);
        $this->publishPastryPhotos();
        $this->retireStandinRoti();

        $this->command?->info('  ✓ Kopitiam 57 till — pastry from shop board; minuman/makanan still stand-in');
    }

    private function ensureOwner(): User
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin Kopitiam 57',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );
        $admin->assignRole(Role::ADMIN);

        return $admin;
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
        Warehouse::query()->where('is_default', true)->update(['is_default' => false]);

        return Warehouse::query()->updateOrCreate(
            ['code' => 'KT57-TOKO'],
            [
                'name' => 'Kopitiam 57 — Toko Depan',
                'address' => 'Stand-in alamat demo (bukan alamat toko)',
                'phone' => '021-000057',
                'contact_person' => 'Siti Kasir',
                'is_default' => true,
                'is_active' => true,
                'notes' => 'Outlet till Kopitiam 57. Default gudang on the POS-first tenant.',
            ]
        );
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
            'POS-ROT' => 'Pastry',
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

    private function publishPastryPhotos(): void
    {
        $from = database_path('seeders/Demo/assets/kopitiam');
        $to = public_path('pos/kopitiam');
        File::ensureDirectoryExists($to);

        foreach (File::files($from) as $file) {
            File::copy($file->getPathname(), $to.DIRECTORY_SEPARATOR.$file->getFilename());
        }
    }

    private function retireStandinRoti(): void
    {
        Product::query()
            ->whereIn('sku', self::RETIRED_STANDIN_SKUS)
            ->update([
                'is_active' => false,
                'is_sellable' => false,
            ]);
    }

    /**
     * Pastry prices are the printed K number (rupiah integers). PPN off so
     * the till button matches the board; HPP is a stand-in ~40% until recipe cost.
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
            ['sku' => 'KT57-SB-GARLIC', 'name' => 'Salt Bread Garlic Cheese', 'category' => 'POS-ROT', 'price' => 28_000, 'cost' => 11_000, 'qty' => 20, 'track' => true, 'taxable' => false, 'barcode' => '899057000016'],
            ['sku' => 'KT57-SB-DBL', 'name' => 'Salt Bread Double Cheese', 'category' => 'POS-ROT', 'price' => 23_000, 'cost' => 9_000, 'qty' => 20, 'track' => true, 'taxable' => false, 'barcode' => '899057000017'],
            ['sku' => 'KT57-SB-ORI', 'name' => 'Salt Bread Original', 'category' => 'POS-ROT', 'price' => 18_000, 'cost' => 7_000, 'qty' => 20, 'track' => true, 'taxable' => false, 'barcode' => '899057000018'],
            ['sku' => 'KT57-SMEER', 'name' => 'Roti Smeer Meses', 'category' => 'POS-ROT', 'price' => 22_000, 'cost' => 9_000, 'qty' => 20, 'track' => true, 'taxable' => false, 'barcode' => '899057000019'],
            ['sku' => 'KT57-CROISS-BT', 'name' => 'Butter Croissant', 'category' => 'POS-ROT', 'price' => 23_000, 'cost' => 9_000, 'qty' => 20, 'track' => true, 'taxable' => false, 'barcode' => '899057000020'],
            ['sku' => 'KT57-SB-BEEF', 'name' => 'Salt Bread Smoked Beef & Cheese', 'category' => 'POS-ROT', 'price' => 40_000, 'cost' => 16_000, 'qty' => 20, 'track' => true, 'taxable' => false, 'barcode' => '899057000021'],
            ['sku' => 'KT57-CROISS-CH', 'name' => 'Double Choco Croissant', 'category' => 'POS-ROT', 'price' => 45_000, 'cost' => 18_000, 'qty' => 20, 'track' => true, 'taxable' => false, 'barcode' => '899057000022'],
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
