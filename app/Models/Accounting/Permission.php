<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory;

    // Permission groups
    public const GROUP_ACCOUNTS = 'accounts';

    public const GROUP_CONTACTS = 'contacts';

    public const GROUP_PRODUCTS = 'products';

    public const GROUP_INVOICES = 'invoices';

    public const GROUP_BILLS = 'bills';

    public const GROUP_PAYMENTS = 'payments';

    public const GROUP_JOURNALS = 'journals';

    public const GROUP_INVENTORY = 'inventory';

    public const GROUP_BUDGETS = 'budgets';

    public const GROUP_REPORTS = 'reports';

    public const GROUP_SETTINGS = 'settings';

    public const GROUP_USERS = 'users';

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

            // Bills
            ['name' => 'bills.view', 'display_name' => 'Lihat Tagihan', 'group' => self::GROUP_BILLS, 'description' => 'Melihat daftar tagihan'],
            ['name' => 'bills.create', 'display_name' => 'Buat Tagihan', 'group' => self::GROUP_BILLS, 'description' => 'Membuat tagihan baru'],
            ['name' => 'bills.edit', 'display_name' => 'Edit Tagihan', 'group' => self::GROUP_BILLS, 'description' => 'Mengubah tagihan'],
            ['name' => 'bills.delete', 'display_name' => 'Hapus Tagihan', 'group' => self::GROUP_BILLS, 'description' => 'Menghapus tagihan'],
            ['name' => 'bills.post', 'display_name' => 'Posting Tagihan', 'group' => self::GROUP_BILLS, 'description' => 'Memposting tagihan'],

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

            // Budgets
            ['name' => 'budgets.view', 'display_name' => 'Lihat Anggaran', 'group' => self::GROUP_BUDGETS, 'description' => 'Melihat anggaran'],
            ['name' => 'budgets.create', 'display_name' => 'Buat Anggaran', 'group' => self::GROUP_BUDGETS, 'description' => 'Membuat anggaran baru'],
            ['name' => 'budgets.edit', 'display_name' => 'Edit Anggaran', 'group' => self::GROUP_BUDGETS, 'description' => 'Mengubah anggaran'],
            ['name' => 'budgets.delete', 'display_name' => 'Hapus Anggaran', 'group' => self::GROUP_BUDGETS, 'description' => 'Menghapus anggaran'],
            ['name' => 'budgets.approve', 'display_name' => 'Setujui Anggaran', 'group' => self::GROUP_BUDGETS, 'description' => 'Menyetujui anggaran'],

            // Reports
            ['name' => 'reports.financial', 'display_name' => 'Laporan Keuangan', 'group' => self::GROUP_REPORTS, 'description' => 'Melihat laporan keuangan'],
            ['name' => 'reports.tax', 'display_name' => 'Laporan Pajak', 'group' => self::GROUP_REPORTS, 'description' => 'Melihat laporan pajak'],
            ['name' => 'reports.aging', 'display_name' => 'Laporan Aging', 'group' => self::GROUP_REPORTS, 'description' => 'Melihat laporan aging'],
            ['name' => 'reports.export', 'display_name' => 'Export Laporan', 'group' => self::GROUP_REPORTS, 'description' => 'Export laporan'],

            // Settings
            ['name' => 'settings.fiscal_periods', 'display_name' => 'Kelola Periode Fiskal', 'group' => self::GROUP_SETTINGS, 'description' => 'Mengelola periode fiskal'],
            ['name' => 'settings.close_period', 'display_name' => 'Tutup Periode', 'group' => self::GROUP_SETTINGS, 'description' => 'Menutup periode fiskal'],
            ['name' => 'settings.warehouses', 'display_name' => 'Kelola Gudang', 'group' => self::GROUP_SETTINGS, 'description' => 'Mengelola gudang'],

            // Users
            ['name' => 'users.view', 'display_name' => 'Lihat Pengguna', 'group' => self::GROUP_USERS, 'description' => 'Melihat daftar pengguna'],
            ['name' => 'users.create', 'display_name' => 'Buat Pengguna', 'group' => self::GROUP_USERS, 'description' => 'Membuat pengguna baru'],
            ['name' => 'users.edit', 'display_name' => 'Edit Pengguna', 'group' => self::GROUP_USERS, 'description' => 'Mengubah pengguna'],
            ['name' => 'users.delete', 'display_name' => 'Hapus Pengguna', 'group' => self::GROUP_USERS, 'description' => 'Menghapus pengguna'],
            ['name' => 'users.manage_roles', 'display_name' => 'Kelola Role', 'group' => self::GROUP_USERS, 'description' => 'Mengelola role pengguna'],
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
