<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Contracts\Inventory\InventoryServiceInterface;
use App\Enums\Pos\PosPricingMode;
use App\Enums\Pos\PosSessionStatus;
use App\Models\Core\Role;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductCategory;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Models\Pos\PosSession;
use App\Models\User;
use App\Support\Features;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

/**
 * Kopitiam 57 till catalog.
 *
 * Cafe prices from Menu Kopitiam 57 Maju. The till is Moka Add: tile =
 * Harga Cafe, bill adds 5% service then 10% PBJT (ADR-0065). Pastry
 * prices stay the PDF board K number (also cafe).
 */
class PosKopitiamDemoSeeder extends Seeder
{
    /**
     * Shared Kopitiam demo password. Unique enough that Chrome does not
     * treat it as a Have I Been Pwned breach of "password". Not a per-user
     * secret — still change it before handing a real till to Siti.
     */
    public const DEMO_PASSWORD = 'Kopitiam57-kasir';

    /** @var list<string> */
    public const DEMO_EMAILS = [
        'admin@example.com',
        'siti@kopitiam57.test',
        'rina@kopitiam57.test',
        'dewi@kopitiam57.test',
    ];

    /** @var list<string> */
    private const RETIRED_STANDIN_SKUS = [
        'KT57-KAYA',
        'KT57-TELUR',
        'KT57-ROTI-CK',
        'KT57-ESTEH',
        'KT57-INDO',
        'KT57-KWET',
        'KT57-NLEM',
        'KT57-MILO',
        'KT57-KANTONG',
        'KT57-PACK',
    ];

    public function run(): void
    {
        if (Features::disabled('pos')) {
            $this->command?->warn('  ⚠  FEATURE_POS is off. Seed still writes catalog; /kasir stays hidden until FEATURE_POS=true.');
        }

        $this->call(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $admin = $this->ensureOwner();
        $this->seedKasir($admin);
        $this->seedAccountant();
        $this->seedInventoryClerk();
        $warehouse = $this->seedOutlet();
        $categories = $this->seedCategories();
        $this->seedCatalog($categories, $warehouse, $admin);
        $this->publishPastryPhotos();
        $this->retireStandinRoti();
        $this->lockOpenSessionsToAddOn();

        $this->command?->info('  ✓ Kopitiam 57 till — cafe menu + pastry; bill adds service 5% and PBJT 10%');
        $this->command?->info('  Logins: '.implode(', ', self::DEMO_EMAILS));
        $this->command?->info('  Password: '.self::DEMO_PASSWORD);
    }

    /**
     * Hash-only update for the four demo users. Does not touch catalog,
     * sessions, or holds — use this on a live till instead of re-seeding.
     */
    public static function rotatePasswords(): int
    {
        $updated = 0;

        foreach (self::DEMO_EMAILS as $email) {
            $user = User::query()->where('email', $email)->first();
            if ($user === null) {
                continue;
            }

            $user->password = Hash::make(self::DEMO_PASSWORD);
            $user->save();
            $updated++;
        }

        return $updated;
    }

    private function hashedDemoPassword(): string
    {
        return Hash::make(self::DEMO_PASSWORD);
    }

    private function ensureOwner(): User
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin Kopitiam 57',
                'password' => $this->hashedDemoPassword(),
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
                'password' => $this->hashedDemoPassword(),
                'is_active' => true,
            ]
        );
        $kasir->assignRole(Role::CASHIER);

        if ($admin->email !== $kasir->email) {
            $admin->assignRole(Role::ADMIN);
        }
    }

    private function seedAccountant(): void
    {
        $akuntan = User::query()->updateOrCreate(
            ['email' => 'rina@kopitiam57.test'],
            [
                'name' => 'Rina Akuntan',
                'password' => $this->hashedDemoPassword(),
                'is_active' => true,
            ]
        );
        $akuntan->assignRole(Role::ACCOUNTANT);
    }

    private function seedInventoryClerk(): void
    {
        $gudang = User::query()->updateOrCreate(
            ['email' => 'dewi@kopitiam57.test'],
            [
                'name' => 'Dewi Gudang',
                'password' => $this->hashedDemoPassword(),
                'is_active' => true,
            ]
        );
        $gudang->assignRole(Role::INVENTORY);
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
            'POS-DIM' => 'Dimsum',
            'POS-APP' => 'Appetizer',
            'POS-TOS' => 'Toast',
            'POS-BUB' => 'Bubur & Sup',
            'POS-NAS' => 'Nasi',
            'POS-MIE' => 'Mie',
            'POS-TOF' => 'Tofu',
            'POS-KOP' => 'Kopi',
            'POS-TEH' => 'Teh',
            'POS-MLK' => 'Milk Based',
            'POS-JUS' => 'Jus',
            'POS-SMH' => 'Smoothies',
            'POS-FLT' => 'Float',
            'POS-XTR' => 'Extra',
            'POS-ROT' => 'Pastry',
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
                        'description' => 'Kopitiam 57 — harga cafe. Bill menambah service 5% dan PBJT 10%.',
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

    private function lockOpenSessionsToAddOn(): void
    {
        PosSession::query()
            ->where('status', PosSessionStatus::Open)
            ->update([
                'pricing_mode' => PosPricingMode::Add,
                'service_rate' => 5,
                'tax_add_rate' => 10,
                'tax_add_name' => 'PBJT',
            ]);
    }

    /**
     * @return list<array{sku: string, name: string, category: string, price: int, cost: int, qty: int, track: bool, taxable: bool, barcode: string}>
     */
    private function skus(): array
    {
        return KopitiamCafeMenu::items();
    }
}
