<?php

namespace Database\Seeders;

use App\Models\Accounting\Permission;
use App\Models\Accounting\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Create all permissions
        foreach (Permission::getDefaultPermissions() as $permissionData) {
            Permission::firstOrCreate(
                ['name' => $permissionData['name']],
                $permissionData
            );
        }

        // Create system roles
        $adminRole = Role::firstOrCreate(
            ['name' => Role::ADMIN],
            [
                'display_name' => 'Administrator',
                'description' => 'Akses penuh ke semua fitur sistem',
                'is_system' => true,
            ]
        );

        $accountantRole = Role::firstOrCreate(
            ['name' => Role::ACCOUNTANT],
            [
                'display_name' => 'Akuntan',
                'description' => 'Akses ke fitur akuntansi dan laporan',
                'is_system' => true,
            ]
        );

        $cashierRole = Role::firstOrCreate(
            ['name' => Role::CASHIER],
            [
                'display_name' => 'Kasir',
                'description' => 'Akses ke faktur dan pembayaran',
                'is_system' => true,
            ]
        );

        $salesRole = Role::firstOrCreate(
            ['name' => Role::SALES],
            [
                'display_name' => 'Sales',
                'description' => 'Akses ke penjualan, quotation, dan faktur',
                'is_system' => true,
            ]
        );

        $purchasingRole = Role::firstOrCreate(
            ['name' => Role::PURCHASING],
            [
                'display_name' => 'Purchasing',
                'description' => 'Akses ke pembelian, PO, dan tagihan vendor',
                'is_system' => true,
            ]
        );

        $inventoryRole = Role::firstOrCreate(
            ['name' => Role::INVENTORY],
            [
                'display_name' => 'Inventori',
                'description' => 'Akses ke manajemen inventori dan gudang',
                'is_system' => true,
            ]
        );

        $viewerRole = Role::firstOrCreate(
            ['name' => Role::VIEWER],
            [
                'display_name' => 'Viewer',
                'description' => 'Hanya dapat melihat data',
                'is_system' => true,
            ]
        );

        // Assign permissions to Accountant role
        $accountantPermissions = Permission::whereIn('name', [
            'accounts.view', 'accounts.create', 'accounts.edit',
            'contacts.view', 'contacts.create', 'contacts.edit',
            'products.view',
            'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.post',
            'bills.view', 'bills.create', 'bills.edit', 'bills.post',
            'payments.view', 'payments.create',
            'journals.view', 'journals.create', 'journals.post', 'journals.reverse',
            'budgets.view', 'budgets.create', 'budgets.edit',
            'reports.financial', 'reports.tax', 'reports.aging', 'reports.export',
            'settings.fiscal_periods',
        ])->pluck('id');
        $accountantRole->permissions()->sync($accountantPermissions);

        // Assign permissions to Cashier role
        $cashierPermissions = Permission::whereIn('name', [
            'contacts.view',
            'products.view',
            'invoices.view', 'invoices.create',
            'bills.view',
            'payments.view', 'payments.create',
        ])->pluck('id');
        $cashierRole->permissions()->sync($cashierPermissions);

        // Assign permissions to Sales role
        $salesPermissions = Permission::whereIn('name', [
            'contacts.view', 'contacts.create', 'contacts.edit',
            'products.view',
            'invoices.view', 'invoices.create', 'invoices.edit',
            'payments.view',
            'inventory.view',
            'reports.aging',
        ])->pluck('id');
        $salesRole->permissions()->sync($salesPermissions);

        // Assign permissions to Purchasing role
        $purchasingPermissions = Permission::whereIn('name', [
            'contacts.view', 'contacts.create', 'contacts.edit',
            'products.view',
            'bills.view', 'bills.create', 'bills.edit',
            'payments.view',
            'inventory.view',
            'reports.aging',
        ])->pluck('id');
        $purchasingRole->permissions()->sync($purchasingPermissions);

        // Assign permissions to Inventory role
        $inventoryPermissions = Permission::whereIn('name', [
            'products.view', 'products.create', 'products.edit',
            'inventory.view', 'inventory.stock_in', 'inventory.stock_out', 'inventory.adjust', 'inventory.transfer',
            'settings.warehouses',
        ])->pluck('id');
        $inventoryRole->permissions()->sync($inventoryPermissions);

        // Assign permissions to Viewer role
        $viewerPermissions = Permission::whereIn('name', [
            'accounts.view',
            'contacts.view',
            'products.view',
            'invoices.view',
            'bills.view',
            'payments.view',
            'journals.view',
            'inventory.view',
            'budgets.view',
            'reports.financial', 'reports.aging',
        ])->pluck('id');
        $viewerRole->permissions()->sync($viewerPermissions);

        $this->command->info('Roles and permissions seeded successfully!');
        $this->command->info('Created '.Permission::count().' permissions');
        $this->command->info('Created '.Role::count().' roles');
    }
}
