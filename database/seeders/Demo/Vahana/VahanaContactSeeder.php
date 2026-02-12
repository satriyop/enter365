<?php

namespace Database\Seeders\Demo\Vahana;

use App\Models\Contacts\Contact;
use Illuminate\Database\Seeder;

class VahanaContactSeeder extends Seeder
{
    /**
     * Seed contacts for PT Vahana (Electrical Panel Maker).
     * Creates realistic customers, vendors, and subcontractors for the industry.
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
            // Major PLN/Utility
            [
                'code' => 'C-PLN-JBR',
                'name' => 'PT PLN (Persero) UP3 Bandung',
                'type' => Contact::TYPE_CUSTOMER,
                'email' => 'up3bandung@pln.co.id',
                'phone' => '022-4231234',
                'address' => 'Jl. Asia Afrika No. 63',
                'city' => 'Bandung',
                'province' => 'Jawa Barat',
                'postal_code' => '40111',
                'npwp' => '01.000.000.0-411.000',
                'credit_limit' => 500000000,
                'payment_term_days' => 45,
            ],
            [
                'code' => 'C-PLN-JKT',
                'name' => 'PT PLN (Persero) UID Jakarta Raya',
                'type' => Contact::TYPE_CUSTOMER,
                'email' => 'uidjkt@pln.co.id',
                'phone' => '021-7261122',
                'address' => 'Jl. M.I. Ridwan Rais No. 1',
                'city' => 'Jakarta Pusat',
                'province' => 'DKI Jakarta',
                'postal_code' => '10110',
                'npwp' => '01.000.000.0-093.000',
                'credit_limit' => 1000000000,
                'payment_term_days' => 60,
            ],
            // Industrial Customers
            [
                'code' => 'C-KRK',
                'name' => 'PT Krakatau Steel (Persero) Tbk',
                'type' => Contact::TYPE_CUSTOMER,
                'email' => 'procurement@kfrkataustel.com',
                'phone' => '0254-391971',
                'address' => 'Jl. Industri No. 5, Kawasan Industri Krakatau',
                'city' => 'Cilegon',
                'province' => 'Banten',
                'postal_code' => '42435',
                'npwp' => '01.061.124.0-054.000',
                'credit_limit' => 250000000,
                'payment_term_days' => 30,
            ],
            [
                'code' => 'C-AST',
                'name' => 'PT Astra International Tbk',
                'type' => Contact::TYPE_CUSTOMER,
                'email' => 'procurement@astra.co.id',
                'phone' => '021-5088888',
                'address' => 'Jl. Gaya Motor Raya No. 8, Sunter II',
                'city' => 'Jakarta Utara',
                'province' => 'DKI Jakarta',
                'postal_code' => '14330',
                'npwp' => '01.303.672.7-054.000',
                'credit_limit' => 300000000,
                'payment_term_days' => 30,
            ],
            [
                'code' => 'C-UNL',
                'name' => 'PT Unilever Indonesia Tbk',
                'type' => Contact::TYPE_CUSTOMER,
                'email' => 'procurement@unilever.com',
                'phone' => '021-5299711',
                'address' => 'Jl. BSD Boulevard Barat, Green Office Park',
                'city' => 'Tangerang Selatan',
                'province' => 'Banten',
                'postal_code' => '15345',
                'npwp' => '01.001.692.1-054.000',
                'credit_limit' => 200000000,
                'payment_term_days' => 30,
            ],
            // Contractors/EPC
            [
                'code' => 'C-WKA',
                'name' => 'PT Wijaya Karya (Persero) Tbk',
                'type' => Contact::TYPE_CUSTOMER,
                'email' => 'procurement@wika.co.id',
                'phone' => '021-8192808',
                'address' => 'Jl. D.I. Panjaitan Kav. 9',
                'city' => 'Jakarta Timur',
                'province' => 'DKI Jakarta',
                'postal_code' => '13340',
                'npwp' => '01.001.647.4-093.000',
                'credit_limit' => 400000000,
                'payment_term_days' => 45,
            ],
            [
                'code' => 'C-PP',
                'name' => 'PT PP (Persero) Tbk',
                'type' => Contact::TYPE_CUSTOMER,
                'email' => 'procurement@pt-pp.com',
                'phone' => '021-7884445',
                'address' => 'Jl. TB Simatupang No. 57',
                'city' => 'Jakarta Selatan',
                'province' => 'DKI Jakarta',
                'postal_code' => '12530',
                'npwp' => '01.001.646.6-093.000',
                'credit_limit' => 350000000,
                'payment_term_days' => 45,
            ],
            // Medium Contractors
            [
                'code' => 'C-TRIA',
                'name' => 'PT Trias Sentosa',
                'type' => Contact::TYPE_CUSTOMER,
                'email' => 'purchasing@triassentosa.co.id',
                'phone' => '031-8910099',
                'address' => 'Jl. Rungkut Industri II No. 15',
                'city' => 'Surabaya',
                'province' => 'Jawa Timur',
                'postal_code' => '60293',
                'npwp' => '01.224.567.8-615.000',
                'credit_limit' => 100000000,
                'payment_term_days' => 30,
            ],
            [
                'code' => 'C-MKT',
                'name' => 'CV Mitra Kontraktor Teknik',
                'type' => Contact::TYPE_CUSTOMER,
                'email' => 'info@mitrakontraktor.com',
                'phone' => '021-82475566',
                'address' => 'Jl. Raya Bekasi KM 25',
                'city' => 'Bekasi',
                'province' => 'Jawa Barat',
                'postal_code' => '17530',
                'npwp' => '02.345.678.9-432.000',
                'credit_limit' => 75000000,
                'payment_term_days' => 14,
            ],
            [
                'code' => 'C-ELM',
                'name' => 'CV Elektrik Mandiri',
                'type' => Contact::TYPE_CUSTOMER,
                'email' => 'elektrikmandiri@gmail.com',
                'phone' => '021-88995566',
                'address' => 'Ruko Megamall Blok A-12',
                'city' => 'Bekasi',
                'province' => 'Jawa Barat',
                'postal_code' => '17144',
                'npwp' => '02.456.789.0-432.000',
                'credit_limit' => 50000000,
                'payment_term_days' => 14,
            ],
            // Real Estate/Property
            [
                'code' => 'C-SML',
                'name' => 'PT Summarecon Agung Tbk',
                'type' => Contact::TYPE_CUSTOMER,
                'email' => 'procurement@summarecon.com',
                'phone' => '021-29967888',
                'address' => 'Summarecon Mall Serpong',
                'city' => 'Tangerang',
                'province' => 'Banten',
                'postal_code' => '15810',
                'npwp' => '01.303.673.5-411.000',
                'credit_limit' => 200000000,
                'payment_term_days' => 30,
            ],
            [
                'code' => 'C-CTP',
                'name' => 'PT Ciputra Development Tbk',
                'type' => Contact::TYPE_CUSTOMER,
                'email' => 'procurement@ciputra.com',
                'phone' => '021-30418888',
                'address' => 'Ciputra World 1 Jakarta',
                'city' => 'Jakarta Selatan',
                'province' => 'DKI Jakarta',
                'postal_code' => '12940',
                'npwp' => '01.303.672.3-054.000',
                'credit_limit' => 150000000,
                'payment_term_days' => 30,
            ],
        ];

        foreach ($customers as $customer) {
            Contact::updateOrCreate(
                ['code' => $customer['code']],
                array_merge($customer, ['is_active' => true])
            );
        }

        $this->command->info('Created 12 customers');
    }

    private function createVendors(): void
    {
        $vendors = [
            // Major Electrical Equipment Suppliers
            [
                'code' => 'S-SCH',
                'name' => 'PT Schneider Electric Indonesia',
                'type' => Contact::TYPE_SUPPLIER,
                'email' => 'customer.care.id@se.com',
                'phone' => '021-29242242',
                'address' => 'Ventura Building, Jl. RA Kartini No. 26',
                'city' => 'Jakarta Selatan',
                'province' => 'DKI Jakarta',
                'postal_code' => '12430',
                'npwp' => '01.070.324.0-054.000',
                'credit_limit' => 0,
                'payment_term_days' => 30,
            ],
            [
                'code' => 'S-ABB',
                'name' => 'PT ABB Sakti Industri',
                'type' => Contact::TYPE_SUPPLIER,
                'email' => 'sales@id.abb.com',
                'phone' => '021-5700770',
                'address' => 'Wisma Raharja, Jl. TB Simatupang',
                'city' => 'Jakarta Selatan',
                'province' => 'DKI Jakarta',
                'postal_code' => '12560',
                'npwp' => '01.060.123.4-054.000',
                'credit_limit' => 0,
                'payment_term_days' => 30,
            ],
            [
                'code' => 'S-LGD',
                'name' => 'PT Legrand Indonesia',
                'type' => Contact::TYPE_SUPPLIER,
                'email' => 'sales@legrand.co.id',
                'phone' => '021-5273388',
                'address' => 'Menara Dea II, Jl. Mega Kuningan Barat',
                'city' => 'Jakarta Selatan',
                'province' => 'DKI Jakarta',
                'postal_code' => '12950',
                'npwp' => '01.071.456.7-054.000',
                'credit_limit' => 0,
                'payment_term_days' => 30,
            ],
            [
                'code' => 'S-CHNT',
                'name' => 'PT Chint Electric Indonesia',
                'type' => Contact::TYPE_SUPPLIER,
                'email' => 'sales@chint.co.id',
                'phone' => '021-29578800',
                'address' => 'Ruko Mega Grosir Cempaka Mas Blok M1/18',
                'city' => 'Jakarta Pusat',
                'province' => 'DKI Jakarta',
                'postal_code' => '10640',
                'npwp' => '02.234.567.8-054.000',
                'credit_limit' => 0,
                'payment_term_days' => 14,
            ],
            // Cable Suppliers
            [
                'code' => 'S-KMI',
                'name' => 'PT Kabel Metal Indonesia',
                'type' => Contact::TYPE_SUPPLIER,
                'email' => 'sales@kabelmetal.co.id',
                'phone' => '021-4600602',
                'address' => 'Jl. Daan Mogot KM 16',
                'city' => 'Tangerang',
                'province' => 'Banten',
                'postal_code' => '15122',
                'npwp' => '01.069.452.1-054.000',
                'credit_limit' => 0,
                'payment_term_days' => 30,
            ],
            [
                'code' => 'S-SUM',
                'name' => 'PT Supreme Cable Manufacturing',
                'type' => Contact::TYPE_SUPPLIER,
                'email' => 'sales@supreme.co.id',
                'phone' => '021-4603069',
                'address' => 'Jl. Kebon Sirih No. 71',
                'city' => 'Jakarta Pusat',
                'province' => 'DKI Jakarta',
                'postal_code' => '10340',
                'npwp' => '01.070.788.1-054.000',
                'credit_limit' => 0,
                'payment_term_days' => 30,
            ],
            // Busbar & Copper Suppliers
            [
                'code' => 'S-BBR',
                'name' => 'PT Busbar Indonesia',
                'type' => Contact::TYPE_SUPPLIER,
                'email' => 'sales@busbarindonesia.com',
                'phone' => '021-88326655',
                'address' => 'Jl. Raya Narogong KM 12',
                'city' => 'Bekasi',
                'province' => 'Jawa Barat',
                'postal_code' => '17116',
                'npwp' => '02.345.678.1-432.000',
                'credit_limit' => 0,
                'payment_term_days' => 14,
            ],
            [
                'code' => 'S-CPR',
                'name' => 'PT Copper Indonesia Persada',
                'type' => Contact::TYPE_SUPPLIER,
                'email' => 'sales@copperindonesia.com',
                'phone' => '021-4682555',
                'address' => 'Jl. Raya Cakung Cilincing',
                'city' => 'Jakarta Utara',
                'province' => 'DKI Jakarta',
                'postal_code' => '14130',
                'npwp' => '02.456.789.2-054.000',
                'credit_limit' => 0,
                'payment_term_days' => 14,
            ],
            // Enclosure & Panel Box Suppliers
            [
                'code' => 'S-RTL',
                'name' => 'PT Rittal Indonesia',
                'type' => Contact::TYPE_SUPPLIER,
                'email' => 'info@rittal.co.id',
                'phone' => '021-29526568',
                'address' => 'Jl. Raya Kelapa Gading Permai',
                'city' => 'Jakarta Utara',
                'province' => 'DKI Jakarta',
                'postal_code' => '14240',
                'npwp' => '01.072.345.6-054.000',
                'credit_limit' => 0,
                'payment_term_days' => 30,
            ],
            [
                'code' => 'S-ENB',
                'name' => 'UD Enclosure Box Jaya',
                'type' => Contact::TYPE_SUPPLIER,
                'email' => 'enboxjaya@gmail.com',
                'phone' => '021-88324455',
                'address' => 'Jl. Raya Industri Cikarang',
                'city' => 'Bekasi',
                'province' => 'Jawa Barat',
                'postal_code' => '17530',
                'npwp' => '03.567.890.1-432.000',
                'credit_limit' => 0,
                'payment_term_days' => 7,
            ],
            // Accessories Suppliers
            [
                'code' => 'S-AKE',
                'name' => 'UD Aksesoris Elektrik',
                'type' => Contact::TYPE_SUPPLIER,
                'email' => 'aksesoriselektrik@gmail.com',
                'phone' => '021-6231445',
                'address' => 'Glodok Jaya Lt. 2 Blok B No. 45',
                'city' => 'Jakarta Barat',
                'province' => 'DKI Jakarta',
                'postal_code' => '11130',
                'npwp' => '03.678.901.2-054.000',
                'credit_limit' => 0,
                'payment_term_days' => 7,
            ],
            [
                'code' => 'S-DIN',
                'name' => 'PT DIN Rail Nusantara',
                'type' => Contact::TYPE_SUPPLIER,
                'email' => 'sales@dinrail.co.id',
                'phone' => '021-4517788',
                'address' => 'Jl. Raya Cikupa No. 88',
                'city' => 'Tangerang',
                'province' => 'Banten',
                'postal_code' => '15710',
                'npwp' => '02.789.012.3-411.000',
                'credit_limit' => 0,
                'payment_term_days' => 14,
            ],
        ];

        foreach ($vendors as $vendor) {
            Contact::updateOrCreate(
                ['code' => $vendor['code']],
                array_merge($vendor, ['is_active' => true])
            );
        }

        $this->command->info('Created 12 vendors');
    }

    private function createSubcontractors(): void
    {
        $subcontractors = [
            [
                'code' => 'S-INS1',
                'name' => 'CV Instalasi Listrik Prima',
                'type' => Contact::TYPE_SUPPLIER,
                'email' => 'instalasiprima@gmail.com',
                'phone' => '081234567890',
                'address' => 'Jl. Raya Bekasi KM 28',
                'city' => 'Bekasi',
                'province' => 'Jawa Barat',
                'postal_code' => '17520',
                'npwp' => '02.890.123.4-432.000',
                'is_subcontractor' => true,
                'subcontractor_services' => json_encode(['instalasi_panel', 'instalasi_listrik']),
                'hourly_rate' => 75000,
                'daily_rate' => 500000,
                'is_pph_subject' => true,
                'pph_category' => 'pph23_jasa',
                'pph_rate' => 2.00,
            ],
            [
                'code' => 'S-INS2',
                'name' => 'PT Wiring Specialist Indonesia',
                'type' => Contact::TYPE_SUPPLIER,
                'email' => 'wiring.specialist@gmail.com',
                'phone' => '081345678901',
                'address' => 'Jl. Industri Selatan III',
                'city' => 'Bekasi',
                'province' => 'Jawa Barat',
                'postal_code' => '17530',
                'npwp' => '02.901.234.5-432.000',
                'is_subcontractor' => true,
                'subcontractor_services' => json_encode(['instalasi_panel', 'wiring']),
                'hourly_rate' => 100000,
                'daily_rate' => 750000,
                'is_pph_subject' => true,
                'pph_category' => 'pph4_2_konstruksi',
                'pph_rate' => 3.00,
            ],
            [
                'code' => 'S-COM1',
                'name' => 'PT Commissioning Expert',
                'type' => Contact::TYPE_SUPPLIER,
                'email' => 'commexpert@gmail.com',
                'phone' => '081456789012',
                'address' => 'Jl. Arteri Kelapa Dua',
                'city' => 'Tangerang',
                'province' => 'Banten',
                'postal_code' => '15810',
                'npwp' => '02.012.345.6-411.000',
                'is_subcontractor' => true,
                'subcontractor_services' => json_encode(['commissioning', 'testing']),
                'hourly_rate' => 150000,
                'daily_rate' => 1000000,
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

        $this->command->info('Created 3 subcontractors');
    }
}
