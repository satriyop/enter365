<?php

namespace Database\Seeders\Demo;

use App\Support\Features;
use Database\Seeders\Demo\Nex\NexContactSeeder;
use Database\Seeders\Demo\Nex\NexProductSeeder;
use Database\Seeders\Demo\Nex\NexTransactionSeeder;
use Database\Seeders\Demo\Vahana\VahanaContactSeeder;
use Database\Seeders\Demo\Vahana\VahanaProductSeeder;
use Database\Seeders\Demo\Vahana\VahanaTransactionSeeder;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    /** General SME trading/jasa — aligns with FEATURE_PRESET=general */
    public const DEMO_GENERAL = 'general';

    public const DEMO_VAHANA = 'vahana';

    public const DEMO_NEX = 'nex';

    public const DEMO_ALL = 'all';

    /**
     * Seed demo data for the application.
     *
     * Profiles:
     * - general: master + core accounting/trading extended data (default product posture)
     * - vahana: panel manufacturing (enable FEATURE_PRESET=manufacturing or full)
     * - nex: solar EPC (enable FEATURE_PRESET=solar or full)
     * - all: both verticals + full packs
     *
     * Usage:
     *   php artisan seed:demo --demo=general
     *   php artisan seed:demo --demo=vahana
     *   php artisan seed:demo --demo=nex
     *   php artisan seed:demo --demo=all
     */
    public function run(): void
    {
        $demoChoice = $this->getDemoChoice();
        $this->warnIfPackMismatch($demoChoice);

        $this->command->info('');
        $this->command->info('╔═══════════════════════════════════════════════════════════════════╗');
        $this->command->info('║                     ENTER365 DEMO DATA                             ║');
        $this->command->info('║         Indonesian Accounting System - SAK EMKM Compliant          ║');
        $this->command->info('║         Profile: '.str_pad(strtoupper($demoChoice), 48).'║');
        $this->command->info('╚═══════════════════════════════════════════════════════════════════╝');
        $this->command->info('');

        // Seed master data first (shared)
        $this->command->info('📦 Seeding Master Data...');
        $this->call(MasterDataSeeder::class);
        $this->command->info('');

        // Component library is manufacturing/BOM-oriented
        if (Features::enabled('bom') && $demoChoice !== self::DEMO_GENERAL) {
            $this->command->info('🔌 Seeding Component Library (Cross-Reference System)...');
            $this->call(ComponentLibrarySeeder::class);
            $this->command->info('');
        } else {
            $this->command->warn('  ⏭  Skipping Component Library (bom pack off or general demo)');
        }

        // Seed Vahana-specific data (Electrical Panel Maker)
        if ($demoChoice === self::DEMO_VAHANA || $demoChoice === self::DEMO_ALL) {
            $this->seedVahana();
        }

        // Seed NEX-specific data (Solar EPC Contractor)
        if ($demoChoice === self::DEMO_NEX || $demoChoice === self::DEMO_ALL) {
            $this->seedNex();
        }

        if ($demoChoice === self::DEMO_GENERAL) {
            $this->command->info('🏢 General demo: master data only for vertical products; core ops seeders next.');
            $this->command->info('');
        }

        // Extended features (respects feature flags inside seeder)
        $this->command->info('📊 Seeding Extended Features (Projects, Down Payments, Work Orders, Stock Opnames)...');
        $this->call(DemoExtendedTransactionSeeder::class);

        // Advanced accounting (bank recon, budgets, multi-currency — general-friendly)
        $this->command->info('💰 Seeding Advanced Accounting (Multi-Currency, FX Reval, Bank Recon, Budgets)...');
        $this->call(DemoAdvancedAccountingSeeder::class);

        // Advanced ops (MRP, subcontracting) — only when packs on and not pure general
        if ($demoChoice !== self::DEMO_GENERAL && (
            Features::enabled('mrp')
            || Features::enabled('subcontracting')
            || Features::enabled('recurring')
        )) {
            $this->command->info('🔧 Seeding Advanced Operations (Landed Costs, MRP, Subcontracting, Recurring)...');
            $this->call(DemoAdvancedOperationsSeeder::class);
        } else {
            $this->command->warn('  ⏭  Skipping Advanced Operations (general profile or packs off)');
        }

        // Alternate paths (includes solar proposals when pack on)
        if ($demoChoice !== self::DEMO_GENERAL) {
            $this->command->info('🔀 Seeding Alternate Paths (Rejections, Cancellations, Voids, Solar Proposals)...');
            $this->call(DemoAlternatePathsSeeder::class);
        } else {
            $this->command->warn('  ⏭  Skipping Alternate Paths (use demo=vahana|nex|all for edge-path demos)');
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

        // Try to use interactive prompt
        try {
            $this->command->info('');
            $this->command->info('Which demo data would you like to seed?');
            $this->command->info('  (Product packs: FEATURE_PRESET='.config('features.preset', 'general').')');
            $this->command->info('');

            return $this->command->choice(
                'Select demo data',
                [
                    self::DEMO_GENERAL => '🏢 General - SME trading/jasa (default product posture)',
                    self::DEMO_VAHANA => '⚡ Vahana - Electrical Panel Maker (vahana.co.id)',
                    self::DEMO_NEX => '☀️  NEX - Solar EPC Contractor (energimasadepan.com)',
                    self::DEMO_ALL => '🔄 Full - Vahana + NEX + packs',
                ],
                self::DEMO_GENERAL
            );
        } catch (\Throwable) {
            // Non-interactive: follow FEATURE_PRESET so CI/local match product defaults
            return match (config('features.preset', 'general')) {
                'manufacturing' => self::DEMO_VAHANA,
                'solar' => self::DEMO_NEX,
                'full' => self::DEMO_ALL,
                default => self::DEMO_GENERAL,
            };
        }
    }

    /**
     * Warn when demo vertical needs packs that are currently disabled.
     */
    protected function warnIfPackMismatch(string $demoChoice): void
    {
        if (in_array($demoChoice, [self::DEMO_VAHANA, self::DEMO_ALL], true)
            && Features::disabled('work_orders') && Features::disabled('bom')) {
            $this->command?->warn('  ⚠  Manufacturing packs are OFF. Enable FEATURE_PRESET=manufacturing|full for full Vahana flows.');
        }

        if (in_array($demoChoice, [self::DEMO_NEX, self::DEMO_ALL], true)
            && Features::disabled('solar_proposals')) {
            $this->command?->warn('  ⚠  solar_proposals pack is OFF. Enable FEATURE_PRESET=solar|full for NEX solar flows.');
        }
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

        if ($demoChoice === self::DEMO_GENERAL) {
            $this->command->info('║    🏢 General SME: master data + core accounting demo paths         ║');
        }
        if ($demoChoice === self::DEMO_VAHANA || $demoChoice === self::DEMO_ALL) {
            $this->command->info('║    ⚡ PT Vahana: Switchboards, MCC, ATS, Capacitor Bank panels      ║');
        }
        if ($demoChoice === self::DEMO_NEX || $demoChoice === self::DEMO_ALL) {
            $this->command->info('║    ☀️  PT NEX: PLTS Rooftop, Ground Mount, Lease-to-Own solar       ║');
        }

        $this->command->info('╠═══════════════════════════════════════════════════════════════════╣');
        $this->command->info('║  Tip: set FEATURE_PRESET=manufacturing|solar|full for vertical UIs ║');
        $this->command->info('╚═══════════════════════════════════════════════════════════════════╝');
        $this->command->info('');
    }
}
