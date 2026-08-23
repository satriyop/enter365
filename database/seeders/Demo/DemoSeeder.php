<?php

namespace Database\Seeders\Demo;

use App\Support\Features;
use Database\Seeders\Demo\Nex\NexContactSeeder;
use Database\Seeders\Demo\Nex\NexProductSeeder;
use Database\Seeders\Demo\Nex\NexSolarProposalSeeder;
use Database\Seeders\Demo\Nex\NexTransactionSeeder;
use Database\Seeders\Demo\Vahana\VahanaContactSeeder;
use Database\Seeders\Demo\Vahana\VahanaProductSeeder;
use Database\Seeders\Demo\Vahana\VahanaTransactionSeeder;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    /** General SME trading/jasa — FEATURE_PRESET=general */
    public const DEMO_GENERAL = 'general';

    /** Services/jasa + projects — FEATURE_PRESET=services */
    public const DEMO_SERVICES = 'services';

    /** Generic shop floor (no industry vertical) — FEATURE_PRESET=manufacturing */
    public const DEMO_MANUFACTURING = 'manufacturing';

    /** Odoo-like enterprise packs without industry vertical masters */
    public const DEMO_ENTERPRISE = 'enterprise';

    public const DEMO_VAHANA = 'vahana';

    public const DEMO_NEX = 'nex';

    public const DEMO_ALL = 'all';

    /** Kasir-first stand-in: Kopitiam 57 till catalog */
    public const DEMO_POS = 'pos';

    /**
     * @return list<string>
     */
    public static function profiles(): array
    {
        return [
            self::DEMO_GENERAL,
            self::DEMO_SERVICES,
            self::DEMO_MANUFACTURING,
            self::DEMO_ENTERPRISE,
            self::DEMO_VAHANA,
            self::DEMO_NEX,
            self::DEMO_ALL,
            self::DEMO_POS,
        ];
    }

    /**
     * Map FEATURE_PRESET → demo profile.
     */
    public static function profileFromFeaturePreset(?string $preset = null): string
    {
        $preset = strtolower((string) ($preset ?? config('features.preset', 'general')));

        return match ($preset) {
            'services' => self::DEMO_SERVICES,
            'manufacturing' => self::DEMO_MANUFACTURING,
            'enterprise' => self::DEMO_ENTERPRISE,
            'vahana' => self::DEMO_VAHANA,
            'solar', 'nex' => self::DEMO_NEX,
            'full' => self::DEMO_ALL,
            'pos' => self::DEMO_POS,
            default => self::DEMO_GENERAL,
        };
    }

    /**
     * Seed demo data for the application.
     *
     * Profiles:
     * - general: master + trading cycles (no MFG/industry)
     * - services: trading + projects/jasa demos
     * - manufacturing: trading + generic BOM/WO (no Vahana/NEX)
     * - enterprise: trading + generic MFG + projects packs (no industry masters)
     * - vahana: panel manufacturing + electrical_panel
     * - nex: solar EPC + solar_proposals
     * - all: both verticals + full packs
     *
     * Usage:
     *   php artisan seed:demo --demo=general
     *   FEATURE_PRESET=enterprise php artisan seed:demo
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
        $this->command->info('║         FEATURE_PRESET='.str_pad((string) config('features.preset', 'general'), 40).'║');
        $this->command->info('╚═══════════════════════════════════════════════════════════════════╝');
        $this->command->info('');

        // Seed master data first (shared)
        $this->command->info('📦 Seeding Master Data...');
        $this->call(MasterDataSeeder::class);
        $this->command->info('');

        // Component library = Vahana electrical_panel only
        $seedPanelLibrary = Features::enabled('electrical_panel')
            && in_array($demoChoice, [self::DEMO_VAHANA, self::DEMO_ALL], true);

        if ($seedPanelLibrary) {
            $this->command->info('🔌 Seeding Component Library (electrical_panel add-on)...');
            $this->call(ComponentLibrarySeeder::class);
            $this->command->info('');
        } else {
            $this->command->warn('  ⏭  Skipping Component Library (not vahana/all or electrical_panel off)');
        }

        // Industry verticals
        if ($demoChoice === self::DEMO_VAHANA || $demoChoice === self::DEMO_ALL) {
            $this->seedVahana();
        }

        if ($demoChoice === self::DEMO_NEX || $demoChoice === self::DEMO_ALL) {
            $this->seedNex();
        }

        if ($demoChoice === self::DEMO_POS) {
            $this->command->info('☕ Seeding Kopitiam 57 stand-in till (DEMO — bukan harga toko)...');
            $this->call(PosKopitiamDemoSeeder::class);
            $this->command->info('');
            $this->showCompletionMessage($demoChoice);

            return;
        }

        // Core trading for non-vertical-only paths (also used as base for services/enterprise/mfg)
        if (in_array($demoChoice, [
            self::DEMO_GENERAL,
            self::DEMO_SERVICES,
            self::DEMO_MANUFACTURING,
            self::DEMO_ENTERPRISE,
        ], true)) {
            $label = match ($demoChoice) {
                self::DEMO_ENTERPRISE => 'enterprise trading + pack-friendly cycles (no industry masters)',
                self::DEMO_MANUFACTURING => 'manufacturing trading base (generic shop floor next)',
                self::DEMO_SERVICES => 'services trading base (projects next)',
                default => 'general trading cycles (no NEX/Vahana vertical)',
            };
            $this->command->info("🏢 Seeding {$label}...");
            $this->call(GeneralTradingDemoSeeder::class);
            $this->command->info('');
        }

        // Generic manufacturing BOM masters (enterprise / manufacturing / all-with-packs)
        if ($this->shouldSeedGenericManufacturing($demoChoice)) {
            $this->command->info('🏭 Seeding generic manufacturing (BOM/materials for WO/MRP demos)...');
            $this->call(EnterpriseManufacturingDemoSeeder::class);
            $this->command->info('');
        }

        // Services project demos
        if (in_array($demoChoice, [self::DEMO_SERVICES, self::DEMO_ENTERPRISE, self::DEMO_ALL], true)
            && Features::enabled('projects')) {
            $this->call(ServicesProjectsDemoSeeder::class);
            $this->command->info('');
        }

        // Extended features (respects feature flags inside seeder)
        $this->command->info('📊 Seeding Extended Features (Projects, Down Payments, Work Orders, Stock Opnames)...');
        $this->call(DemoExtendedTransactionSeeder::class);

        // Advanced accounting (bank recon, budgets, multi-currency — general-friendly)
        $this->command->info('💰 Seeding Advanced Accounting (Multi-Currency, FX Reval, Bank Recon, Budgets)...');
        $this->call(DemoAdvancedAccountingSeeder::class);

        // Advanced ops (MRP, subcontracting) — odoo packs; skip pure general/services without mfg
        if ($this->shouldSeedAdvancedOperations($demoChoice)) {
            $this->command->info('🔧 Seeding Advanced Operations (Landed Costs, MRP, Subcontracting, Recurring)...');
            $this->call(DemoAdvancedOperationsSeeder::class);
        } else {
            $this->command->warn('  ⏭  Skipping Advanced Operations (profile or packs off)');
        }

        // Alternate paths (edge cases + optional solar when available)
        if (in_array($demoChoice, [
            self::DEMO_VAHANA,
            self::DEMO_NEX,
            self::DEMO_ALL,
            self::DEMO_ENTERPRISE,
            self::DEMO_MANUFACTURING,
        ], true)) {
            $this->command->info('🔀 Seeding Alternate Paths (Rejections, Cancellations, Voids, Solar when on)...');
            $this->call(DemoAlternatePathsSeeder::class);
        } else {
            $this->command->warn('  ⏭  Skipping Alternate Paths (edge demos for enterprise/mfg/verticals)');
        }

        $this->showCompletionMessage($demoChoice);
    }

    protected function shouldSeedGenericManufacturing(string $demoChoice): bool
    {
        if (! Features::enabled('bom') && ! Features::enabled('manufacturing')) {
            return false;
        }

        // Vahana/NEX already seed their own BOMs; still allow generic BOM for enterprise-style packs on `all`
        return in_array($demoChoice, [
            self::DEMO_MANUFACTURING,
            self::DEMO_ENTERPRISE,
            self::DEMO_ALL,
        ], true);
    }

    protected function shouldSeedAdvancedOperations(string $demoChoice): bool
    {
        if (in_array($demoChoice, [self::DEMO_GENERAL, self::DEMO_SERVICES], true)) {
            return false;
        }

        return Features::enabled('mrp')
            || Features::enabled('subcontracting')
            || Features::enabled('recurring');
    }

    /**
     * Get the demo choice from app instance, command option, or interactive prompt.
     */
    protected function getDemoChoice(): string
    {
        if (app()->bound('demo.choice')) {
            $choice = (string) app('demo.choice');
            if (in_array($choice, self::profiles(), true)) {
                return $choice;
            }
        }

        try {
            $this->command->info('');
            $this->command->info('Which demo data would you like to seed?');
            $this->command->info('  (Product packs: FEATURE_PRESET='.config('features.preset', 'general').')');
            $this->command->info('  Recommended default for this preset: '.self::profileFromFeaturePreset());
            $this->command->info('');

            return $this->command->choice(
                'Select demo data',
                [
                    self::DEMO_GENERAL => '🏢 General - SME trading/jasa',
                    self::DEMO_SERVICES => '🏗️  Services - trading + projects',
                    self::DEMO_MANUFACTURING => '⚙️  Manufacturing - generic shop floor (no vertical)',
                    self::DEMO_ENTERPRISE => '🏭 Enterprise - Odoo-like packs, no industry masters',
                    self::DEMO_VAHANA => '⚡ Vahana - Electrical Panel (electrical_panel)',
                    self::DEMO_NEX => '☀️  NEX - Solar EPC (solar_proposals)',
                    self::DEMO_ALL => '🔄 Full - Vahana + NEX + packs',
                    self::DEMO_POS => '☕ POS - Kopitiam 57 stand-in till',
                ],
                self::profileFromFeaturePreset()
            );
        } catch (\Throwable) {
            return self::profileFromFeaturePreset();
        }
    }

    protected function warnIfPackMismatch(string $demoChoice): void
    {
        $recommended = self::profileFromFeaturePreset();
        if ($demoChoice !== $recommended) {
            $this->command?->warn(
                "  ⚠  Demo profile '{$demoChoice}' differs from FEATURE_PRESET mapping '{$recommended}'. Data may not match UI gates."
            );
        }

        if (in_array($demoChoice, [self::DEMO_VAHANA, self::DEMO_ALL], true)
            && Features::disabled('electrical_panel')) {
            $this->command?->warn('  ⚠  electrical_panel is OFF. Enable FEATURE_PRESET=vahana|full for panel library / brand-swap.');
        }

        if (in_array($demoChoice, [self::DEMO_VAHANA, self::DEMO_MANUFACTURING, self::DEMO_ENTERPRISE, self::DEMO_ALL], true)
            && Features::disabled('work_orders') && Features::disabled('bom')) {
            $this->command?->warn('  ⚠  Manufacturing packs are OFF. Enable FEATURE_PRESET=manufacturing|enterprise|vahana|full.');
        }

        if (in_array($demoChoice, [self::DEMO_NEX, self::DEMO_ALL], true)
            && Features::disabled('solar_proposals')) {
            $this->command?->warn('  ⚠  solar_proposals is OFF. Enable FEATURE_PRESET=solar|nex|full for NEX solar flows.');
        }

        if (in_array($demoChoice, [self::DEMO_SERVICES, self::DEMO_ENTERPRISE, self::DEMO_ALL], true)
            && Features::disabled('projects')) {
            $this->command?->warn('  ⚠  projects pack is OFF. Enable FEATURE_PRESET=services|enterprise|full for project demos.');
        }
    }

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

        $this->command->info('  → Solar Proposals (draft/sent demos)');
        $this->call(NexSolarProposalSeeder::class);

        $this->command->info('');
    }

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
        if ($demoChoice === self::DEMO_POS) {
            $this->command->info('║    siti@kopitiam57.test (password: password)  Kasir                 ║');
        }
        $this->command->info('╠═══════════════════════════════════════════════════════════════════╣');
        $this->command->info('║  Data Seeded:                                                      ║');

        match ($demoChoice) {
            self::DEMO_GENERAL => $this->command->info('║    🏢 General SME: trading + accounting only                          ║'),
            self::DEMO_SERVICES => $this->command->info('║    🏗️  Services: trading + projects/jasa                            ║'),
            self::DEMO_MANUFACTURING => $this->command->info('║    ⚙️  Manufacturing: generic BOM/WO (no industry vertical)         ║'),
            self::DEMO_ENTERPRISE => $this->command->info('║    🏭 Enterprise: packs (MFG/projects); no solar/panel masters      ║'),
            self::DEMO_VAHANA, self::DEMO_ALL => $this->command->info('║    ⚡ PT Vahana: panel library + BOM + brand-capable masters         ║'),
            self::DEMO_POS => $this->command->info('║    ☕ Kopitiam 57 till catalog (DEMO papan, bukan harga toko)       ║'),
            default => null,
        };

        if ($demoChoice === self::DEMO_NEX || $demoChoice === self::DEMO_ALL) {
            $this->command->info('║    ☀️  PT NEX: PLTS products + solar proposals                      ║');
        }

        $this->command->info('╠═══════════════════════════════════════════════════════════════════╣');
        $this->command->info('║  Tip: align FEATURE_PRESET with --demo for UI + data consistency   ║');
        $this->command->info('╚═══════════════════════════════════════════════════════════════════╝');
        $this->command->info('');
    }
}
