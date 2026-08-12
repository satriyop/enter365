<?php

namespace App\Console\Commands;

use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Console\Command;

class SeedDemoCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seed:demo
                            {--demo= : Which demo (general, services, manufacturing, enterprise, vahana, nex, all). Default: from FEATURE_PRESET}
                            {--fresh : Run migrate:fresh before seeding}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed demo data aligned with FEATURE_PRESET (general/services/manufacturing/enterprise/vahana/nex/all)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Run fresh migration if requested
        if ($this->option('fresh')) {
            $this->info('Running migrate:fresh...');
            $this->call('migrate:fresh');
            $this->newLine();

            // Also run base seeder for foundation data
            $this->info('Running foundation seeder...');
            $this->call('db:seed');
            $this->newLine();
        }

        $demo = $this->option('demo');
        $valid = DemoSeeder::profiles();
        $recommended = DemoSeeder::profileFromFeaturePreset();

        // Validate demo option if provided
        if ($demo && ! in_array($demo, $valid, true)) {
            $this->error("Invalid demo option: {$demo}");
            $this->info('Valid options: '.implode(', ', $valid));

            return self::FAILURE;
        }

        // If no demo option provided, show interactive prompt (default = FEATURE_PRESET map)
        if (! $demo) {
            $this->info('');
            $this->info('Which demo data would you like to seed?');
            $this->info('  FEATURE_PRESET='.config('features.preset', 'general'));
            $this->info("  Recommended: {$recommended}");
            $this->info('');

            $demo = $this->choice(
                'Select demo data',
                [
                    DemoSeeder::DEMO_GENERAL => '🏢 General - SME trading/jasa',
                    DemoSeeder::DEMO_SERVICES => '🏗️  Services - trading + projects',
                    DemoSeeder::DEMO_MANUFACTURING => '⚙️  Manufacturing - generic shop floor',
                    DemoSeeder::DEMO_ENTERPRISE => '🏭 Enterprise - Odoo-like packs, no industry masters',
                    DemoSeeder::DEMO_VAHANA => '⚡ Vahana - Electrical Panel (electrical_panel)',
                    DemoSeeder::DEMO_NEX => '☀️  NEX - Solar EPC (solar_proposals)',
                    DemoSeeder::DEMO_ALL => '🔄 Full - Vahana + NEX + packs',
                ],
                $recommended
            );
        }

        // Store the demo choice in a way the seeder can access
        app()->instance('demo.choice', $demo);

        // Run the seeder
        $seeder = new DemoSeeder;
        $seeder->setCommand($this);
        $seeder->run();

        return self::SUCCESS;
    }
}
