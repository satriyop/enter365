<?php

namespace Database\Seeders;

use App\Models\Accounting\IndonesiaSolarData;
use Illuminate\Database\Seeder;

class IndonesiaSolarDataSeeder extends Seeder
{
    /**
     * Seed Indonesia solar irradiance data for major cities.
     *
     * Data sources: NASA POWER, Global Solar Atlas, BMKG
     * Peak Sun Hours: Annual average equivalent hours/day
     * Solar Irradiance: Average daily Global Horizontal Irradiance (kWh/m²/day)
     */
    public function run(): void
    {
        $solarData = [
            // SUMATERA
            ['province' => 'Aceh', 'city' => 'Banda Aceh', 'latitude' => 5.5483, 'longitude' => 95.3238, 'peak_sun_hours' => 4.2, 'solar_irradiance_kwh_m2_day' => 4.45, 'optimal_tilt_angle' => 6, 'temperature_avg' => 27.5],
            ['province' => 'Sumatera Utara', 'city' => 'Medan', 'latitude' => 3.5952, 'longitude' => 98.6722, 'peak_sun_hours' => 4.3, 'solar_irradiance_kwh_m2_day' => 4.55, 'optimal_tilt_angle' => 4, 'temperature_avg' => 27.0],
            ['province' => 'Sumatera Barat', 'city' => 'Padang', 'latitude' => -0.9471, 'longitude' => 100.4172, 'peak_sun_hours' => 4.4, 'solar_irradiance_kwh_m2_day' => 4.65, 'optimal_tilt_angle' => 1, 'temperature_avg' => 26.5],
            ['province' => 'Riau', 'city' => 'Pekanbaru', 'latitude' => 0.5071, 'longitude' => 101.4478, 'peak_sun_hours' => 4.3, 'solar_irradiance_kwh_m2_day' => 4.50, 'optimal_tilt_angle' => 1, 'temperature_avg' => 27.5],
            ['province' => 'Kepulauan Riau', 'city' => 'Batam', 'latitude' => 1.0456, 'longitude' => 104.0305, 'peak_sun_hours' => 4.4, 'solar_irradiance_kwh_m2_day' => 4.60, 'optimal_tilt_angle' => 1, 'temperature_avg' => 27.0],
            ['province' => 'Jambi', 'city' => 'Jambi', 'latitude' => -1.6101, 'longitude' => 103.6131, 'peak_sun_hours' => 4.4, 'solar_irradiance_kwh_m2_day' => 4.60, 'optimal_tilt_angle' => 2, 'temperature_avg' => 27.0],
            ['province' => 'Sumatera Selatan', 'city' => 'Palembang', 'latitude' => -2.9761, 'longitude' => 104.7754, 'peak_sun_hours' => 4.5, 'solar_irradiance_kwh_m2_day' => 4.70, 'optimal_tilt_angle' => 3, 'temperature_avg' => 27.5],
            ['province' => 'Bengkulu', 'city' => 'Bengkulu', 'latitude' => -3.7928, 'longitude' => 102.2608, 'peak_sun_hours' => 4.4, 'solar_irradiance_kwh_m2_day' => 4.60, 'optimal_tilt_angle' => 4, 'temperature_avg' => 26.5],
            ['province' => 'Lampung', 'city' => 'Bandar Lampung', 'latitude' => -5.4500, 'longitude' => 105.2667, 'peak_sun_hours' => 4.5, 'solar_irradiance_kwh_m2_day' => 4.75, 'optimal_tilt_angle' => 5, 'temperature_avg' => 27.0],
            ['province' => 'Bangka Belitung', 'city' => 'Pangkal Pinang', 'latitude' => -2.1316, 'longitude' => 106.1169, 'peak_sun_hours' => 4.4, 'solar_irradiance_kwh_m2_day' => 4.65, 'optimal_tilt_angle' => 2, 'temperature_avg' => 27.0],

            // JAWA
            ['province' => 'DKI Jakarta', 'city' => 'Jakarta', 'latitude' => -6.2088, 'longitude' => 106.8456, 'peak_sun_hours' => 4.6, 'solar_irradiance_kwh_m2_day' => 4.85, 'optimal_tilt_angle' => 6, 'temperature_avg' => 28.0],
            ['province' => 'Banten', 'city' => 'Tangerang', 'latitude' => -6.1781, 'longitude' => 106.6319, 'peak_sun_hours' => 4.6, 'solar_irradiance_kwh_m2_day' => 4.85, 'optimal_tilt_angle' => 6, 'temperature_avg' => 28.0],
            ['province' => 'Banten', 'city' => 'Serang', 'latitude' => -6.1201, 'longitude' => 106.1503, 'peak_sun_hours' => 4.6, 'solar_irradiance_kwh_m2_day' => 4.80, 'optimal_tilt_angle' => 6, 'temperature_avg' => 27.5],
            ['province' => 'Jawa Barat', 'city' => 'Bandung', 'latitude' => -6.9175, 'longitude' => 107.6191, 'peak_sun_hours' => 4.5, 'solar_irradiance_kwh_m2_day' => 4.75, 'optimal_tilt_angle' => 7, 'temperature_avg' => 23.5],
            ['province' => 'Jawa Barat', 'city' => 'Bekasi', 'latitude' => -6.2348, 'longitude' => 107.0000, 'peak_sun_hours' => 4.6, 'solar_irradiance_kwh_m2_day' => 4.85, 'optimal_tilt_angle' => 6, 'temperature_avg' => 28.0],
            ['province' => 'Jawa Barat', 'city' => 'Depok', 'latitude' => -6.4025, 'longitude' => 106.7942, 'peak_sun_hours' => 4.6, 'solar_irradiance_kwh_m2_day' => 4.80, 'optimal_tilt_angle' => 6, 'temperature_avg' => 27.0],
            ['province' => 'Jawa Barat', 'city' => 'Bogor', 'latitude' => -6.5944, 'longitude' => 106.7892, 'peak_sun_hours' => 4.4, 'solar_irradiance_kwh_m2_day' => 4.60, 'optimal_tilt_angle' => 7, 'temperature_avg' => 25.0],
            ['province' => 'Jawa Barat', 'city' => 'Cirebon', 'latitude' => -6.7063, 'longitude' => 108.5570, 'peak_sun_hours' => 4.7, 'solar_irradiance_kwh_m2_day' => 4.90, 'optimal_tilt_angle' => 7, 'temperature_avg' => 28.0],
            ['province' => 'Jawa Barat', 'city' => 'Karawang', 'latitude' => -6.3227, 'longitude' => 107.3376, 'peak_sun_hours' => 4.7, 'solar_irradiance_kwh_m2_day' => 4.90, 'optimal_tilt_angle' => 6, 'temperature_avg' => 28.0],
            ['province' => 'Jawa Tengah', 'city' => 'Semarang', 'latitude' => -6.9667, 'longitude' => 110.4196, 'peak_sun_hours' => 4.7, 'solar_irradiance_kwh_m2_day' => 4.95, 'optimal_tilt_angle' => 7, 'temperature_avg' => 28.0],
            ['province' => 'Jawa Tengah', 'city' => 'Solo', 'latitude' => -7.5755, 'longitude' => 110.8243, 'peak_sun_hours' => 4.7, 'solar_irradiance_kwh_m2_day' => 4.90, 'optimal_tilt_angle' => 8, 'temperature_avg' => 27.5],
            ['province' => 'DIY Yogyakarta', 'city' => 'Yogyakarta', 'latitude' => -7.7972, 'longitude' => 110.3688, 'peak_sun_hours' => 4.7, 'solar_irradiance_kwh_m2_day' => 4.90, 'optimal_tilt_angle' => 8, 'temperature_avg' => 27.0],
            ['province' => 'Jawa Timur', 'city' => 'Surabaya', 'latitude' => -7.2575, 'longitude' => 112.7521, 'peak_sun_hours' => 4.8, 'solar_irradiance_kwh_m2_day' => 5.05, 'optimal_tilt_angle' => 7, 'temperature_avg' => 28.5],
            ['province' => 'Jawa Timur', 'city' => 'Malang', 'latitude' => -7.9786, 'longitude' => 112.6309, 'peak_sun_hours' => 4.6, 'solar_irradiance_kwh_m2_day' => 4.85, 'optimal_tilt_angle' => 8, 'temperature_avg' => 24.0],
            ['province' => 'Jawa Timur', 'city' => 'Sidoarjo', 'latitude' => -7.4478, 'longitude' => 112.7183, 'peak_sun_hours' => 4.8, 'solar_irradiance_kwh_m2_day' => 5.00, 'optimal_tilt_angle' => 7, 'temperature_avg' => 28.0],
            ['province' => 'Jawa Timur', 'city' => 'Gresik', 'latitude' => -7.1619, 'longitude' => 112.6513, 'peak_sun_hours' => 4.8, 'solar_irradiance_kwh_m2_day' => 5.05, 'optimal_tilt_angle' => 7, 'temperature_avg' => 28.5],

            // KALIMANTAN
            ['province' => 'Kalimantan Barat', 'city' => 'Pontianak', 'latitude' => -0.0263, 'longitude' => 109.3425, 'peak_sun_hours' => 4.3, 'solar_irradiance_kwh_m2_day' => 4.50, 'optimal_tilt_angle' => 0, 'temperature_avg' => 27.5],
            ['province' => 'Kalimantan Tengah', 'city' => 'Palangkaraya', 'latitude' => -2.2136, 'longitude' => 113.9108, 'peak_sun_hours' => 4.4, 'solar_irradiance_kwh_m2_day' => 4.60, 'optimal_tilt_angle' => 2, 'temperature_avg' => 27.0],
            ['province' => 'Kalimantan Selatan', 'city' => 'Banjarmasin', 'latitude' => -3.3194, 'longitude' => 114.5900, 'peak_sun_hours' => 4.5, 'solar_irradiance_kwh_m2_day' => 4.70, 'optimal_tilt_angle' => 3, 'temperature_avg' => 27.5],
            ['province' => 'Kalimantan Timur', 'city' => 'Samarinda', 'latitude' => -0.4948, 'longitude' => 117.1436, 'peak_sun_hours' => 4.4, 'solar_irradiance_kwh_m2_day' => 4.55, 'optimal_tilt_angle' => 0, 'temperature_avg' => 27.5],
            ['province' => 'Kalimantan Timur', 'city' => 'Balikpapan', 'latitude' => -1.2379, 'longitude' => 116.8529, 'peak_sun_hours' => 4.4, 'solar_irradiance_kwh_m2_day' => 4.60, 'optimal_tilt_angle' => 1, 'temperature_avg' => 27.5],
            ['province' => 'Kalimantan Utara', 'city' => 'Tarakan', 'latitude' => 3.2994, 'longitude' => 117.6311, 'peak_sun_hours' => 4.3, 'solar_irradiance_kwh_m2_day' => 4.50, 'optimal_tilt_angle' => 3, 'temperature_avg' => 27.5],

            // SULAWESI
            ['province' => 'Sulawesi Utara', 'city' => 'Manado', 'latitude' => 1.4748, 'longitude' => 124.8421, 'peak_sun_hours' => 4.5, 'solar_irradiance_kwh_m2_day' => 4.70, 'optimal_tilt_angle' => 1, 'temperature_avg' => 27.0],
            ['province' => 'Gorontalo', 'city' => 'Gorontalo', 'latitude' => 0.5435, 'longitude' => 123.0568, 'peak_sun_hours' => 4.5, 'solar_irradiance_kwh_m2_day' => 4.75, 'optimal_tilt_angle' => 1, 'temperature_avg' => 27.5],
            ['province' => 'Sulawesi Tengah', 'city' => 'Palu', 'latitude' => -0.8917, 'longitude' => 119.8707, 'peak_sun_hours' => 4.6, 'solar_irradiance_kwh_m2_day' => 4.80, 'optimal_tilt_angle' => 1, 'temperature_avg' => 28.0],
            ['province' => 'Sulawesi Barat', 'city' => 'Mamuju', 'latitude' => -2.6748, 'longitude' => 118.8885, 'peak_sun_hours' => 4.5, 'solar_irradiance_kwh_m2_day' => 4.70, 'optimal_tilt_angle' => 3, 'temperature_avg' => 27.5],
            ['province' => 'Sulawesi Selatan', 'city' => 'Makassar', 'latitude' => -5.1477, 'longitude' => 119.4327, 'peak_sun_hours' => 4.8, 'solar_irradiance_kwh_m2_day' => 5.00, 'optimal_tilt_angle' => 5, 'temperature_avg' => 28.0],
            ['province' => 'Sulawesi Tenggara', 'city' => 'Kendari', 'latitude' => -3.9985, 'longitude' => 122.5129, 'peak_sun_hours' => 4.7, 'solar_irradiance_kwh_m2_day' => 4.90, 'optimal_tilt_angle' => 4, 'temperature_avg' => 27.5],

            // BALI & NUSA TENGGARA
            ['province' => 'Bali', 'city' => 'Denpasar', 'latitude' => -8.6500, 'longitude' => 115.2167, 'peak_sun_hours' => 4.9, 'solar_irradiance_kwh_m2_day' => 5.15, 'optimal_tilt_angle' => 9, 'temperature_avg' => 27.5],
            ['province' => 'Bali', 'city' => 'Badung', 'latitude' => -8.5819, 'longitude' => 115.1773, 'peak_sun_hours' => 4.9, 'solar_irradiance_kwh_m2_day' => 5.15, 'optimal_tilt_angle' => 9, 'temperature_avg' => 27.5],
            ['province' => 'NTB', 'city' => 'Mataram', 'latitude' => -8.5833, 'longitude' => 116.1167, 'peak_sun_hours' => 5.0, 'solar_irradiance_kwh_m2_day' => 5.25, 'optimal_tilt_angle' => 9, 'temperature_avg' => 28.0],
            ['province' => 'NTT', 'city' => 'Kupang', 'latitude' => -10.1718, 'longitude' => 123.6075, 'peak_sun_hours' => 5.2, 'solar_irradiance_kwh_m2_day' => 5.45, 'optimal_tilt_angle' => 10, 'temperature_avg' => 28.5],

            // MALUKU & PAPUA
            ['province' => 'Maluku', 'city' => 'Ambon', 'latitude' => -3.6954, 'longitude' => 128.1814, 'peak_sun_hours' => 4.5, 'solar_irradiance_kwh_m2_day' => 4.70, 'optimal_tilt_angle' => 4, 'temperature_avg' => 27.0],
            ['province' => 'Maluku Utara', 'city' => 'Ternate', 'latitude' => 0.7833, 'longitude' => 127.3667, 'peak_sun_hours' => 4.4, 'solar_irradiance_kwh_m2_day' => 4.60, 'optimal_tilt_angle' => 1, 'temperature_avg' => 27.5],
            ['province' => 'Papua Barat', 'city' => 'Manokwari', 'latitude' => -0.8615, 'longitude' => 134.0620, 'peak_sun_hours' => 4.3, 'solar_irradiance_kwh_m2_day' => 4.50, 'optimal_tilt_angle' => 1, 'temperature_avg' => 27.0],
            ['province' => 'Papua', 'city' => 'Jayapura', 'latitude' => -2.5335, 'longitude' => 140.7181, 'peak_sun_hours' => 4.4, 'solar_irradiance_kwh_m2_day' => 4.55, 'optimal_tilt_angle' => 3, 'temperature_avg' => 27.5],
        ];

        foreach ($solarData as $data) {
            IndonesiaSolarData::updateOrCreate(
                ['province' => $data['province'], 'city' => $data['city']],
                $data
            );
        }
    }
}
