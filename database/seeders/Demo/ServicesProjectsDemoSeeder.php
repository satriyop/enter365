<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Contracts\Projects\ProjectServiceInterface;
use App\Enums\DocumentStatus;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Projects\Project;
use App\Models\User;
use App\Support\Features;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Lightweight services/jasa demo when projects pack is on (FEATURE_PRESET=services).
 * Does not seed manufacturing industry data.
 */
class ServicesProjectsDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Features::disabled('projects')) {
            $this->command?->warn('  ⏭  Skipping services projects demo (projects pack off)');

            return;
        }

        $this->command?->info('🏗️  Seeding services/jasa project demos...');

        try {
            $projectService = app(ProjectServiceInterface::class);
        } catch (\Throwable) {
            $this->command?->warn('  ⏭  Project service unavailable');

            return;
        }

        $admin = User::where('email', 'admin@demo.com')->first()
            ?? User::where('email', 'admin@example.com')->first();

        if (! $admin) {
            return;
        }

        Auth::guard('web')->login($admin);

        try {
            $customer = Contact::query()->where('type', 'customer')->first();
            if (! $customer) {
                $customer = Contact::factory()->customer()->create([
                    'code' => 'C-SVC-01',
                    'name' => 'Customer Jasa Demo',
                ]);
            }

            Product::updateOrCreate(
                ['sku' => 'SVC-INSTALL'],
                [
                    'name' => 'Installation Service',
                    'type' => Product::TYPE_SERVICE,
                    'unit' => 'job',
                    'purchase_price' => 0,
                    'selling_price' => 5_000_000,
                    'is_active' => true,
                    'track_inventory' => false,
                    'is_sellable' => true,
                    'is_purchasable' => false,
                ]
            );

            if (Project::query()->where('name', 'Instalasi & Commissioning Demo')->exists()) {
                $this->command?->info('    Services project already exists');

                return;
            }

            $project = $projectService->create([
                'name' => 'Instalasi & Commissioning Demo',
                'description' => 'Demo project for FEATURE_PRESET=services / services demo profile',
                'contact_id' => $customer->id,
                'start_date' => now()->subDays(7)->toDateString(),
                'end_date' => now()->addDays(30)->toDateString(),
                'budget_amount' => 50_000_000,
                'contract_amount' => 65_000_000,
                'priority' => Project::PRIORITY_NORMAL,
                'location' => 'Jakarta',
                'notes' => 'Seeded services demo project',
                'manager_id' => $admin->id,
                'created_by' => $admin->id,
                'status' => DocumentStatus::Planning->value ?? 'planning',
            ]);

            $this->command?->info('    Created services project: '.($project->project_number ?? $project->name));
        } finally {
            Auth::guard('web')->logout();
        }
    }
}
