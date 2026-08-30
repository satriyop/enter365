<?php

namespace Database\Seeders;

use App\Models\Core\Role;
use App\Models\User;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Usage:
     *   php artisan db:seed                    # Foundation only
     *   php artisan db:seed --class=DemoSeeder # Foundation + Demo data (full)
     *
     * Component Library Only (for Panel Manufacturing Cross-Reference):
     *   php artisan db:seed --class=Database\\Seeders\\Demo\\ComponentLibrarySeeder
     *
     * Full Demo Data (includes Component Library):
     *   php artisan db:seed --class=Database\\Seeders\\Demo\\DemoSeeder
     *
     * Fresh database with demo:
     *   php artisan migrate:fresh --seed
     *   php artisan db:seed --class=Database\\Seeders\\Demo\\DemoSeeder
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException(
                'DatabaseSeeder is blocked in production (demo admin@example.com / password). Use ./scripts/prod.sh seed-pos for the Kopitiam catalog, or seed named classes explicitly.'
            );
        }

        // Create admin user
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );

        // Foundation seeders (always run)
        $this->call([
            FiscalPeriodSeeder::class,
            ChartOfAccountsSeeder::class,
            RolesAndPermissionsSeeder::class,
            IndonesiaSolarDataSeeder::class,
            PlnTariffSeeder::class,
        ]);

        // Assign admin role to admin user
        $adminUser = User::where('email', 'admin@example.com')->first();
        $adminUser->assignRole(Role::ADMIN);

        $this->command->info('');
        $this->command->info('Foundation data seeded successfully.');
        $this->command->info('FEATURE_PRESET='.config('features.preset', 'general'));
        $this->command->info('');
        $this->command->info('Demo profiles (align with FEATURE_PRESET):');
        $this->command->info('  php artisan seed:demo --demo=general|services|manufacturing|enterprise|vahana|nex|all');
        $this->command->info('  php artisan seed:demo --fresh   # migrate:fresh + foundation + demo');
        $this->command->info('');
        $this->command->info('  Industry masters soft-skip when flags off (electrical_panel / solar_proposals).');
        $this->command->info('');
    }
}
