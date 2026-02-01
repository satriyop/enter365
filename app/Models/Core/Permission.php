<?php

namespace App\Models\Core;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use Filterable, HasFactory;

    // Permission groups
    public const GROUP_DASHBOARD = 'dashboard';

    public const GROUP_ACCOUNTS = 'accounts';

    public const GROUP_CONTACTS = 'contacts';

    public const GROUP_PRODUCTS = 'products';

    public const GROUP_INVOICES = 'invoices';

    public const GROUP_QUOTATIONS = 'quotations';

    public const GROUP_BILLS = 'bills';

    public const GROUP_PURCHASE_ORDERS = 'purchase_orders';

    public const GROUP_PAYMENTS = 'payments';

    public const GROUP_JOURNALS = 'journals';

    public const GROUP_INVENTORY = 'inventory';

    public const GROUP_DELIVERY_ORDERS = 'delivery_orders';

    public const GROUP_WORK_ORDERS = 'work_orders';

    public const GROUP_BOMS = 'boms';

    public const GROUP_PROJECTS = 'projects';

    public const GROUP_BUDGETS = 'budgets';

    public const GROUP_REPORTS = 'reports';

    public const GROUP_SETTINGS = 'settings';

    public const GROUP_USERS = 'users';

    public const GROUP_SALES_RETURNS = 'sales_returns';

    public const GROUP_GOODS_RECEIPT_NOTES = 'goods_receipt_notes';

    public const GROUP_PURCHASE_RETURNS = 'purchase_returns';

    public const GROUP_MATERIAL_REQUISITIONS = 'material_requisitions';

    public const GROUP_SUBCONTRACTOR_WORK_ORDERS = 'subcontractor_work_orders';

    public const GROUP_STOCK_OPNAMES = 'stock_opnames';

    public const GROUP_FISCAL_PERIODS = 'fiscal_periods';

    public const GROUP_WAREHOUSES = 'warehouses';

    protected $fillable = [
        'name',
        'display_name',
        'group',
        'description',
    ];

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    /**
     * Get permission by name.
     */
    public static function findByName(string $name): ?self
    {
        return static::where('name', $name)->first();
    }

    /**
     * Get all permissions grouped by group.
     *
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, Permission>>
     */
    public static function allGrouped(): \Illuminate\Support\Collection
    {
        return static::orderBy('group')->orderBy('name')->get()->groupBy('group');
    }

    /**
     * Get default permissions list.
     *
     * @return array<array{name: string, display_name: string, group: string, description: string}>
     */
    public static function getDefaultPermissions(): array
    {
        return [
            // Accounts
            ['name' => 'accounts.view', 'display_name' => 'Lihat Akun', 'group' => self::GROUP_ACCOUNTS, 'description' => 'Melihat daftar akun'],
            ['name' => 'accounts.create', 'display_name' => 'Buat Akun', 'group' => self::GROUP_ACCOUNTS, 'description' => 'Membuat akun baru'],
            ['name' => 'accounts.edit', 'display_name' => 'Edit Akun', 'group' => self::GROUP_ACCOUNTS, 'description' => 'Mengubah akun'],
            ['name' => 'accounts.delete', 'display_name' => 'Hapus Akun', 'group' => self::GROUP_ACCOUNTS, 'description' => 'Menghapus akun'],

            // Contacts
            ['name' => 'contacts.view', 'display_name' => 'Lihat Kontak', 'group' => self::GROUP_CONTACTS, 'description' => 'Melihat daftar kontak'],
            ['name' => 'contacts.create', 'display_name' => 'Buat Kontak', 'group' => self::GROUP_CONTACTS, 'description' => 'Membuat kontak baru'],
            ['name' => 'contacts.edit', 'display_name' => 'Edit Kontak', 'group' => self::GROUP_CONTACTS, 'description' => 'Mengubah kontak'],
            ['name' => 'contacts.delete', 'display_name' => 'Hapus Kontak', 'group' => self::GROUP_CONTACTS, 'description' => 'Menghapus kontak'],

            // Products
            ['name' => 'products.view', 'display_name' => 'Lihat Produk', 'group' => self::GROUP_PRODUCTS, 'description' => 'Melihat daftar produk'],
            ['name' => 'products.create', 'display_name' => 'Buat Produk', 'group' => self::GROUP_PRODUCTS, 'description' => 'Membuat produk baru'],
            ['name' => 'products.edit', 'display_name' => 'Edit Produk', 'group' => self::GROUP_PRODUCTS, 'description' => 'Mengubah produk'],
            ['name' => 'products.delete', 'display_name' => 'Hapus Produk', 'group' => self::GROUP_PRODUCTS, 'description' => 'Menghapus produk'],

            // Invoices
            ['name' => 'invoices.view', 'display_name' => 'Lihat Faktur', 'group' => self::GROUP_INVOICES, 'description' => 'Melihat daftar faktur'],
            ['name' => 'invoices.create', 'display_name' => 'Buat Faktur', 'group' => self::GROUP_INVOICES, 'description' => 'Membuat faktur baru'],
            ['name' => 'invoices.edit', 'display_name' => 'Edit Faktur', 'group' => self::GROUP_INVOICES, 'description' => 'Mengubah faktur'],
            ['name' => 'invoices.delete', 'display_name' => 'Hapus Faktur', 'group' => self::GROUP_INVOICES, 'description' => 'Menghapus faktur'],
            ['name' => 'invoices.post', 'display_name' => 'Posting Faktur', 'group' => self::GROUP_INVOICES, 'description' => 'Memposting faktur'],
            ['name' => 'invoices.void', 'display_name' => 'Void Faktur', 'group' => self::GROUP_INVOICES, 'description' => 'Membatalkan faktur'],

            // Quotations
            ['name' => 'quotations.view', 'display_name' => 'Lihat Penawaran', 'group' => self::GROUP_QUOTATIONS, 'description' => 'Melihat daftar penawaran'],
            ['name' => 'quotations.create', 'display_name' => 'Buat Penawaran', 'group' => self::GROUP_QUOTATIONS, 'description' => 'Membuat penawaran baru'],
            ['name' => 'quotations.edit', 'display_name' => 'Edit Penawaran', 'group' => self::GROUP_QUOTATIONS, 'description' => 'Mengubah penawaran'],
            ['name' => 'quotations.delete', 'display_name' => 'Hapus Penawaran', 'group' => self::GROUP_QUOTATIONS, 'description' => 'Menghapus penawaran'],
            ['name' => 'quotations.approve', 'display_name' => 'Setujui Penawaran', 'group' => self::GROUP_QUOTATIONS, 'description' => 'Menyetujui penawaran'],

            // Bills
            ['name' => 'bills.view', 'display_name' => 'Lihat Tagihan', 'group' => self::GROUP_BILLS, 'description' => 'Melihat daftar tagihan'],
            ['name' => 'bills.create', 'display_name' => 'Buat Tagihan', 'group' => self::GROUP_BILLS, 'description' => 'Membuat tagihan baru'],
            ['name' => 'bills.edit', 'display_name' => 'Edit Tagihan', 'group' => self::GROUP_BILLS, 'description' => 'Mengubah tagihan'],
            ['name' => 'bills.delete', 'display_name' => 'Hapus Tagihan', 'group' => self::GROUP_BILLS, 'description' => 'Menghapus tagihan'],
            ['name' => 'bills.post', 'display_name' => 'Posting Tagihan', 'group' => self::GROUP_BILLS, 'description' => 'Memposting tagihan'],

            // Purchase Orders
            ['name' => 'purchase_orders.view', 'display_name' => 'Lihat PO', 'group' => self::GROUP_PURCHASE_ORDERS, 'description' => 'Melihat daftar purchase order'],
            ['name' => 'purchase_orders.create', 'display_name' => 'Buat PO', 'group' => self::GROUP_PURCHASE_ORDERS, 'description' => 'Membuat purchase order baru'],
            ['name' => 'purchase_orders.edit', 'display_name' => 'Edit PO', 'group' => self::GROUP_PURCHASE_ORDERS, 'description' => 'Mengubah purchase order'],
            ['name' => 'purchase_orders.delete', 'display_name' => 'Hapus PO', 'group' => self::GROUP_PURCHASE_ORDERS, 'description' => 'Menghapus purchase order'],
            ['name' => 'purchase_orders.approve', 'display_name' => 'Setujui PO', 'group' => self::GROUP_PURCHASE_ORDERS, 'description' => 'Menyetujui purchase order'],

            // Payments
            ['name' => 'payments.view', 'display_name' => 'Lihat Pembayaran', 'group' => self::GROUP_PAYMENTS, 'description' => 'Melihat daftar pembayaran'],
            ['name' => 'payments.create', 'display_name' => 'Buat Pembayaran', 'group' => self::GROUP_PAYMENTS, 'description' => 'Membuat pembayaran baru'],
            ['name' => 'payments.void', 'display_name' => 'Void Pembayaran', 'group' => self::GROUP_PAYMENTS, 'description' => 'Membatalkan pembayaran'],

            // Journals
            ['name' => 'journals.view', 'display_name' => 'Lihat Jurnal', 'group' => self::GROUP_JOURNALS, 'description' => 'Melihat daftar jurnal'],
            ['name' => 'journals.create', 'display_name' => 'Buat Jurnal', 'group' => self::GROUP_JOURNALS, 'description' => 'Membuat jurnal baru'],
            ['name' => 'journals.post', 'display_name' => 'Posting Jurnal', 'group' => self::GROUP_JOURNALS, 'description' => 'Memposting jurnal'],
            ['name' => 'journals.reverse', 'display_name' => 'Reverse Jurnal', 'group' => self::GROUP_JOURNALS, 'description' => 'Membalik jurnal'],

            // Inventory
            ['name' => 'inventory.view', 'display_name' => 'Lihat Inventori', 'group' => self::GROUP_INVENTORY, 'description' => 'Melihat inventori'],
            ['name' => 'inventory.stock_in', 'display_name' => 'Stok Masuk', 'group' => self::GROUP_INVENTORY, 'description' => 'Mencatat stok masuk'],
            ['name' => 'inventory.stock_out', 'display_name' => 'Stok Keluar', 'group' => self::GROUP_INVENTORY, 'description' => 'Mencatat stok keluar'],
            ['name' => 'inventory.adjust', 'display_name' => 'Penyesuaian Stok', 'group' => self::GROUP_INVENTORY, 'description' => 'Menyesuaikan stok'],
            ['name' => 'inventory.transfer', 'display_name' => 'Transfer Stok', 'group' => self::GROUP_INVENTORY, 'description' => 'Transfer antar gudang'],

            // Delivery Orders
            ['name' => 'delivery_orders.view', 'display_name' => 'Lihat Surat Jalan', 'group' => self::GROUP_DELIVERY_ORDERS, 'description' => 'Melihat daftar surat jalan'],
            ['name' => 'delivery_orders.create', 'display_name' => 'Buat Surat Jalan', 'group' => self::GROUP_DELIVERY_ORDERS, 'description' => 'Membuat surat jalan baru'],
            ['name' => 'delivery_orders.edit', 'display_name' => 'Edit Surat Jalan', 'group' => self::GROUP_DELIVERY_ORDERS, 'description' => 'Mengubah surat jalan'],
            ['name' => 'delivery_orders.delete', 'display_name' => 'Hapus Surat Jalan', 'group' => self::GROUP_DELIVERY_ORDERS, 'description' => 'Menghapus surat jalan'],

            // Work Orders
            ['name' => 'work_orders.view', 'display_name' => 'Lihat Work Order', 'group' => self::GROUP_WORK_ORDERS, 'description' => 'Melihat daftar work order'],
            ['name' => 'work_orders.create', 'display_name' => 'Buat Work Order', 'group' => self::GROUP_WORK_ORDERS, 'description' => 'Membuat work order baru'],
            ['name' => 'work_orders.edit', 'display_name' => 'Edit Work Order', 'group' => self::GROUP_WORK_ORDERS, 'description' => 'Mengubah work order'],
            ['name' => 'work_orders.delete', 'display_name' => 'Hapus Work Order', 'group' => self::GROUP_WORK_ORDERS, 'description' => 'Menghapus work order'],

            // BOMs
            ['name' => 'boms.view', 'display_name' => 'Lihat BOM', 'group' => self::GROUP_BOMS, 'description' => 'Melihat daftar bill of materials'],
            ['name' => 'boms.create', 'display_name' => 'Buat BOM', 'group' => self::GROUP_BOMS, 'description' => 'Membuat bill of materials baru'],
            ['name' => 'boms.edit', 'display_name' => 'Edit BOM', 'group' => self::GROUP_BOMS, 'description' => 'Mengubah bill of materials'],
            ['name' => 'boms.delete', 'display_name' => 'Hapus BOM', 'group' => self::GROUP_BOMS, 'description' => 'Menghapus bill of materials'],

            // Projects
            ['name' => 'projects.view', 'display_name' => 'Lihat Proyek', 'group' => self::GROUP_PROJECTS, 'description' => 'Melihat daftar proyek'],
            ['name' => 'projects.create', 'display_name' => 'Buat Proyek', 'group' => self::GROUP_PROJECTS, 'description' => 'Membuat proyek baru'],
            ['name' => 'projects.edit', 'display_name' => 'Edit Proyek', 'group' => self::GROUP_PROJECTS, 'description' => 'Mengubah proyek'],
            ['name' => 'projects.delete', 'display_name' => 'Hapus Proyek', 'group' => self::GROUP_PROJECTS, 'description' => 'Menghapus proyek'],

            // Budgets
            ['name' => 'budgets.view', 'display_name' => 'Lihat Anggaran', 'group' => self::GROUP_BUDGETS, 'description' => 'Melihat anggaran'],
            ['name' => 'budgets.create', 'display_name' => 'Buat Anggaran', 'group' => self::GROUP_BUDGETS, 'description' => 'Membuat anggaran baru'],
            ['name' => 'budgets.edit', 'display_name' => 'Edit Anggaran', 'group' => self::GROUP_BUDGETS, 'description' => 'Mengubah anggaran'],
            ['name' => 'budgets.delete', 'display_name' => 'Hapus Anggaran', 'group' => self::GROUP_BUDGETS, 'description' => 'Menghapus anggaran'],
            ['name' => 'budgets.approve', 'display_name' => 'Setujui Anggaran', 'group' => self::GROUP_BUDGETS, 'description' => 'Menyetujui anggaran'],

            // Dashboard
            ['name' => 'dashboard.view', 'display_name' => 'Lihat Dashboard', 'group' => self::GROUP_DASHBOARD, 'description' => 'Melihat ringkasan dashboard'],
            ['name' => 'dashboard.financials', 'display_name' => 'Dashboard Keuangan', 'group' => self::GROUP_DASHBOARD, 'description' => 'Melihat data keuangan di dashboard'],
            ['name' => 'dashboard.kpis', 'display_name' => 'Dashboard KPI', 'group' => self::GROUP_DASHBOARD, 'description' => 'Melihat indikator kinerja di dashboard'],

            // Reports
            ['name' => 'reports.financial', 'display_name' => 'Laporan Keuangan', 'group' => self::GROUP_REPORTS, 'description' => 'Melihat laporan keuangan'],
            ['name' => 'reports.tax', 'display_name' => 'Laporan Pajak', 'group' => self::GROUP_REPORTS, 'description' => 'Melihat laporan pajak'],
            ['name' => 'reports.aging', 'display_name' => 'Laporan Aging', 'group' => self::GROUP_REPORTS, 'description' => 'Melihat laporan aging'],
            ['name' => 'reports.cash_flow', 'display_name' => 'Laporan Arus Kas', 'group' => self::GROUP_REPORTS, 'description' => 'Melihat laporan arus kas'],
            ['name' => 'reports.project', 'display_name' => 'Laporan Proyek', 'group' => self::GROUP_REPORTS, 'description' => 'Melihat laporan profitabilitas proyek'],
            ['name' => 'reports.manufacturing', 'display_name' => 'Laporan Manufaktur', 'group' => self::GROUP_REPORTS, 'description' => 'Melihat laporan biaya work order dan subkontraktor'],
            ['name' => 'reports.cogs', 'display_name' => 'Laporan HPP', 'group' => self::GROUP_REPORTS, 'description' => 'Melihat laporan harga pokok penjualan'],
            ['name' => 'reports.export', 'display_name' => 'Export Laporan', 'group' => self::GROUP_REPORTS, 'description' => 'Export laporan'],

            // Settings
            ['name' => 'settings.fiscal_periods', 'display_name' => 'Kelola Periode Fiskal', 'group' => self::GROUP_SETTINGS, 'description' => 'Mengelola periode fiskal'],
            ['name' => 'settings.close_period', 'display_name' => 'Tutup Periode', 'group' => self::GROUP_SETTINGS, 'description' => 'Menutup periode fiskal'],
            ['name' => 'settings.warehouses', 'display_name' => 'Kelola Gudang', 'group' => self::GROUP_SETTINGS, 'description' => 'Mengelola gudang'],
            ['name' => 'settings.company_profile', 'display_name' => 'Kelola Profil Perusahaan', 'group' => self::GROUP_SETTINGS, 'description' => 'Mengelola profil perusahaan'],
            ['name' => 'settings.features', 'display_name' => 'Lihat Fitur Modul', 'group' => self::GROUP_SETTINGS, 'description' => 'Melihat status modul fitur'],

            // Users
            ['name' => 'users.view', 'display_name' => 'Lihat Pengguna', 'group' => self::GROUP_USERS, 'description' => 'Melihat daftar pengguna'],
            ['name' => 'users.create', 'display_name' => 'Buat Pengguna', 'group' => self::GROUP_USERS, 'description' => 'Membuat pengguna baru'],
            ['name' => 'users.edit', 'display_name' => 'Edit Pengguna', 'group' => self::GROUP_USERS, 'description' => 'Mengubah pengguna'],
            ['name' => 'users.delete', 'display_name' => 'Hapus Pengguna', 'group' => self::GROUP_USERS, 'description' => 'Menghapus pengguna'],
            ['name' => 'users.manage_roles', 'display_name' => 'Kelola Role', 'group' => self::GROUP_USERS, 'description' => 'Mengelola role pengguna'],

            // Sales Returns
            ['name' => 'sales_returns.view', 'display_name' => 'Lihat Retur Penjualan', 'group' => self::GROUP_SALES_RETURNS, 'description' => 'Melihat daftar retur penjualan'],
            ['name' => 'sales_returns.create', 'display_name' => 'Buat Retur Penjualan', 'group' => self::GROUP_SALES_RETURNS, 'description' => 'Membuat retur penjualan baru'],
            ['name' => 'sales_returns.edit', 'display_name' => 'Edit Retur Penjualan', 'group' => self::GROUP_SALES_RETURNS, 'description' => 'Mengubah retur penjualan'],
            ['name' => 'sales_returns.delete', 'display_name' => 'Hapus Retur Penjualan', 'group' => self::GROUP_SALES_RETURNS, 'description' => 'Menghapus retur penjualan'],
            ['name' => 'sales_returns.approve', 'display_name' => 'Setujui Retur Penjualan', 'group' => self::GROUP_SALES_RETURNS, 'description' => 'Menyetujui retur penjualan'],

            // Goods Receipt Notes
            ['name' => 'goods_receipt_notes.view', 'display_name' => 'Lihat Penerimaan Barang', 'group' => self::GROUP_GOODS_RECEIPT_NOTES, 'description' => 'Melihat daftar penerimaan barang'],
            ['name' => 'goods_receipt_notes.create', 'display_name' => 'Buat Penerimaan Barang', 'group' => self::GROUP_GOODS_RECEIPT_NOTES, 'description' => 'Membuat penerimaan barang baru'],
            ['name' => 'goods_receipt_notes.edit', 'display_name' => 'Edit Penerimaan Barang', 'group' => self::GROUP_GOODS_RECEIPT_NOTES, 'description' => 'Mengubah penerimaan barang'],
            ['name' => 'goods_receipt_notes.delete', 'display_name' => 'Hapus Penerimaan Barang', 'group' => self::GROUP_GOODS_RECEIPT_NOTES, 'description' => 'Menghapus penerimaan barang'],
            ['name' => 'goods_receipt_notes.receive', 'display_name' => 'Terima Barang', 'group' => self::GROUP_GOODS_RECEIPT_NOTES, 'description' => 'Konfirmasi penerimaan barang'],

            // Purchase Returns
            ['name' => 'purchase_returns.view', 'display_name' => 'Lihat Retur Pembelian', 'group' => self::GROUP_PURCHASE_RETURNS, 'description' => 'Melihat daftar retur pembelian'],
            ['name' => 'purchase_returns.create', 'display_name' => 'Buat Retur Pembelian', 'group' => self::GROUP_PURCHASE_RETURNS, 'description' => 'Membuat retur pembelian baru'],
            ['name' => 'purchase_returns.edit', 'display_name' => 'Edit Retur Pembelian', 'group' => self::GROUP_PURCHASE_RETURNS, 'description' => 'Mengubah retur pembelian'],
            ['name' => 'purchase_returns.delete', 'display_name' => 'Hapus Retur Pembelian', 'group' => self::GROUP_PURCHASE_RETURNS, 'description' => 'Menghapus retur pembelian'],
            ['name' => 'purchase_returns.approve', 'display_name' => 'Setujui Retur Pembelian', 'group' => self::GROUP_PURCHASE_RETURNS, 'description' => 'Menyetujui retur pembelian'],

            // Material Requisitions
            ['name' => 'material_requisitions.view', 'display_name' => 'Lihat Permintaan Material', 'group' => self::GROUP_MATERIAL_REQUISITIONS, 'description' => 'Melihat daftar permintaan material'],
            ['name' => 'material_requisitions.create', 'display_name' => 'Buat Permintaan Material', 'group' => self::GROUP_MATERIAL_REQUISITIONS, 'description' => 'Membuat permintaan material baru'],
            ['name' => 'material_requisitions.edit', 'display_name' => 'Edit Permintaan Material', 'group' => self::GROUP_MATERIAL_REQUISITIONS, 'description' => 'Mengubah permintaan material'],
            ['name' => 'material_requisitions.delete', 'display_name' => 'Hapus Permintaan Material', 'group' => self::GROUP_MATERIAL_REQUISITIONS, 'description' => 'Menghapus permintaan material'],
            ['name' => 'material_requisitions.approve', 'display_name' => 'Setujui Permintaan Material', 'group' => self::GROUP_MATERIAL_REQUISITIONS, 'description' => 'Menyetujui permintaan material'],

            // Subcontractor Work Orders
            ['name' => 'subcontractor_work_orders.view', 'display_name' => 'Lihat SPK Subkon', 'group' => self::GROUP_SUBCONTRACTOR_WORK_ORDERS, 'description' => 'Melihat daftar SPK subkontraktor'],
            ['name' => 'subcontractor_work_orders.create', 'display_name' => 'Buat SPK Subkon', 'group' => self::GROUP_SUBCONTRACTOR_WORK_ORDERS, 'description' => 'Membuat SPK subkontraktor baru'],
            ['name' => 'subcontractor_work_orders.edit', 'display_name' => 'Edit SPK Subkon', 'group' => self::GROUP_SUBCONTRACTOR_WORK_ORDERS, 'description' => 'Mengubah SPK subkontraktor'],
            ['name' => 'subcontractor_work_orders.delete', 'display_name' => 'Hapus SPK Subkon', 'group' => self::GROUP_SUBCONTRACTOR_WORK_ORDERS, 'description' => 'Menghapus SPK subkontraktor'],

            // Stock Opnames
            ['name' => 'stock_opnames.view', 'display_name' => 'Lihat Stok Opname', 'group' => self::GROUP_STOCK_OPNAMES, 'description' => 'Melihat daftar stok opname'],
            ['name' => 'stock_opnames.create', 'display_name' => 'Buat Stok Opname', 'group' => self::GROUP_STOCK_OPNAMES, 'description' => 'Membuat stok opname baru'],
            ['name' => 'stock_opnames.edit', 'display_name' => 'Edit Stok Opname', 'group' => self::GROUP_STOCK_OPNAMES, 'description' => 'Mengubah stok opname'],
            ['name' => 'stock_opnames.delete', 'display_name' => 'Hapus Stok Opname', 'group' => self::GROUP_STOCK_OPNAMES, 'description' => 'Menghapus stok opname'],
            ['name' => 'stock_opnames.approve', 'display_name' => 'Setujui Stok Opname', 'group' => self::GROUP_STOCK_OPNAMES, 'description' => 'Menyetujui stok opname'],

            // Fiscal Periods
            ['name' => 'fiscal_periods.view', 'display_name' => 'Lihat Periode Fiskal', 'group' => self::GROUP_FISCAL_PERIODS, 'description' => 'Melihat daftar periode fiskal'],
            ['name' => 'fiscal_periods.create', 'display_name' => 'Buat Periode Fiskal', 'group' => self::GROUP_FISCAL_PERIODS, 'description' => 'Membuat periode fiskal baru'],
            ['name' => 'fiscal_periods.edit', 'display_name' => 'Edit Periode Fiskal', 'group' => self::GROUP_FISCAL_PERIODS, 'description' => 'Mengubah periode fiskal'],
            ['name' => 'fiscal_periods.delete', 'display_name' => 'Hapus Periode Fiskal', 'group' => self::GROUP_FISCAL_PERIODS, 'description' => 'Menghapus periode fiskal'],
            ['name' => 'fiscal_periods.close', 'display_name' => 'Tutup Periode Fiskal', 'group' => self::GROUP_FISCAL_PERIODS, 'description' => 'Menutup periode fiskal'],
            ['name' => 'fiscal_periods.reopen', 'display_name' => 'Buka Periode Fiskal', 'group' => self::GROUP_FISCAL_PERIODS, 'description' => 'Membuka kembali periode fiskal'],

            // Warehouses
            ['name' => 'warehouses.view', 'display_name' => 'Lihat Gudang', 'group' => self::GROUP_WAREHOUSES, 'description' => 'Melihat daftar gudang'],
            ['name' => 'warehouses.create', 'display_name' => 'Buat Gudang', 'group' => self::GROUP_WAREHOUSES, 'description' => 'Membuat gudang baru'],
            ['name' => 'warehouses.edit', 'display_name' => 'Edit Gudang', 'group' => self::GROUP_WAREHOUSES, 'description' => 'Mengubah gudang'],
            ['name' => 'warehouses.delete', 'display_name' => 'Hapus Gudang', 'group' => self::GROUP_WAREHOUSES, 'description' => 'Menghapus gudang'],
        ];
    }

    /**
     * Scope by group.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Permission>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Permission>
     */
    public function scopeInGroup($query, string $group)
    {
        return $query->where('group', $group);
    }
}
