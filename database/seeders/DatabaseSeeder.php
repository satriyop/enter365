<?php

namespace Database\Seeders;

use App\Models\Accounting\Role;
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
     *   php artisan db:seed --class=DemoSeeder # Foundation + Demo data
     *
     * For fresh database with demo:
     *   php artisan migrate:fresh --seed
     *   php artisan db:seed --class=Database\\Seeders\\Demo\\DemoSeeder
     */
    public function run(): void
    {
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
        ]);

        // Assign admin role to admin user
        $adminUser = User::where('email', 'admin@example.com')->first();
        $adminUser->assignRole(Role::ADMIN);

        $this->command->info('');
        $this->command->info('Foundation data seeded successfully.');
        $this->command->info('');
        $this->command->info('To add demo data, run:');
        $this->command->info('  php artisan db:seed --class=Database\\\\Seeders\\\\Demo\\\\DemoSeeder');
        $this->command->info('');
    }
}
