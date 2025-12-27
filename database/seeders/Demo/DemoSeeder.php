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
     *   php artisan db:seed --class=Database\\Seeders\\Demo\\DemoSeeder
     *
     * Or include in DatabaseSeeder with:
     *   php artisan db:seed --class=DatabaseSeeder
     *   (when demo mode is enabled)
     */
    public function run(): void
    {
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

        // Seed NEX-specific data (Solar EPC Contractor)
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
        $this->command->info('║  Customers Represented:                                            ║');
        $this->command->info('║    ⚡ PT Vahana: Switchboards, MCC, ATS, Capacitor Bank panels      ║');
        $this->command->info('║    ☀️  PT NEX: PLTS Rooftop, Ground Mount, Lease-to-Own solar       ║');
        $this->command->info('╠═══════════════════════════════════════════════════════════════════╣');
        $this->command->info('║  Component Library (Brand Partners):                               ║');
        $this->command->info('║    Schneider Electric | ABB | Siemens | CHINT | LS | Legrand       ║');
        $this->command->info('╚═══════════════════════════════════════════════════════════════════╝');
        $this->command->info('');
    }
}
