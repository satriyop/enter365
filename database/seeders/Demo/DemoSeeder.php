<?php

namespace Database\Seeders\Demo;

use Database\Seeders\Demo\Nex\NexContactSeeder;
use Database\Seeders\Demo\Nex\NexProductSeeder;
use Database\Seeders\Demo\Nex\NexTransactionSeeder;
use Database\Seeders\Demo\Vahana\VahanaContactSeeder;
use Database\Seeders\Demo\Vahana\VahanaProductSeeder;
use Database\Seeders\Demo\Vahana\VahanaTransactionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public const DEMO_VAHANA = 'vahana';

    public const DEMO_NEX = 'nex';

    public const DEMO_ALL = 'all';

    /**
     * Seed demo data for the application.
     *
     * This seeder creates contextual demo data for:
     * - PT Vahana Gasti Teknika (Electrical Panel Maker) - vahana.co.id
     * - PT Nusantara Energi Khatulistiwa / NEX (Solar EPC) - energimasadepan.com
     *
     * It includes:
     * - Master data (warehouses, product categories, users)
     * - Contacts (customers, vendors, subcontractors)
     * - Products with BOMs (raw materials, finished goods, services)
     * - Full transaction cycle (quotations, invoices, POs, work orders)
     *
     * Usage:
     *   php artisan db:seed --class=DemoSeeder                    # Interactive prompt
     *   php artisan db:seed --class=DemoSeeder --demo=vahana      # Vahana only
     *   php artisan db:seed --class=DemoSeeder --demo=nex         # NEX only
     *   php artisan db:seed --class=DemoSeeder --demo=all         # Both (default)
     *
     * Or with fresh migration:
     *   php artisan migrate:fresh --seed --seeder=DemoSeeder --demo=vahana
     */
    public function run(): void
    {
        $demoChoice = $this->getDemoChoice();

        $this->command->info('');
        $this->command->info('╔═══════════════════════════════════════════════════════════════════╗');
        $this->command->info('║                     ENTER365 DEMO DATA                             ║');
        $this->command->info('║         Indonesian Accounting System - SAK EMKM Compliant          ║');
        $this->command->info('╚═══════════════════════════════════════════════════════════════════╝');
        $this->command->info('');

        // Ensure roles and permissions exist (required for user role assignment)
        $this->command->info('🔐 Ensuring Roles & Permissions...');
        $this->call(RolesAndPermissionsSeeder::class);
        $this->command->info('');

        // Seed master data first (shared between both customers)
        $this->command->info('📦 Seeding Master Data...');
        $this->call(MasterDataSeeder::class);
        $this->command->info('');

        // Seed comprehensive component library for panel manufacturing
        $this->command->info('🔌 Seeding Component Library (Cross-Reference System)...');
        $this->call(ComponentLibrarySeeder::class);
        $this->command->info('');

        // Seed Vahana-specific data (Electrical Panel Maker)
        if ($demoChoice === self::DEMO_VAHANA || $demoChoice === self::DEMO_ALL) {
            $this->seedVahana();
        }

        // Seed NEX-specific data (Solar EPC Contractor)
        if ($demoChoice === self::DEMO_NEX || $demoChoice === self::DEMO_ALL) {
            $this->seedNex();
        }

        $this->showCompletionMessage($demoChoice);
    }

    /**
     * Get the demo choice from app instance, command option, or interactive prompt.
     */
    protected function getDemoChoice(): string
    {
        // Check if demo choice was set via custom command (seed:demo)
        if (app()->bound('demo.choice')) {
            return app('demo.choice');
        }

        // Check if running in non-interactive mode (e.g., CI/CD)
        if (! $this->command->input->isInteractive()) {
            return self::DEMO_ALL;
        }

        // Interactive prompt
        $this->command->info('');
        $this->command->info('Which demo data would you like to seed?');
        $this->command->info('');

        return $this->command->choice(
            'Select demo data',
            [
                self::DEMO_VAHANA => '⚡ Vahana - Electrical Panel Maker (vahana.co.id)',
                self::DEMO_NEX => '☀️  NEX - Solar EPC Contractor (energimasadepan.com)',
                self::DEMO_ALL => '🔄 Both - Vahana + NEX (full demo)',
            ],
            self::DEMO_ALL
        );
    }

    /**
     * Seed Vahana demo data.
     */
    protected function seedVahana(): void
    {
        $this->command->info('╔═══════════════════════════════════════════════════════════════════╗');
        $this->command->info('║  ⚡ PT VAHANA GASTI TEKNIKA - Electrical Panel Maker               ║');
        $this->command->info('║     https://vahana.co.id                                           ║');
        $this->command->info('╚═══════════════════════════════════════════════════════════════════╝');
        $this->command->info('');

        $this->command->info('  → Contacts (PLN, Contractors, Industries)');
        $this->call(VahanaContactSeeder::class);

        $this->command->info('  → Products & BOMs (MCB, MCCB, Panels)');
        $this->call(VahanaProductSeeder::class);

        $this->command->info('  → Transactions (Quotations, Invoices, POs, Work Orders)');
        $this->call(VahanaTransactionSeeder::class);

        $this->command->info('');
    }

    /**
     * Seed NEX demo data.
     */
    protected function seedNex(): void
    {
        $this->command->info('╔═══════════════════════════════════════════════════════════════════╗');
        $this->command->info('║  ☀️  PT NUSANTARA ENERGI KHATULISTIWA (NEX) - Solar EPC            ║');
        $this->command->info('║     https://energimasadepan.com                                    ║');
        $this->command->info('╚═══════════════════════════════════════════════════════════════════╝');
        $this->command->info('');

        $this->command->info('  → Contacts (Industrial, Commercial, Agricultural customers)');
        $this->call(NexContactSeeder::class);

        $this->command->info('  → Products & BOMs (PV Modules, Inverters, PLTS Systems)');
        $this->call(NexProductSeeder::class);

        $this->command->info('  → Transactions (Quotations with Multi-Option Variants)');
        $this->call(NexTransactionSeeder::class);

        $this->command->info('');
    }

    /**
     * Show completion message based on demo choice.
     */
    protected function showCompletionMessage(string $demoChoice): void
    {
        $this->command->info('╔═══════════════════════════════════════════════════════════════════╗');
        $this->command->info('║                      Demo Data Complete!                           ║');
        $this->command->info('╠═══════════════════════════════════════════════════════════════════╣');
        $this->command->info('║  Demo Users:                                                       ║');
        $this->command->info('║    admin@demo.com      (password: password)                        ║');
        $this->command->info('║    sales@demo.com      (password: password)                        ║');
        $this->command->info('║    purchasing@demo.com (password: password)                        ║');
        $this->command->info('║    produksi@demo.com   (password: password)                        ║');
        $this->command->info('║    finance@demo.com    (password: password)                        ║');
        $this->command->info('║    gudang@demo.com     (password: password)                        ║');
        $this->command->info('╠═══════════════════════════════════════════════════════════════════╣');
        $this->command->info('║  Data Seeded:                                                      ║');

        if ($demoChoice === self::DEMO_VAHANA || $demoChoice === self::DEMO_ALL) {
            $this->command->info('║    ⚡ PT Vahana: Switchboards, MCC, ATS, Capacitor Bank panels      ║');
        }
        if ($demoChoice === self::DEMO_NEX || $demoChoice === self::DEMO_ALL) {
            $this->command->info('║    ☀️  PT NEX: PLTS Rooftop, Ground Mount, Lease-to-Own solar       ║');
        }

        $this->command->info('╠═══════════════════════════════════════════════════════════════════╣');
        $this->command->info('║  Component Library (Brand Partners):                               ║');
        $this->command->info('║    Schneider Electric | ABB | Siemens | CHINT | LS | Legrand       ║');
        $this->command->info('╚═══════════════════════════════════════════════════════════════════╝');
        $this->command->info('');
    }
}
