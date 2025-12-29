<?php

namespace Database\Seeders;

use App\Models\CompanyProfile;
use Illuminate\Database\Seeder;

class CompanyProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // PT Vahana Gasti Teknika
        CompanyProfile::updateOrCreate(
            ['slug' => 'vahana'],
            [
                'name' => 'PT Vahana Gasti Teknika',
                'tagline' => 'Writing the future of safe, smart and sustainable electrification',
                'description' => 'PT Vahana Gasti Teknika is a leading electrical engineering company specializing in power distribution solutions. We design, manufacture, and install high-quality electrical panel systems for industrial and commercial applications across Indonesia.',
                'founded_year' => 2015,
                'employees_count' => '50-100',
                'primary_color' => '#FF7A3D',
                'secondary_color' => '#FF5100',
                'services' => [
                    [
                        'title' => 'Switchboards & Distribution Panels',
                        'description' => 'Custom-designed low voltage switchboards and distribution panels for industrial and commercial applications.',
                        'icon' => 'zap',
                    ],
                    [
                        'title' => 'Motor Control Centers (MCC)',
                        'description' => 'Intelligent motor control solutions with advanced protection and monitoring capabilities.',
                        'icon' => 'settings',
                    ],
                    [
                        'title' => 'Medium Voltage Switchgear',
                        'description' => 'Reliable MV switchgear systems designed for high-performance power distribution.',
                        'icon' => 'power',
                    ],
                    [
                        'title' => 'Capacitor Bank Systems',
                        'description' => 'Power factor correction solutions to optimize electrical efficiency and reduce energy costs.',
                        'icon' => 'battery-charging',
                    ],
                    [
                        'title' => 'Genset Control Panels',
                        'description' => 'Automated generator control panels with AMF/ATS functionality for seamless power backup.',
                        'icon' => 'cpu',
                    ],
                ],
                'portfolio' => [
                    [
                        'title' => 'PT Kereta Api Indonesia',
                        'description' => 'Main distribution panel system for railway station infrastructure.',
                        'year' => 2023,
                    ],
                    [
                        'title' => 'Pertamina Refinery Unit',
                        'description' => 'Motor control center installation for refinery operations.',
                        'year' => 2022,
                    ],
                    [
                        'title' => 'PLN Substation Upgrade',
                        'description' => 'Medium voltage switchgear replacement project.',
                        'year' => 2023,
                    ],
                ],
                'certifications' => [
                    [
                        'name' => 'ISO 9001:2015',
                        'issuer' => 'TUV Rheinland',
                        'year' => 2020,
                    ],
                    [
                        'name' => 'SNI Certified Products',
                        'issuer' => 'BSN Indonesia',
                        'year' => 2019,
                    ],
                    [
                        'name' => 'TKDN Certification',
                        'issuer' => 'Kemenperin',
                        'year' => 2021,
                    ],
                ],
                'social_links' => [
                    'instagram' => 'https://instagram.com/vahanagastiteknika',
                    'linkedin' => 'https://linkedin.com/company/vahana-gasti-teknika',
                ],
                'email' => 'info@vahana.co.id',
                'phone' => '+62 899 119 2333',
                'address' => 'Sigma Kartika Green Warehouse L-3A, Jl. Atma Asnawi No. 20, Kec. Gunung Sindur, Kab. Bogor, Jawa Barat 16340',
                'website' => 'https://vahana.co.id',
                'is_active' => true,
            ]
        );

        // PT Nusantara Energi Khatulistiwa (NEX)
        CompanyProfile::updateOrCreate(
            ['slug' => 'nex'],
            [
                'name' => 'PT Nusantara Energi Khatulistiwa',
                'tagline' => 'Flexible and Adaptive Clean Energy Solutions',
                'description' => 'PT Nusantara Energi Khatulistiwa (NEX) is a clean energy company founded in 2021, dedicated to accelerating Indonesia\'s transition to sustainable energy. We provide innovative solar PV solutions with flexible financing options including zero-capex and lease-to-own models.',
                'founded_year' => 2021,
                'employees_count' => '10-50',
                'primary_color' => '#22C55E',
                'secondary_color' => '#166534',
                'services' => [
                    [
                        'title' => 'Zero Capex Solar',
                        'description' => 'Install solar panels with no upfront investment. Pay only for the energy you use at rates lower than PLN.',
                        'icon' => 'sun',
                    ],
                    [
                        'title' => 'Lease-to-Own Solar',
                        'description' => 'Flexible payment plans that let you own your solar system over time while saving on electricity from day one.',
                        'icon' => 'trending-up',
                    ],
                    [
                        'title' => 'Nusa Solar PV Systems',
                        'description' => 'Premium quality solar photovoltaic systems designed and optimized for Indonesian conditions.',
                        'icon' => 'zap',
                    ],
                    [
                        'title' => 'Energy Consulting',
                        'description' => 'Expert analysis of your energy consumption and customized recommendations for maximum savings.',
                        'icon' => 'bar-chart',
                    ],
                ],
                'portfolio' => [
                    [
                        'title' => 'Industrial Rooftop Solar',
                        'description' => '500 kWp rooftop installation for manufacturing facility in Tangerang.',
                        'year' => 2023,
                    ],
                    [
                        'title' => 'Commercial Building Solar',
                        'description' => '150 kWp system for office building with zero-capex model.',
                        'year' => 2024,
                    ],
                    [
                        'title' => 'Warehouse Solar Project',
                        'description' => '300 kWp installation for logistics warehouse in Bekasi.',
                        'year' => 2024,
                    ],
                ],
                'certifications' => [
                    [
                        'name' => 'ESDM Licensed Solar Installer',
                        'issuer' => 'Kementerian ESDM',
                        'year' => 2022,
                    ],
                    [
                        'name' => 'PLN Certified EPC Partner',
                        'issuer' => 'PLN',
                        'year' => 2023,
                    ],
                ],
                'social_links' => [
                    'instagram' => 'https://instagram.com/nexenergy.id',
                    'linkedin' => 'https://linkedin.com/company/nex-energy',
                ],
                'email' => 'info@energimasadepan.com',
                'phone' => '021 59577680',
                'address' => 'Surya Grand Cisoka Blok E.31, Tangerang, Banten',
                'website' => 'https://energimasadepan.com',
                'is_active' => true,
            ]
        );
    }
}
