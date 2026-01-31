<?php

use App\Models\Core\Permission;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * New permissions added for the authorization layer.
     *
     * @var array<array{name: string, display_name: string, group: string, description: string}>
     */
    private array $newPermissions = [
        ['name' => 'invoices.post', 'display_name' => 'Posting Faktur', 'group' => 'invoices', 'description' => 'Memposting faktur'],
        ['name' => 'invoices.void', 'display_name' => 'Void Faktur', 'group' => 'invoices', 'description' => 'Membatalkan faktur'],
        ['name' => 'quotations.view', 'display_name' => 'Lihat Penawaran', 'group' => 'quotations', 'description' => 'Melihat daftar penawaran'],
        ['name' => 'quotations.create', 'display_name' => 'Buat Penawaran', 'group' => 'quotations', 'description' => 'Membuat penawaran baru'],
        ['name' => 'quotations.edit', 'display_name' => 'Edit Penawaran', 'group' => 'quotations', 'description' => 'Mengubah penawaran'],
        ['name' => 'quotations.delete', 'display_name' => 'Hapus Penawaran', 'group' => 'quotations', 'description' => 'Menghapus penawaran'],
        ['name' => 'quotations.approve', 'display_name' => 'Setujui Penawaran', 'group' => 'quotations', 'description' => 'Menyetujui penawaran'],
        ['name' => 'purchase_orders.view', 'display_name' => 'Lihat PO', 'group' => 'purchase_orders', 'description' => 'Melihat daftar purchase order'],
        ['name' => 'purchase_orders.create', 'display_name' => 'Buat PO', 'group' => 'purchase_orders', 'description' => 'Membuat purchase order baru'],
        ['name' => 'purchase_orders.edit', 'display_name' => 'Edit PO', 'group' => 'purchase_orders', 'description' => 'Mengubah purchase order'],
        ['name' => 'purchase_orders.delete', 'display_name' => 'Hapus PO', 'group' => 'purchase_orders', 'description' => 'Menghapus purchase order'],
        ['name' => 'purchase_orders.approve', 'display_name' => 'Setujui PO', 'group' => 'purchase_orders', 'description' => 'Menyetujui purchase order'],
        ['name' => 'delivery_orders.view', 'display_name' => 'Lihat Surat Jalan', 'group' => 'delivery_orders', 'description' => 'Melihat daftar surat jalan'],
        ['name' => 'delivery_orders.create', 'display_name' => 'Buat Surat Jalan', 'group' => 'delivery_orders', 'description' => 'Membuat surat jalan baru'],
        ['name' => 'delivery_orders.edit', 'display_name' => 'Edit Surat Jalan', 'group' => 'delivery_orders', 'description' => 'Mengubah surat jalan'],
        ['name' => 'delivery_orders.delete', 'display_name' => 'Hapus Surat Jalan', 'group' => 'delivery_orders', 'description' => 'Menghapus surat jalan'],
        ['name' => 'work_orders.view', 'display_name' => 'Lihat Work Order', 'group' => 'work_orders', 'description' => 'Melihat daftar work order'],
        ['name' => 'work_orders.create', 'display_name' => 'Buat Work Order', 'group' => 'work_orders', 'description' => 'Membuat work order baru'],
        ['name' => 'work_orders.edit', 'display_name' => 'Edit Work Order', 'group' => 'work_orders', 'description' => 'Mengubah work order'],
        ['name' => 'work_orders.delete', 'display_name' => 'Hapus Work Order', 'group' => 'work_orders', 'description' => 'Menghapus work order'],
        ['name' => 'boms.view', 'display_name' => 'Lihat BOM', 'group' => 'boms', 'description' => 'Melihat daftar bill of materials'],
        ['name' => 'boms.create', 'display_name' => 'Buat BOM', 'group' => 'boms', 'description' => 'Membuat bill of materials baru'],
        ['name' => 'boms.edit', 'display_name' => 'Edit BOM', 'group' => 'boms', 'description' => 'Mengubah bill of materials'],
        ['name' => 'boms.delete', 'display_name' => 'Hapus BOM', 'group' => 'boms', 'description' => 'Menghapus bill of materials'],
        ['name' => 'projects.view', 'display_name' => 'Lihat Proyek', 'group' => 'projects', 'description' => 'Melihat daftar proyek'],
        ['name' => 'projects.create', 'display_name' => 'Buat Proyek', 'group' => 'projects', 'description' => 'Membuat proyek baru'],
        ['name' => 'projects.edit', 'display_name' => 'Edit Proyek', 'group' => 'projects', 'description' => 'Mengubah proyek'],
        ['name' => 'projects.delete', 'display_name' => 'Hapus Proyek', 'group' => 'projects', 'description' => 'Menghapus proyek'],
        ['name' => 'budgets.approve', 'display_name' => 'Setujui Anggaran', 'group' => 'budgets', 'description' => 'Menyetujui anggaran'],
        ['name' => 'settings.company_profile', 'display_name' => 'Kelola Profil Perusahaan', 'group' => 'settings', 'description' => 'Mengelola profil perusahaan'],
        ['name' => 'users.manage_roles', 'display_name' => 'Kelola Role', 'group' => 'users', 'description' => 'Mengelola role pengguna'],
        ['name' => 'bills.post', 'display_name' => 'Posting Tagihan', 'group' => 'bills', 'description' => 'Memposting tagihan'],
        ['name' => 'journals.post', 'display_name' => 'Posting Jurnal', 'group' => 'journals', 'description' => 'Memposting jurnal'],
        ['name' => 'journals.reverse', 'display_name' => 'Reverse Jurnal', 'group' => 'journals', 'description' => 'Membalik jurnal'],
    ];

    public function up(): void
    {
        foreach ($this->newPermissions as $permissionData) {
            Permission::firstOrCreate(
                ['name' => $permissionData['name']],
                $permissionData
            );
        }
    }

    public function down(): void
    {
        $names = array_column($this->newPermissions, 'name');
        Permission::whereIn('name', $names)->delete();
    }
};
