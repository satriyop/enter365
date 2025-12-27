<?php

namespace Database\Seeders;

use App\Models\Accounting\PlnTariff;
use Illuminate\Database\Seeder;

class PlnTariffSeeder extends Seeder
{
    /**
     * Seed PLN electricity tariff data.
     *
     * Based on PLN tariff adjustment effective 2024
     * Source: PLN official website and ESDM regulations
     *
     * Tariff codes:
     * R = Rumah Tangga (Residential)
     * B = Bisnis (Business)
     * I = Industri (Industrial)
     * S = Sosial (Social)
     * P = Pemerintah (Government)
     * TR = Tegangan Rendah (Low Voltage)
     * TM = Tegangan Menengah (Medium Voltage)
     * TT = Tegangan Tinggi (High Voltage)
     */
    public function run(): void
    {
        $tariffs = [
            // ========================================
            // RESIDENTIAL (Rumah Tangga)
            // ========================================
            [
                'category_code' => 'R-1/TR 450',
                'category_name' => 'Rumah Tangga 450 VA (Subsidi)',
                'customer_type' => 'residential',
                'power_va_min' => 450,
                'power_va_max' => 450,
                'rate_per_kwh' => 415,
                'is_active' => true,
                'notes' => 'Tarif bersubsidi untuk rumah tangga daya rendah',
            ],
            [
                'category_code' => 'R-1/TR 900',
                'category_name' => 'Rumah Tangga 900 VA (Subsidi)',
                'customer_type' => 'residential',
                'power_va_min' => 900,
                'power_va_max' => 900,
                'rate_per_kwh' => 605,
                'is_active' => true,
                'notes' => 'Tarif bersubsidi untuk rumah tangga daya 900 VA',
            ],
            [
                'category_code' => 'R-1/TR 1300',
                'category_name' => 'Rumah Tangga 1300 VA',
                'customer_type' => 'residential',
                'power_va_min' => 1300,
                'power_va_max' => 1300,
                'rate_per_kwh' => 1444,
                'is_active' => true,
                'notes' => 'Tarif non-subsidi',
            ],
            [
                'category_code' => 'R-1/TR 2200',
                'category_name' => 'Rumah Tangga 2200 VA',
                'customer_type' => 'residential',
                'power_va_min' => 2200,
                'power_va_max' => 2200,
                'rate_per_kwh' => 1444,
                'is_active' => true,
                'notes' => 'Tarif non-subsidi',
            ],
            [
                'category_code' => 'R-2/TR',
                'category_name' => 'Rumah Tangga 3500-5500 VA',
                'customer_type' => 'residential',
                'power_va_min' => 3500,
                'power_va_max' => 5500,
                'rate_per_kwh' => 1699,
                'is_active' => true,
                'notes' => 'Tarif non-subsidi untuk daya menengah',
            ],
            [
                'category_code' => 'R-3/TR',
                'category_name' => 'Rumah Tangga > 6600 VA',
                'customer_type' => 'residential',
                'power_va_min' => 6600,
                'power_va_max' => null,
                'rate_per_kwh' => 1699,
                'is_active' => true,
                'notes' => 'Tarif non-subsidi untuk daya tinggi',
            ],

            // ========================================
            // BUSINESS (Bisnis)
            // ========================================
            [
                'category_code' => 'B-1/TR',
                'category_name' => 'Bisnis 450-5500 VA',
                'customer_type' => 'business',
                'power_va_min' => 450,
                'power_va_max' => 5500,
                'rate_per_kwh' => 1444,
                'is_active' => true,
                'notes' => 'Tarif bisnis daya rendah',
            ],
            [
                'category_code' => 'B-2/TR',
                'category_name' => 'Bisnis 6600-200000 VA',
                'customer_type' => 'business',
                'power_va_min' => 6600,
                'power_va_max' => 200000,
                'rate_per_kwh' => 1699,
                'is_active' => true,
                'notes' => 'Tarif bisnis daya menengah',
            ],
            [
                'category_code' => 'B-3/TM',
                'category_name' => 'Bisnis > 200 kVA (Tegangan Menengah)',
                'customer_type' => 'business',
                'power_va_min' => 200000,
                'power_va_max' => null,
                'rate_per_kwh' => 1621,
                'capacity_charge' => 40800,
                'is_active' => true,
                'notes' => 'Tarif bisnis tegangan menengah per kVA',
            ],

            // ========================================
            // INDUSTRIAL (Industri)
            // ========================================
            [
                'category_code' => 'I-1/TR',
                'category_name' => 'Industri 450-14000 VA',
                'customer_type' => 'industrial',
                'power_va_min' => 450,
                'power_va_max' => 14000,
                'rate_per_kwh' => 1444,
                'is_active' => true,
                'notes' => 'Industri kecil tegangan rendah',
            ],
            [
                'category_code' => 'I-2/TR',
                'category_name' => 'Industri 14000-200000 VA',
                'customer_type' => 'industrial',
                'power_va_min' => 14000,
                'power_va_max' => 200000,
                'rate_per_kwh' => 1699,
                'is_active' => true,
                'notes' => 'Industri menengah tegangan rendah',
            ],
            [
                'category_code' => 'I-3/TM',
                'category_name' => 'Industri > 200 kVA (Tegangan Menengah)',
                'customer_type' => 'industrial',
                'power_va_min' => 200000,
                'power_va_max' => null,
                'rate_per_kwh' => 1621,
                'capacity_charge' => 40800,
                'peak_rate_per_kwh' => 1699,
                'off_peak_rate_per_kwh' => 1444,
                'peak_hours' => '18:00-22:00',
                'is_active' => true,
                'notes' => 'Industri besar dengan TOU (Time of Use)',
            ],
            [
                'category_code' => 'I-4/TT',
                'category_name' => 'Industri Tegangan Tinggi',
                'customer_type' => 'industrial',
                'power_va_min' => 30000000,
                'power_va_max' => null,
                'rate_per_kwh' => 1428,
                'capacity_charge' => 35700,
                'peak_rate_per_kwh' => 1530,
                'off_peak_rate_per_kwh' => 1260,
                'peak_hours' => '18:00-22:00',
                'is_active' => true,
                'notes' => 'Industri tegangan tinggi 150 kV ke atas',
            ],

            // ========================================
            // SOCIAL (Sosial)
            // ========================================
            [
                'category_code' => 'S-1/TR',
                'category_name' => 'Sosial 450-220000 VA',
                'customer_type' => 'social',
                'power_va_min' => 450,
                'power_va_max' => 220000,
                'rate_per_kwh' => 1032,
                'is_active' => true,
                'notes' => 'Yayasan sosial, panti asuhan, rumah ibadah',
            ],
            [
                'category_code' => 'S-2/TM',
                'category_name' => 'Sosial > 200 kVA',
                'customer_type' => 'social',
                'power_va_min' => 200000,
                'power_va_max' => null,
                'rate_per_kwh' => 987,
                'capacity_charge' => 28600,
                'is_active' => true,
                'notes' => 'Instansi sosial besar',
            ],

            // ========================================
            // GOVERNMENT (Pemerintah)
            // ========================================
            [
                'category_code' => 'P-1/TR',
                'category_name' => 'Pemerintah 450-200000 VA',
                'customer_type' => 'government',
                'power_va_min' => 450,
                'power_va_max' => 200000,
                'rate_per_kwh' => 1444,
                'is_active' => true,
                'notes' => 'Kantor pemerintah, sekolah negeri',
            ],
            [
                'category_code' => 'P-2/TM',
                'category_name' => 'Pemerintah > 200 kVA',
                'customer_type' => 'government',
                'power_va_min' => 200000,
                'power_va_max' => null,
                'rate_per_kwh' => 1360,
                'capacity_charge' => 38600,
                'is_active' => true,
                'notes' => 'Instansi pemerintah besar',
            ],
            [
                'category_code' => 'P-3/TR',
                'category_name' => 'Penerangan Jalan Umum',
                'customer_type' => 'government',
                'power_va_min' => 450,
                'power_va_max' => null,
                'rate_per_kwh' => 1444,
                'is_active' => true,
                'notes' => 'PJU (Penerangan Jalan Umum)',
            ],
        ];

        foreach ($tariffs as $tariff) {
            PlnTariff::updateOrCreate(
                ['category_code' => $tariff['category_code']],
                $tariff
            );
        }
    }
}
