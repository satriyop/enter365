<?php

namespace Database\Seeders;

use App\Domain\Accounting\FiscalPeriods\Enums\FiscalPeriodStatus;
use App\Models\Accounting\FiscalPeriod;
use Illuminate\Database\Seeder;

class FiscalPeriodSeeder extends Seeder
{
    /**
     * Create fiscal periods for the current and previous year.
     */
    public function run(): void
    {
        $currentYear = now()->year;

        FiscalPeriod::query()->firstOrCreate(
            [
                'start_date' => ($currentYear - 1).'-01-01',
                'end_date' => ($currentYear - 1).'-12-31',
            ],
            [
                'name' => 'Tahun Fiskal '.($currentYear - 1),
                'status' => FiscalPeriodStatus::Closed,
                'is_closed' => true,
                'is_locked' => true,
                'closed_at' => now(),
            ]
        );

        FiscalPeriod::query()->firstOrCreate(
            [
                'start_date' => $currentYear.'-01-01',
                'end_date' => $currentYear.'-12-31',
            ],
            [
                'name' => 'Tahun Fiskal '.$currentYear,
                'status' => FiscalPeriodStatus::Open,
                'is_closed' => false,
                'is_locked' => false,
            ]
        );
    }
}
