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
                            {--demo= : Which demo to seed (general, vahana, nex, all)}
                            {--fresh : Run migrate:fresh before seeding}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed demo data (general SME, Vahana, NEX, or full)';

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

        $valid = [
            DemoSeeder::DEMO_GENERAL,
            DemoSeeder::DEMO_VAHANA,
            DemoSeeder::DEMO_NEX,
            DemoSeeder::DEMO_ALL,
        ];

        // Validate demo option if provided
        if ($demo && ! in_array($demo, $valid, true)) {
            $this->error("Invalid demo option: {$demo}");
            $this->info('Valid options: general, vahana, nex, all');

            return self::FAILURE;
        }

        // If no demo option provided, show interactive prompt
        if (! $demo) {
            $this->info('');
            $this->info('Which demo data would you like to seed?');
            $this->info('  FEATURE_PRESET='.config('features.preset', 'general'));
            $this->info('');

            $demo = $this->choice(
                'Select demo data',
                [
                    DemoSeeder::DEMO_GENERAL => '🏢 General - SME trading/jasa (default)',
                    DemoSeeder::DEMO_VAHANA => '⚡ Vahana - Electrical Panel Maker (vahana.co.id)',
                    DemoSeeder::DEMO_NEX => '☀️  NEX - Solar EPC Contractor (energimasadepan.com)',
                    DemoSeeder::DEMO_ALL => '🔄 Full - Vahana + NEX + packs',
                ],
                DemoSeeder::DEMO_GENERAL
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
