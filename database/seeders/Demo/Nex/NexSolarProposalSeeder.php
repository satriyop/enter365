<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Nex;

use App\Contracts\Solar\SolarProposalServiceInterface;
use App\Models\Contacts\Contact;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomVariantGroup;
use App\Models\Solar\SolarProposal;
use App\Models\User;
use App\Support\Features;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Seeds multiple solar proposals for NEX / solar_proposals demos.
 */
class NexSolarProposalSeeder extends Seeder
{
    public function run(): void
    {
        if (Features::disabled('solar_proposals')) {
            $this->command?->warn('  ⏭  Skipping NEX solar proposals (solar_proposals off)');

            return;
        }

        try {
            $service = app(SolarProposalServiceInterface::class);
        } catch (\Throwable) {
            $this->command?->warn('  ⏭  Solar proposal service not bound, skipping');

            return;
        }

        $admin = User::where('email', 'admin@demo.com')->first()
            ?? User::where('email', 'sales@demo.com')->first()
            ?? User::where('email', 'admin@example.com')->first();

        if (! $admin) {
            $this->command?->warn('  ⏭  No demo user for solar proposals');

            return;
        }

        Auth::guard('web')->login($admin);

        try {
            $customers = Contact::query()
                ->whereIn('type', [Contact::TYPE_CUSTOMER, Contact::TYPE_BOTH])
                ->orderBy('id')
                ->limit(5)
                ->get();

            if ($customers->isEmpty()) {
                $this->command?->warn('  ⏭  No customers for solar proposals');

                return;
            }

            $variantGroup = BomVariantGroup::whereHas('boms')->first();
            $bom = $variantGroup
                ? Bom::where('variant_group_id', $variantGroup->id)
                    ->whereIn('status', ['active', 'approved'])
                    ->first()
                : Bom::whereIn('status', ['active', 'approved'])->first();

            $sites = [
                [
                    'site_name' => 'Pabrik Demo Cikarang',
                    'site_address' => 'Kawasan Industri Jababeka, Cikarang',
                    'province' => 'Jawa Barat',
                    'city' => 'Bekasi',
                    'latitude' => -6.3200,
                    'longitude' => 107.1700,
                    'roof_area_m2' => 5000,
                    'monthly_consumption_kwh' => 85000,
                    'pln_tariff_category' => 'I-3/TM',
                    'electricity_rate' => 1115,
                ],
                [
                    'site_name' => 'Gudang Logistik Surabaya',
                    'site_address' => 'Jl. Rungkut Industri, Surabaya',
                    'province' => 'Jawa Timur',
                    'city' => 'Surabaya',
                    'latitude' => -7.3200,
                    'longitude' => 112.7800,
                    'roof_area_m2' => 2500,
                    'monthly_consumption_kwh' => 42000,
                    'pln_tariff_category' => 'I-2/TM',
                    'electricity_rate' => 1114,
                ],
                [
                    'site_name' => 'Kantor & Rooftop Jakarta',
                    'site_address' => 'SCBD, Jakarta Selatan',
                    'province' => 'DKI Jakarta',
                    'city' => 'Jakarta Selatan',
                    'latitude' => -6.2250,
                    'longitude' => 106.8090,
                    'roof_area_m2' => 800,
                    'monthly_consumption_kwh' => 12000,
                    'pln_tariff_category' => 'B-2/TR',
                    'electricity_rate' => 1444,
                ],
            ];

            $created = 0;
            foreach ($sites as $index => $site) {
                $customer = $customers[$index % $customers->count()];

                $existing = SolarProposal::query()
                    ->where('site_name', $site['site_name'])
                    ->where('contact_id', $customer->id)
                    ->first();

                if ($existing) {
                    continue;
                }

                try {
                    $proposal = $service->create(array_merge($site, [
                        'contact_id' => $customer->id,
                        'roof_type' => 'flat',
                        'roof_orientation' => 'north',
                        'roof_tilt_degrees' => 10,
                        'shading_percentage' => 5,
                        'tariff_escalation_percent' => 5.0,
                        'notes' => 'Demo NEX solar proposal (seeded)',
                        'created_by' => $admin->id,
                    ]));

                    try {
                        $proposal = $service->calculateProposal($proposal);
                    } catch (\Throwable) {
                        // calculation may need irradiance masters; keep draft
                    }

                    if ($variantGroup && $bom) {
                        try {
                            $service->attachVariantGroup($proposal, $variantGroup->id);
                            $service->selectBom($proposal, $bom->id);
                        } catch (\Throwable) {
                            // optional enrichment when variant BOMs absent
                        }
                    }

                    if ($index === 0 && method_exists($service, 'send')) {
                        try {
                            $service->send($proposal);
                        } catch (\Throwable) {
                            // leave as draft/calculated
                        }
                    }

                    $created++;
                } catch (\Throwable $e) {
                    $this->command?->warn('    Solar proposal seed failed: '.$e->getMessage());
                }
            }

            $this->command?->info("    Created/ensured {$created} solar proposal(s); total=".SolarProposal::query()->count());
        } finally {
            Auth::guard('web')->logout();
        }
    }
}
