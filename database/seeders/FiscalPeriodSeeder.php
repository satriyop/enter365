<?php

namespace Database\Seeders;

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
        
        // Previous year (closed)
        FiscalPeriod::create([
            'name' => 'Tahun Fiskal ' . ($currentYear - 1),
            'start_date' => ($currentYear - 1) . '-01-01',
            'end_date' => ($currentYear - 1) . '-12-31',
            'is_closed' => true,
        ]);

        // Current year (open)
        FiscalPeriod::create([
            'name' => 'Tahun Fiskal ' . $currentYear,
            'start_date' => $currentYear . '-01-01',
            'end_date' => $currentYear . '-12-31',
            'is_closed' => false,
        ]);
    }
}
