<?php

namespace Database\Seeders\Demo;

use App\Models\Accounting\ProductCategory;
use App\Models\Accounting\Role;
use App\Models\Accounting\Warehouse;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MasterDataSeeder extends Seeder
{
    /**
     * Seed shared master data: warehouses, product categories, users.
     */
    public function run(): void
    {
        $this->createWarehouses();
        $this->createProductCategories();
        $this->createUsers();
    }

    private function createWarehouses(): void
    {
        $warehouses = [
            [
                'code' => 'WH-001',
                'name' => 'Gudang Utama',
                'address' => 'Jl. Industri Raya No. 100, Kawasan MM2100',
                'phone' => '021-89983456',
                'contact_person' => 'Pak Bambang',
                'is_default' => true,
                'is_active' => true,
                'notes' => 'Gudang utama penyimpanan bahan baku dan finished goods',
            ],
            [
                'code' => 'WH-002',
                'name' => 'Gudang Produksi',
                'address' => 'Jl. Industri Raya No. 100, Kawasan MM2100',
                'phone' => '021-89983457',
                'contact_person' => 'Pak Dedi',
                'is_default' => false,
                'is_active' => true,
                'notes' => 'Gudang WIP (Work in Progress) area produksi',
            ],
            [
                'code' => 'WH-003',
                'name' => 'Gudang Finished Goods',
                'address' => 'Jl. Industri Raya No. 102, Kawasan MM2100',
                'phone' => '021-89983458',
                'contact_person' => 'Bu Siti',
                'is_default' => false,
                'is_active' => true,
                'notes' => 'Gudang barang jadi siap kirim',
            ],
        ];

        foreach ($warehouses as $warehouse) {
            Warehouse::updateOrCreate(
                ['code' => $warehouse['code']],
                $warehouse
            );
        }

        $this->command->info('Created 3 warehouses');
    }

    private function createProductCategories(): void
    {
        // Parent categories
        $categories = [
            // Raw Materials - Electrical (PT Vahana)
            [
                'code' => 'RM',
                'name' => 'Bahan Baku',
                'description' => 'Raw materials untuk produksi',
                'children' => [
                    ['code' => 'RM-EL', 'name' => 'Komponen Elektrikal', 'description' => 'MCB, MCCB, Kontaktor, dll'],
                    ['code' => 'RM-CB', 'name' => 'Kabel & Wiring', 'description' => 'Kabel power, control, grounding'],
                    ['code' => 'RM-BB', 'name' => 'Busbar & Koneksi', 'description' => 'Busbar copper, terminal, lug'],
                    ['code' => 'RM-EN', 'name' => 'Enclosure & Box', 'description' => 'Panel box, junction box'],
                    ['code' => 'RM-AC', 'name' => 'Aksesoris', 'description' => 'DIN rail, duct, label, dll'],
                    // Solar Components (PT NEX)
                    ['code' => 'RM-PV', 'name' => 'Modul Surya', 'description' => 'Panel surya / PV modules'],
                    ['code' => 'RM-INV', 'name' => 'Inverter', 'description' => 'String inverter, central inverter, hybrid'],
                    ['code' => 'RM-MNT', 'name' => 'Mounting Structure', 'description' => 'Roof mount, ground mount, rail, clamp'],
                    ['code' => 'RM-DC', 'name' => 'DC Components', 'description' => 'DC cable, MC4 connector, combiner box'],
                    ['code' => 'RM-MON', 'name' => 'Monitoring & Meter', 'description' => 'Smart meter, monitoring system, CT'],
                ],
            ],
            // Finished Goods - Electrical Panels (PT Vahana)
            [
                'code' => 'FG',
                'name' => 'Barang Jadi',
                'description' => 'Finished goods / produk jadi',
                'children' => [
                    ['code' => 'FG-LV', 'name' => 'Panel LVMDP', 'description' => 'Low Voltage Main Distribution Panel'],
                    ['code' => 'FG-MCC', 'name' => 'Panel MCC', 'description' => 'Motor Control Center'],
                    ['code' => 'FG-CAP', 'name' => 'Panel Kapasitor', 'description' => 'Capacitor Bank Panel'],
                    ['code' => 'FG-ATS', 'name' => 'Panel ATS/AMF', 'description' => 'Automatic Transfer Switch'],
                    ['code' => 'FG-DB', 'name' => 'Panel DB', 'description' => 'Distribution Board'],
                    ['code' => 'FG-CTM', 'name' => 'Panel Custom', 'description' => 'Custom built panels'],
                    // Solar Systems (PT NEX)
                    ['code' => 'FG-PLTS', 'name' => 'Sistem PLTS', 'description' => 'Complete PV system / PLTS rooftop & ground'],
                    ['code' => 'FG-HYBRID', 'name' => 'Hybrid System', 'description' => 'Solar + battery storage system'],
                ],
            ],
            // Services
            [
                'code' => 'SVC',
                'name' => 'Jasa',
                'description' => 'Services / jasa',
                'children' => [
                    // Electrical Services (PT Vahana)
                    ['code' => 'SVC-INS', 'name' => 'Jasa Instalasi', 'description' => 'Instalasi panel dan wiring'],
                    ['code' => 'SVC-COM', 'name' => 'Jasa Commissioning', 'description' => 'Testing dan commissioning'],
                    ['code' => 'SVC-MNT', 'name' => 'Jasa Maintenance', 'description' => 'Perawatan dan perbaikan'],
                    // Solar Services (PT NEX)
                    ['code' => 'SVC-SRV', 'name' => 'Survey & Design', 'description' => 'Site survey, system design, proposal'],
                    ['code' => 'SVC-OM', 'name' => 'O&M Services', 'description' => 'Operations & maintenance PLTS'],
                    ['code' => 'SVC-CLN', 'name' => 'Panel Cleaning', 'description' => 'Solar panel cleaning service'],
                ],
            ],
        ];

        foreach ($categories as $parent) {
            $children = $parent['children'] ?? [];
            unset($parent['children']);

            $parentModel = ProductCategory::updateOrCreate(
                ['code' => $parent['code']],
                array_merge($parent, ['is_active' => true, 'sort_order' => 0])
            );

            foreach ($children as $index => $child) {
                ProductCategory::updateOrCreate(
                    ['code' => $child['code']],
                    array_merge($child, [
                        'parent_id' => $parentModel->id,
                        'is_active' => true,
                        'sort_order' => $index + 1,
                    ])
                );
            }
        }

        $this->command->info('Created product categories (3 parents + 22 children: 10 RM, 8 FG, 6 SVC)');
    }

    private function createUsers(): void
    {
        $users = [
            [
                'name' => 'Admin Demo',
                'email' => 'admin@demo.com',
                'password' => Hash::make('password'),
                'is_active' => true,
                'role' => Role::ADMIN,
            ],
            [
                'name' => 'Sales Manager',
                'email' => 'sales@demo.com',
                'password' => Hash::make('password'),
                'is_active' => true,
                'role' => Role::SALES,
            ],
            [
                'name' => 'Purchasing Staff',
                'email' => 'purchasing@demo.com',
                'password' => Hash::make('password'),
                'is_active' => true,
                'role' => Role::PURCHASING,
            ],
            [
                'name' => 'Production Manager',
                'email' => 'produksi@demo.com',
                'password' => Hash::make('password'),
                'is_active' => true,
                'role' => Role::INVENTORY,
            ],
            [
                'name' => 'Finance Staff',
                'email' => 'finance@demo.com',
                'password' => Hash::make('password'),
                'is_active' => true,
                'role' => Role::ACCOUNTANT,
            ],
            [
                'name' => 'Warehouse Staff',
                'email' => 'gudang@demo.com',
                'password' => Hash::make('password'),
                'is_active' => true,
                'role' => Role::INVENTORY,
            ],
        ];

        foreach ($users as $userData) {
            $role = $userData['role'];
            unset($userData['role']);

            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );

            $user->assignRole($role);
        }

        $this->command->info('Created 6 demo users with roles');
    }
}
