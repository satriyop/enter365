<?php

namespace Database\Seeders;

use App\Models\Accounting\Account;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    /**
     * Indonesian Standard Chart of Accounts (Bagan Akun Standar)
     * Following SAK EMKM structure for Indonesian SMEs.
     */
    public function run(): void
    {
        $accounts = [
            // ============================================
            // 1. ASET (ASSETS)
            // ============================================
            ['code' => '1-0000', 'name' => 'Aset', 'type' => Account::TYPE_ASSET, 'subtype' => null, 'is_system' => true],
            
            // 1.1 Aset Lancar (Current Assets)
            ['code' => '1-1000', 'name' => 'Aset Lancar', 'type' => Account::TYPE_ASSET, 'subtype' => Account::SUBTYPE_CURRENT_ASSET, 'is_system' => true, 'parent' => '1-0000'],
            ['code' => '1-1001', 'name' => 'Kas', 'type' => Account::TYPE_ASSET, 'subtype' => Account::SUBTYPE_CURRENT_ASSET, 'is_system' => true, 'parent' => '1-1000'],
            ['code' => '1-1002', 'name' => 'Kas Kecil', 'type' => Account::TYPE_ASSET, 'subtype' => Account::SUBTYPE_CURRENT_ASSET, 'is_system' => false, 'parent' => '1-1000'],
            ['code' => '1-1010', 'name' => 'Bank BCA', 'type' => Account::TYPE_ASSET, 'subtype' => Account::SUBTYPE_CURRENT_ASSET, 'is_system' => false, 'parent' => '1-1000'],
            ['code' => '1-1011', 'name' => 'Bank Mandiri', 'type' => Account::TYPE_ASSET, 'subtype' => Account::SUBTYPE_CURRENT_ASSET, 'is_system' => false, 'parent' => '1-1000'],
            ['code' => '1-1012', 'name' => 'Bank BNI', 'type' => Account::TYPE_ASSET, 'subtype' => Account::SUBTYPE_CURRENT_ASSET, 'is_system' => false, 'parent' => '1-1000'],
            ['code' => '1-1100', 'name' => 'Piutang Usaha', 'type' => Account::TYPE_ASSET, 'subtype' => Account::SUBTYPE_CURRENT_ASSET, 'is_system' => true, 'parent' => '1-1000'],
            ['code' => '1-1101', 'name' => 'Cadangan Kerugian Piutang', 'type' => Account::TYPE_ASSET, 'subtype' => Account::SUBTYPE_CURRENT_ASSET, 'is_system' => false, 'parent' => '1-1000'],
            ['code' => '1-1200', 'name' => 'Piutang Lain-lain', 'type' => Account::TYPE_ASSET, 'subtype' => Account::SUBTYPE_CURRENT_ASSET, 'is_system' => false, 'parent' => '1-1000'],
            ['code' => '1-1300', 'name' => 'PPN Masukan', 'type' => Account::TYPE_ASSET, 'subtype' => Account::SUBTYPE_CURRENT_ASSET, 'is_system' => true, 'parent' => '1-1000'],
            ['code' => '1-1400', 'name' => 'Persediaan Barang Dagangan', 'type' => Account::TYPE_ASSET, 'subtype' => Account::SUBTYPE_CURRENT_ASSET, 'is_system' => false, 'parent' => '1-1000'],
            ['code' => '1-1500', 'name' => 'Biaya Dibayar Dimuka', 'type' => Account::TYPE_ASSET, 'subtype' => Account::SUBTYPE_CURRENT_ASSET, 'is_system' => false, 'parent' => '1-1000'],
            ['code' => '1-1501', 'name' => 'Sewa Dibayar Dimuka', 'type' => Account::TYPE_ASSET, 'subtype' => Account::SUBTYPE_CURRENT_ASSET, 'is_system' => false, 'parent' => '1-1500'],
            ['code' => '1-1502', 'name' => 'Asuransi Dibayar Dimuka', 'type' => Account::TYPE_ASSET, 'subtype' => Account::SUBTYPE_CURRENT_ASSET, 'is_system' => false, 'parent' => '1-1500'],
            
            // 1.2 Aset Tetap (Fixed Assets)
            ['code' => '1-2000', 'name' => 'Aset Tetap', 'type' => Account::TYPE_ASSET, 'subtype' => Account::SUBTYPE_FIXED_ASSET, 'is_system' => true, 'parent' => '1-0000'],
            ['code' => '1-2001', 'name' => 'Tanah', 'type' => Account::TYPE_ASSET, 'subtype' => Account::SUBTYPE_FIXED_ASSET, 'is_system' => false, 'parent' => '1-2000'],
            ['code' => '1-2100', 'name' => 'Bangunan', 'type' => Account::TYPE_ASSET, 'subtype' => Account::SUBTYPE_FIXED_ASSET, 'is_system' => false, 'parent' => '1-2000'],
            ['code' => '1-2101', 'name' => 'Akumulasi Penyusutan Bangunan', 'type' => Account::TYPE_ASSET, 'subtype' => Account::SUBTYPE_FIXED_ASSET, 'is_system' => false, 'parent' => '1-2000'],
            ['code' => '1-2200', 'name' => 'Kendaraan', 'type' => Account::TYPE_ASSET, 'subtype' => Account::SUBTYPE_FIXED_ASSET, 'is_system' => false, 'parent' => '1-2000'],
            ['code' => '1-2201', 'name' => 'Akumulasi Penyusutan Kendaraan', 'type' => Account::TYPE_ASSET, 'subtype' => Account::SUBTYPE_FIXED_ASSET, 'is_system' => false, 'parent' => '1-2000'],
            ['code' => '1-2300', 'name' => 'Peralatan Kantor', 'type' => Account::TYPE_ASSET, 'subtype' => Account::SUBTYPE_FIXED_ASSET, 'is_system' => false, 'parent' => '1-2000'],
            ['code' => '1-2301', 'name' => 'Akumulasi Penyusutan Peralatan', 'type' => Account::TYPE_ASSET, 'subtype' => Account::SUBTYPE_FIXED_ASSET, 'is_system' => false, 'parent' => '1-2000'],
            ['code' => '1-2400', 'name' => 'Mesin dan Peralatan', 'type' => Account::TYPE_ASSET, 'subtype' => Account::SUBTYPE_FIXED_ASSET, 'is_system' => false, 'parent' => '1-2000'],
            ['code' => '1-2401', 'name' => 'Akumulasi Penyusutan Mesin', 'type' => Account::TYPE_ASSET, 'subtype' => Account::SUBTYPE_FIXED_ASSET, 'is_system' => false, 'parent' => '1-2000'],

            // ============================================
            // 2. LIABILITAS (LIABILITIES)
            // ============================================
            ['code' => '2-0000', 'name' => 'Liabilitas', 'type' => Account::TYPE_LIABILITY, 'subtype' => null, 'is_system' => true],
            
            // 2.1 Liabilitas Jangka Pendek (Current Liabilities)
            ['code' => '2-1000', 'name' => 'Liabilitas Jangka Pendek', 'type' => Account::TYPE_LIABILITY, 'subtype' => Account::SUBTYPE_CURRENT_LIABILITY, 'is_system' => true, 'parent' => '2-0000'],
            ['code' => '2-1100', 'name' => 'Utang Usaha', 'type' => Account::TYPE_LIABILITY, 'subtype' => Account::SUBTYPE_CURRENT_LIABILITY, 'is_system' => true, 'parent' => '2-1000'],
            ['code' => '2-1200', 'name' => 'PPN Keluaran', 'type' => Account::TYPE_LIABILITY, 'subtype' => Account::SUBTYPE_CURRENT_LIABILITY, 'is_system' => true, 'parent' => '2-1000'],
            ['code' => '2-1300', 'name' => 'Utang Pajak', 'type' => Account::TYPE_LIABILITY, 'subtype' => Account::SUBTYPE_CURRENT_LIABILITY, 'is_system' => false, 'parent' => '2-1000'],
            ['code' => '2-1301', 'name' => 'Utang PPh 21', 'type' => Account::TYPE_LIABILITY, 'subtype' => Account::SUBTYPE_CURRENT_LIABILITY, 'is_system' => false, 'parent' => '2-1300'],
            ['code' => '2-1302', 'name' => 'Utang PPh 23', 'type' => Account::TYPE_LIABILITY, 'subtype' => Account::SUBTYPE_CURRENT_LIABILITY, 'is_system' => false, 'parent' => '2-1300'],
            ['code' => '2-1303', 'name' => 'Utang PPh 25', 'type' => Account::TYPE_LIABILITY, 'subtype' => Account::SUBTYPE_CURRENT_LIABILITY, 'is_system' => false, 'parent' => '2-1300'],
            ['code' => '2-1304', 'name' => 'Utang PPh Badan', 'type' => Account::TYPE_LIABILITY, 'subtype' => Account::SUBTYPE_CURRENT_LIABILITY, 'is_system' => false, 'parent' => '2-1300'],
            ['code' => '2-1400', 'name' => 'Utang Gaji', 'type' => Account::TYPE_LIABILITY, 'subtype' => Account::SUBTYPE_CURRENT_LIABILITY, 'is_system' => false, 'parent' => '2-1000'],
            ['code' => '2-1500', 'name' => 'Utang Lain-lain', 'type' => Account::TYPE_LIABILITY, 'subtype' => Account::SUBTYPE_CURRENT_LIABILITY, 'is_system' => false, 'parent' => '2-1000'],
            ['code' => '2-1600', 'name' => 'Pendapatan Diterima Dimuka', 'type' => Account::TYPE_LIABILITY, 'subtype' => Account::SUBTYPE_CURRENT_LIABILITY, 'is_system' => false, 'parent' => '2-1000'],
            
            // 2.2 Liabilitas Jangka Panjang (Long-term Liabilities)
            ['code' => '2-2000', 'name' => 'Liabilitas Jangka Panjang', 'type' => Account::TYPE_LIABILITY, 'subtype' => Account::SUBTYPE_LONG_TERM_LIABILITY, 'is_system' => true, 'parent' => '2-0000'],
            ['code' => '2-2100', 'name' => 'Utang Bank', 'type' => Account::TYPE_LIABILITY, 'subtype' => Account::SUBTYPE_LONG_TERM_LIABILITY, 'is_system' => false, 'parent' => '2-2000'],
            ['code' => '2-2200', 'name' => 'Utang Leasing', 'type' => Account::TYPE_LIABILITY, 'subtype' => Account::SUBTYPE_LONG_TERM_LIABILITY, 'is_system' => false, 'parent' => '2-2000'],

            // ============================================
            // 3. EKUITAS (EQUITY)
            // ============================================
            ['code' => '3-0000', 'name' => 'Ekuitas', 'type' => Account::TYPE_EQUITY, 'subtype' => Account::SUBTYPE_EQUITY, 'is_system' => true],
            ['code' => '3-1000', 'name' => 'Modal Disetor', 'type' => Account::TYPE_EQUITY, 'subtype' => Account::SUBTYPE_EQUITY, 'is_system' => true, 'parent' => '3-0000'],
            ['code' => '3-2000', 'name' => 'Laba Ditahan', 'type' => Account::TYPE_EQUITY, 'subtype' => Account::SUBTYPE_EQUITY, 'is_system' => true, 'parent' => '3-0000'],
            ['code' => '3-3000', 'name' => 'Laba Tahun Berjalan', 'type' => Account::TYPE_EQUITY, 'subtype' => Account::SUBTYPE_EQUITY, 'is_system' => true, 'parent' => '3-0000'],
            ['code' => '3-4000', 'name' => 'Prive', 'type' => Account::TYPE_EQUITY, 'subtype' => Account::SUBTYPE_EQUITY, 'is_system' => false, 'parent' => '3-0000'],

            // ============================================
            // 4. PENDAPATAN (REVENUE)
            // ============================================
            ['code' => '4-0000', 'name' => 'Pendapatan', 'type' => Account::TYPE_REVENUE, 'subtype' => null, 'is_system' => true],
            
            // 4.1 Pendapatan Usaha (Operating Revenue)
            ['code' => '4-1000', 'name' => 'Pendapatan Usaha', 'type' => Account::TYPE_REVENUE, 'subtype' => Account::SUBTYPE_OPERATING_REVENUE, 'is_system' => true, 'parent' => '4-0000'],
            ['code' => '4-1001', 'name' => 'Pendapatan Penjualan', 'type' => Account::TYPE_REVENUE, 'subtype' => Account::SUBTYPE_OPERATING_REVENUE, 'is_system' => true, 'parent' => '4-1000'],
            ['code' => '4-1002', 'name' => 'Pendapatan Jasa', 'type' => Account::TYPE_REVENUE, 'subtype' => Account::SUBTYPE_OPERATING_REVENUE, 'is_system' => false, 'parent' => '4-1000'],
            ['code' => '4-1003', 'name' => 'Diskon Penjualan', 'type' => Account::TYPE_REVENUE, 'subtype' => Account::SUBTYPE_OPERATING_REVENUE, 'is_system' => false, 'parent' => '4-1000'],
            ['code' => '4-1004', 'name' => 'Retur Penjualan', 'type' => Account::TYPE_REVENUE, 'subtype' => Account::SUBTYPE_OPERATING_REVENUE, 'is_system' => false, 'parent' => '4-1000'],
            
            // 4.2 Pendapatan Lain-lain (Other Revenue)
            ['code' => '4-2000', 'name' => 'Pendapatan Lain-lain', 'type' => Account::TYPE_REVENUE, 'subtype' => Account::SUBTYPE_OTHER_REVENUE, 'is_system' => true, 'parent' => '4-0000'],
            ['code' => '4-2001', 'name' => 'Pendapatan Bunga', 'type' => Account::TYPE_REVENUE, 'subtype' => Account::SUBTYPE_OTHER_REVENUE, 'is_system' => false, 'parent' => '4-2000'],
            ['code' => '4-2002', 'name' => 'Pendapatan Sewa', 'type' => Account::TYPE_REVENUE, 'subtype' => Account::SUBTYPE_OTHER_REVENUE, 'is_system' => false, 'parent' => '4-2000'],
            ['code' => '4-2003', 'name' => 'Keuntungan Penjualan Aset', 'type' => Account::TYPE_REVENUE, 'subtype' => Account::SUBTYPE_OTHER_REVENUE, 'is_system' => false, 'parent' => '4-2000'],

            // ============================================
            // 5. BEBAN (EXPENSES)
            // ============================================
            ['code' => '5-0000', 'name' => 'Beban', 'type' => Account::TYPE_EXPENSE, 'subtype' => null, 'is_system' => true],
            
            // 5.1 Harga Pokok Penjualan
            ['code' => '5-1000', 'name' => 'Harga Pokok Penjualan', 'type' => Account::TYPE_EXPENSE, 'subtype' => Account::SUBTYPE_OPERATING_EXPENSE, 'is_system' => true, 'parent' => '5-0000'],
            ['code' => '5-1001', 'name' => 'HPP Barang Dagangan', 'type' => Account::TYPE_EXPENSE, 'subtype' => Account::SUBTYPE_OPERATING_EXPENSE, 'is_system' => false, 'parent' => '5-1000'],
            ['code' => '5-1002', 'name' => 'Pembelian', 'type' => Account::TYPE_EXPENSE, 'subtype' => Account::SUBTYPE_OPERATING_EXPENSE, 'is_system' => true, 'parent' => '5-1000'],
            ['code' => '5-1003', 'name' => 'Diskon Pembelian', 'type' => Account::TYPE_EXPENSE, 'subtype' => Account::SUBTYPE_OPERATING_EXPENSE, 'is_system' => false, 'parent' => '5-1000'],
            ['code' => '5-1004', 'name' => 'Retur Pembelian', 'type' => Account::TYPE_EXPENSE, 'subtype' => Account::SUBTYPE_OPERATING_EXPENSE, 'is_system' => false, 'parent' => '5-1000'],
            ['code' => '5-1005', 'name' => 'Ongkos Angkut Pembelian', 'type' => Account::TYPE_EXPENSE, 'subtype' => Account::SUBTYPE_OPERATING_EXPENSE, 'is_system' => false, 'parent' => '5-1000'],
            
            // 5.2 Beban Operasional
            ['code' => '5-2000', 'name' => 'Beban Operasional', 'type' => Account::TYPE_EXPENSE, 'subtype' => Account::SUBTYPE_OPERATING_EXPENSE, 'is_system' => true, 'parent' => '5-0000'],
            ['code' => '5-2001', 'name' => 'Beban Gaji', 'type' => Account::TYPE_EXPENSE, 'subtype' => Account::SUBTYPE_OPERATING_EXPENSE, 'is_system' => false, 'parent' => '5-2000'],
            ['code' => '5-2002', 'name' => 'Beban Tunjangan', 'type' => Account::TYPE_EXPENSE, 'subtype' => Account::SUBTYPE_OPERATING_EXPENSE, 'is_system' => false, 'parent' => '5-2000'],
            ['code' => '5-2003', 'name' => 'Beban BPJS', 'type' => Account::TYPE_EXPENSE, 'subtype' => Account::SUBTYPE_OPERATING_EXPENSE, 'is_system' => false, 'parent' => '5-2000'],
            ['code' => '5-2100', 'name' => 'Beban Sewa', 'type' => Account::TYPE_EXPENSE, 'subtype' => Account::SUBTYPE_OPERATING_EXPENSE, 'is_system' => false, 'parent' => '5-2000'],
            ['code' => '5-2200', 'name' => 'Beban Listrik', 'type' => Account::TYPE_EXPENSE, 'subtype' => Account::SUBTYPE_OPERATING_EXPENSE, 'is_system' => false, 'parent' => '5-2000'],
            ['code' => '5-2201', 'name' => 'Beban Air', 'type' => Account::TYPE_EXPENSE, 'subtype' => Account::SUBTYPE_OPERATING_EXPENSE, 'is_system' => false, 'parent' => '5-2000'],
            ['code' => '5-2202', 'name' => 'Beban Telepon & Internet', 'type' => Account::TYPE_EXPENSE, 'subtype' => Account::SUBTYPE_OPERATING_EXPENSE, 'is_system' => false, 'parent' => '5-2000'],
            ['code' => '5-2300', 'name' => 'Beban Perlengkapan Kantor', 'type' => Account::TYPE_EXPENSE, 'subtype' => Account::SUBTYPE_OPERATING_EXPENSE, 'is_system' => false, 'parent' => '5-2000'],
            ['code' => '5-2400', 'name' => 'Beban Transportasi', 'type' => Account::TYPE_EXPENSE, 'subtype' => Account::SUBTYPE_OPERATING_EXPENSE, 'is_system' => false, 'parent' => '5-2000'],
            ['code' => '5-2500', 'name' => 'Beban Penyusutan', 'type' => Account::TYPE_EXPENSE, 'subtype' => Account::SUBTYPE_OPERATING_EXPENSE, 'is_system' => false, 'parent' => '5-2000'],
            ['code' => '5-2600', 'name' => 'Beban Asuransi', 'type' => Account::TYPE_EXPENSE, 'subtype' => Account::SUBTYPE_OPERATING_EXPENSE, 'is_system' => false, 'parent' => '5-2000'],
            ['code' => '5-2700', 'name' => 'Beban Pemeliharaan', 'type' => Account::TYPE_EXPENSE, 'subtype' => Account::SUBTYPE_OPERATING_EXPENSE, 'is_system' => false, 'parent' => '5-2000'],
            ['code' => '5-2800', 'name' => 'Beban Iklan & Promosi', 'type' => Account::TYPE_EXPENSE, 'subtype' => Account::SUBTYPE_OPERATING_EXPENSE, 'is_system' => false, 'parent' => '5-2000'],
            ['code' => '5-2900', 'name' => 'Beban Operasional Lainnya', 'type' => Account::TYPE_EXPENSE, 'subtype' => Account::SUBTYPE_OPERATING_EXPENSE, 'is_system' => false, 'parent' => '5-2000'],
            
            // 5.3 Beban Lain-lain
            ['code' => '5-3000', 'name' => 'Beban Lain-lain', 'type' => Account::TYPE_EXPENSE, 'subtype' => Account::SUBTYPE_OTHER_EXPENSE, 'is_system' => true, 'parent' => '5-0000'],
            ['code' => '5-3001', 'name' => 'Beban Bunga', 'type' => Account::TYPE_EXPENSE, 'subtype' => Account::SUBTYPE_OTHER_EXPENSE, 'is_system' => false, 'parent' => '5-3000'],
            ['code' => '5-3002', 'name' => 'Beban Administrasi Bank', 'type' => Account::TYPE_EXPENSE, 'subtype' => Account::SUBTYPE_OTHER_EXPENSE, 'is_system' => false, 'parent' => '5-3000'],
            ['code' => '5-3003', 'name' => 'Kerugian Piutang', 'type' => Account::TYPE_EXPENSE, 'subtype' => Account::SUBTYPE_OTHER_EXPENSE, 'is_system' => false, 'parent' => '5-3000'],
            ['code' => '5-3004', 'name' => 'Kerugian Penjualan Aset', 'type' => Account::TYPE_EXPENSE, 'subtype' => Account::SUBTYPE_OTHER_EXPENSE, 'is_system' => false, 'parent' => '5-3000'],
            ['code' => '5-3005', 'name' => 'Beban Pajak Penghasilan', 'type' => Account::TYPE_EXPENSE, 'subtype' => Account::SUBTYPE_OTHER_EXPENSE, 'is_system' => false, 'parent' => '5-3000'],
        ];

        // First pass: Create all accounts without parent relationships
        $accountMap = [];
        foreach ($accounts as $accountData) {
            $parentCode = $accountData['parent'] ?? null;
            unset($accountData['parent']);
            
            $account = Account::create($accountData);
            $accountMap[$accountData['code']] = [
                'id' => $account->id,
                'parent_code' => $parentCode,
            ];
        }

        // Second pass: Set parent relationships
        foreach ($accountMap as $code => $data) {
            if ($data['parent_code']) {
                Account::where('code', $code)->update([
                    'parent_id' => $accountMap[$data['parent_code']]['id'],
                ]);
            }
        }
    }
}
