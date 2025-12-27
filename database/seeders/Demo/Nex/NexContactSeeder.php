<?php

namespace Database\Seeders\Demo\Nex;

use App\Models\Accounting\Contact;
use Illuminate\Database\Seeder;

class NexContactSeeder extends Seeder
{
    /**
     * Seed contacts for PT Nusantara Energi Khatulistiwa / NEX (Solar EPC).
     * Creates realistic customers, vendors, and subcontractors for solar industry.
     */
    public function run(): void
    {
        $this->createCustomers();
        $this->createVendors();
        $this->createSubcontractors();
    }

    private function createCustomers(): void
    {
        $customers = [
            // Industrial Customers
            [
                'code' => 'C-IND-CRG',
                'name' => 'PT Charoen Pokphand Indonesia Tbk',
                'type' => Contact::TYPE_CUSTOMER,
                'email' => 'procurement@cp.co.id',
                'phone' => '021-4609090',
                'address' => 'Jl. Ancol VIII No. 1',
                'city' => 'Jakarta Utara',
                'province' => 'DKI Jakarta',
                'postal_code' => '14430',
                'npwp' => '01.067.890.1-054.000',
                'credit_limit' => 500000000,
                'payment_term_days' => 30,
                'notes' => 'Poultry farm - target 500 kWp rooftop',
            ],
            [
                'code' => 'C-IND-IDF',
                'name' => 'PT Indofood Sukses Makmur Tbk',
                'type' => Contact::TYPE_CUSTOMER,
                'email' => 'energy@indofood.co.id',
                'phone' => '021-5795822',
                'address' => 'Sudirman Plaza, Indofood Tower',
                'city' => 'Jakarta Selatan',
                'province' => 'DKI Jakarta',
                'postal_code' => '12190',
                'npwp' => '01.001.003.7-054.000',
                'credit_limit' => 1000000000,
                'payment_term_days' => 45,
                'notes' => 'Food factory - target 1 MWp rooftop',
            ],
            [
                'code' => 'C-IND-WST',
                'name' => 'PT Wings Surya',
                'type' => Contact::TYPE_CUSTOMER,
                'email' => 'facility@wings.co.id',
                'phone' => '031-8532533',
                'address' => 'Jl. Rungkut Industri III No. 10-12',
                'city' => 'Surabaya',
                'province' => 'Jawa Timur',
                'postal_code' => '60293',
                'npwp' => '01.062.345.6-615.000',
                'credit_limit' => 300000000,
                'payment_term_days' => 30,
                'notes' => 'FMCG factory - target 300 kWp rooftop',
            ],
            // Commercial Customers
            [
                'code' => 'C-COM-MKL',
                'name' => 'PT Matahari Putra Prima Tbk',
                'type' => Contact::TYPE_CUSTOMER,
                'email' => 'property@matahari.co.id',
                'phone' => '021-29671717',
                'address' => 'Menara Matahari, Lippo Karawaci',
                'city' => 'Tangerang',
                'province' => 'Banten',
                'postal_code' => '15811',
                'npwp' => '01.303.127.8-411.000',
                'credit_limit' => 200000000,
                'payment_term_days' => 30,
                'notes' => 'Hypermarket chain - multiple sites',
            ],
            [
                'code' => 'C-COM-HMI',
                'name' => 'PT Hotel Indonesia Natour',
                'type' => Contact::TYPE_CUSTOMER,
                'email' => 'engineering@hotelin.co.id',
                'phone' => '021-3923008',
                'address' => 'Jl. M.H. Thamrin No. 1',
                'city' => 'Jakarta Pusat',
                'province' => 'DKI Jakarta',
                'postal_code' => '10310',
                'npwp' => '01.000.234.5-054.000',
                'credit_limit' => 150000000,
                'payment_term_days' => 30,
                'notes' => 'Hotel chain - rooftop + carport solar',
            ],
            [
                'code' => 'C-COM-OFC',
                'name' => 'PT Pakuwon Jati Tbk',
                'type' => Contact::TYPE_CUSTOMER,
                'email' => 'sustainability@pakuwon.com',
                'phone' => '021-5706575',
                'address' => 'Kota Kasablanka Office Tower',
                'city' => 'Jakarta Selatan',
                'province' => 'DKI Jakarta',
                'postal_code' => '12870',
                'npwp' => '01.348.567.8-054.000',
                'credit_limit' => 400000000,
                'payment_term_days' => 45,
                'notes' => 'Office building & mall developer',
            ],
            // Agricultural Customers
            [
                'code' => 'C-AGR-JFA',
                'name' => 'PT Japfa Comfeed Indonesia Tbk',
                'type' => Contact::TYPE_CUSTOMER,
                'email' => 'procurement@japfa.com',
                'phone' => '021-5294093',
                'address' => 'Wisma Millenia, Jl. MT Haryono Kav. 16',
                'city' => 'Jakarta Selatan',
                'province' => 'DKI Jakarta',
                'postal_code' => '12810',
                'npwp' => '01.067.789.0-054.000',
                'credit_limit' => 350000000,
                'payment_term_days' => 30,
                'notes' => 'Poultry farm & feedmill - multiple sites Jawa',
            ],
            [
                'code' => 'C-AGR-SMI',
                'name' => 'PT Sierad Produce Tbk',
                'type' => Contact::TYPE_CUSTOMER,
                'email' => 'operations@sierad.co.id',
                'phone' => '021-5278450',
                'address' => 'Jl. Raya Parung KM 19',
                'city' => 'Bogor',
                'province' => 'Jawa Barat',
                'postal_code' => '16330',
                'npwp' => '01.078.901.2-401.000',
                'credit_limit' => 200000000,
                'payment_term_days' => 30,
                'notes' => 'Poultry processing - cold storage solar',
            ],
            // Residential Estate Developer
            [
                'code' => 'C-RES-BSD',
                'name' => 'PT Bumi Serpong Damai Tbk',
                'type' => Contact::TYPE_CUSTOMER,
                'email' => 'green.initiative@sinarmasland.com',
                'phone' => '021-53150150',
                'address' => 'Sinar Mas Land Plaza, BSD City',
                'city' => 'Tangerang Selatan',
                'province' => 'Banten',
                'postal_code' => '15345',
                'npwp' => '01.303.456.7-411.000',
                'credit_limit' => 500000000,
                'payment_term_days' => 45,
                'notes' => 'Residential estate - cluster solar program',
            ],
            [
                'code' => 'C-RES-LIP',
                'name' => 'PT Lippo Karawaci Tbk',
                'type' => Contact::TYPE_CUSTOMER,
                'email' => 'sustainability@lippokarawaci.com',
                'phone' => '021-29671234',
                'address' => 'Menara Matahari, Lippo Karawaci',
                'city' => 'Tangerang',
                'province' => 'Banten',
                'postal_code' => '15811',
                'npwp' => '01.315.678.9-411.000',
                'credit_limit' => 400000000,
                'payment_term_days' => 45,
                'notes' => 'Mixed-use development - solar carpark',
            ],
            // Lease-to-Own Customer (NEX specialty)
            [
                'code' => 'C-LTO-MNF',
                'name' => 'PT Mayora Indah Tbk',
                'type' => Contact::TYPE_CUSTOMER,
                'email' => 'energy.saving@mayora.co.id',
                'phone' => '021-5207878',
                'address' => 'Mayora Building, Jl. Tomang Raya No. 21-23',
                'city' => 'Jakarta Barat',
                'province' => 'DKI Jakarta',
                'postal_code' => '11440',
                'npwp' => '01.001.234.5-054.000',
                'credit_limit' => 0, // Lease model
                'payment_term_days' => 30,
                'notes' => 'LEASE-TO-OWN: 750 kWp factory rooftop, 15-year PPA',
            ],
        ];

        foreach ($customers as $customer) {
            Contact::updateOrCreate(
                ['code' => $customer['code']],
                array_merge($customer, ['is_active' => true])
            );
        }

        $this->command->info('    Created 11 NEX customers (industrial, commercial, agricultural, residential)');
    }

    private function createVendors(): void
    {
        $vendors = [
            // PV Module Suppliers
            [
                'code' => 'S-PV-IDN',
                'name' => 'PT IDN Solar Tech',
                'type' => Contact::TYPE_SUPPLIER,
                'email' => 'sales@idnsolartech.co.id',
                'phone' => '021-29556688',
                'address' => 'Kawasan Industri Jababeka II',
                'city' => 'Bekasi',
                'province' => 'Jawa Barat',
                'postal_code' => '17530',
                'npwp' => '02.567.890.1-432.000',
                'credit_limit' => 0,
                'payment_term_days' => 30,
                'notes' => 'NUSA brand manufacturer - local production',
            ],
            [
                'code' => 'S-PV-JAS',
                'name' => 'PT JA Solar Indonesia',
                'type' => Contact::TYPE_SUPPLIER,
                'email' => 'indonesia@jasolar.com',
                'phone' => '021-30418899',
                'address' => 'Menara Astra, Jl. Jend Sudirman Kav. 5-6',
                'city' => 'Jakarta Pusat',
                'province' => 'DKI Jakarta',
                'postal_code' => '10220',
                'npwp' => '01.678.901.2-054.000',
                'credit_limit' => 0,
                'payment_term_days' => 45,
                'notes' => 'Tier-1 PV modules - import',
            ],
            [
                'code' => 'S-PV-LON',
                'name' => 'PT Longi Green Energy Indonesia',
                'type' => Contact::TYPE_SUPPLIER,
                'email' => 'sales.id@longi.com',
                'phone' => '021-29786655',
                'address' => 'Wisma 46, Kota BNI',
                'city' => 'Jakarta Pusat',
                'province' => 'DKI Jakarta',
                'postal_code' => '10220',
                'npwp' => '01.789.012.3-054.000',
                'credit_limit' => 0,
                'payment_term_days' => 45,
                'notes' => 'Hi-MO series - premium efficiency',
            ],
            // Inverter Suppliers
            [
                'code' => 'S-INV-HUA',
                'name' => 'PT Huawei Tech Investment',
                'type' => Contact::TYPE_SUPPLIER,
                'email' => 'solar.id@huawei.com',
                'phone' => '021-29658888',
                'address' => 'Lippo St Moritz Tower',
                'city' => 'Jakarta Barat',
                'province' => 'DKI Jakarta',
                'postal_code' => '11510',
                'npwp' => '01.890.123.4-054.000',
                'credit_limit' => 0,
                'payment_term_days' => 30,
                'notes' => 'SUN2000 series string inverters',
            ],
            [
                'code' => 'S-INV-SMA',
                'name' => 'PT SMA Solar Technology Indonesia',
                'type' => Contact::TYPE_SUPPLIER,
                'email' => 'info.id@sma.de',
                'phone' => '021-29657799',
                'address' => 'Gedung Menara Sudirman',
                'city' => 'Jakarta Selatan',
                'province' => 'DKI Jakarta',
                'postal_code' => '12190',
                'npwp' => '01.901.234.5-054.000',
                'credit_limit' => 0,
                'payment_term_days' => 30,
                'notes' => 'Premium German inverters',
            ],
            [
                'code' => 'S-INV-GRW',
                'name' => 'PT Growatt Indonesia',
                'type' => Contact::TYPE_SUPPLIER,
                'email' => 'sales@growatt.co.id',
                'phone' => '021-45678901',
                'address' => 'Ruko Mangga Dua Square',
                'city' => 'Jakarta Utara',
                'province' => 'DKI Jakarta',
                'postal_code' => '14430',
                'npwp' => '02.012.345.6-054.000',
                'credit_limit' => 0,
                'payment_term_days' => 14,
                'notes' => 'Budget-friendly string inverters',
            ],
            // Mounting Structure Suppliers
            [
                'code' => 'S-MNT-SLR',
                'name' => 'PT Schletter Solar Indonesia',
                'type' => Contact::TYPE_SUPPLIER,
                'email' => 'asia@schletter-group.com',
                'phone' => '021-29556644',
                'address' => 'Kawasan Industri Pulogadung',
                'city' => 'Jakarta Timur',
                'province' => 'DKI Jakarta',
                'postal_code' => '13930',
                'npwp' => '02.123.456.7-054.000',
                'credit_limit' => 0,
                'payment_term_days' => 30,
                'notes' => 'German quality mounting systems',
            ],
            [
                'code' => 'S-MNT-LOC',
                'name' => 'CV Solar Mounting Nusantara',
                'type' => Contact::TYPE_SUPPLIER,
                'email' => 'solarmounting.id@gmail.com',
                'phone' => '031-8912345',
                'address' => 'Jl. Rungkut Industri VI No. 22',
                'city' => 'Surabaya',
                'province' => 'Jawa Timur',
                'postal_code' => '60293',
                'npwp' => '03.234.567.8-615.000',
                'credit_limit' => 0,
                'payment_term_days' => 14,
                'notes' => 'Local fabrication - cost effective',
            ],
            // DC Components & Cable
            [
                'code' => 'S-DC-STB',
                'name' => 'PT Staubli Indonesia',
                'type' => Contact::TYPE_SUPPLIER,
                'email' => 'solar.id@staubli.com',
                'phone' => '021-29658877',
                'address' => 'Menara Rajawali, Mega Kuningan',
                'city' => 'Jakarta Selatan',
                'province' => 'DKI Jakarta',
                'postal_code' => '12950',
                'npwp' => '02.345.678.9-054.000',
                'credit_limit' => 0,
                'payment_term_days' => 30,
                'notes' => 'MC4 connectors - original Swiss quality',
            ],
            [
                'code' => 'S-DC-CBL',
                'name' => 'PT Helukabel Indonesia',
                'type' => Contact::TYPE_SUPPLIER,
                'email' => 'solar@helukabel.co.id',
                'phone' => '021-29526688',
                'address' => 'Kawasan Industri MM2100',
                'city' => 'Bekasi',
                'province' => 'Jawa Barat',
                'postal_code' => '17520',
                'npwp' => '02.456.789.0-432.000',
                'credit_limit' => 0,
                'payment_term_days' => 30,
                'notes' => 'Solar DC cables, SOLARFLEX',
            ],
        ];

        foreach ($vendors as $vendor) {
            Contact::updateOrCreate(
                ['code' => $vendor['code']],
                array_merge($vendor, ['is_active' => true])
            );
        }

        $this->command->info('    Created 10 NEX vendors (PV modules, inverters, mounting, cables)');
    }

    private function createSubcontractors(): void
    {
        $subcontractors = [
            [
                'code' => 'S-SUB-RTF',
                'name' => 'CV Atap Jaya Konstruksi',
                'type' => Contact::TYPE_SUPPLIER,
                'email' => 'atapjayakonst@gmail.com',
                'phone' => '081234567891',
                'address' => 'Jl. Raya Cibinong No. 45',
                'city' => 'Bogor',
                'province' => 'Jawa Barat',
                'postal_code' => '16916',
                'npwp' => '03.567.890.1-401.000',
                'is_subcontractor' => true,
                'subcontractor_services' => json_encode(['structural_assessment', 'roof_reinforcement']),
                'hourly_rate' => 100000,
                'daily_rate' => 750000,
                'notes' => 'Roof structural assessment & reinforcement specialist',
            ],
            [
                'code' => 'S-SUB-ELC',
                'name' => 'PT Elektro Solar Instalasi',
                'type' => Contact::TYPE_SUPPLIER,
                'email' => 'elektrosolar.ins@gmail.com',
                'phone' => '081345678902',
                'address' => 'Jl. Raya Bekasi Timur KM 18',
                'city' => 'Bekasi',
                'province' => 'Jawa Barat',
                'postal_code' => '17111',
                'npwp' => '02.678.901.2-432.000',
                'is_subcontractor' => true,
                'subcontractor_services' => json_encode(['pv_installation', 'electrical_wiring', 'grid_connection']),
                'hourly_rate' => 125000,
                'daily_rate' => 900000,
                'notes' => 'PV installation & grid connection specialist',
            ],
            [
                'code' => 'S-SUB-CMT',
                'name' => 'PT Surya Commissioning Team',
                'type' => Contact::TYPE_SUPPLIER,
                'email' => 'suryacommteam@gmail.com',
                'phone' => '081456789013',
                'address' => 'Jl. Industri Raya No. 88',
                'city' => 'Tangerang',
                'province' => 'Banten',
                'postal_code' => '15320',
                'npwp' => '02.789.012.3-411.000',
                'is_subcontractor' => true,
                'subcontractor_services' => json_encode(['testing', 'commissioning', 'performance_verification']),
                'hourly_rate' => 175000,
                'daily_rate' => 1250000,
                'notes' => 'Testing, commissioning & IV curve analysis',
            ],
            [
                'code' => 'S-SUB-OMT',
                'name' => 'CV Operasi Maintenance Solar',
                'type' => Contact::TYPE_SUPPLIER,
                'email' => 'omsolar.cv@gmail.com',
                'phone' => '081567890124',
                'address' => 'Jl. Raya Cikarang Barat',
                'city' => 'Bekasi',
                'province' => 'Jawa Barat',
                'postal_code' => '17530',
                'npwp' => '03.890.123.4-432.000',
                'is_subcontractor' => true,
                'subcontractor_services' => json_encode(['panel_cleaning', 'preventive_maintenance', 'monitoring']),
                'hourly_rate' => 75000,
                'daily_rate' => 500000,
                'notes' => 'O&M services - panel cleaning & monitoring',
            ],
        ];

        foreach ($subcontractors as $subcontractor) {
            Contact::updateOrCreate(
                ['code' => $subcontractor['code']],
                array_merge($subcontractor, [
                    'is_active' => true,
                    'credit_limit' => 0,
                    'payment_term_days' => 14,
                ])
            );
        }

        $this->command->info('    Created 4 NEX subcontractors (roofing, installation, commissioning, O&M)');
    }
}
