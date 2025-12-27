<?php

namespace Database\Seeders\Demo;

use App\Models\Accounting\ComponentBrandMapping;
use App\Models\Accounting\ComponentStandard;
use App\Models\Accounting\Product;
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
        $this->seedComponentStandards();
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

    private function seedComponentStandards(): void
    {
        // Get first admin user for created_by
        $adminUser = User::where('email', 'admin@demo.com')->first();

        if (! $adminUser) {
            $this->command->warn('Admin user not found, skipping component standards seeding');

            return;
        }

        $standards = [
            // MCB 16A 1P C-curve
            [
                'code' => 'IEC-MCB-1P-16A-C',
                'name' => 'MCB 1-Pole 16A C-Curve',
                'category' => ComponentStandard::CATEGORY_CIRCUIT_BREAKER,
                'subcategory' => ComponentStandard::SUBCATEGORY_MCB,
                'standard' => 'IEC 60898-1',
                'specifications' => [
                    'rating_amps' => 16,
                    'poles' => 1,
                    'curve' => 'C',
                    'breaking_capacity_ka' => 6,
                    'voltage' => '230V AC',
                ],
                'unit' => 'pcs',
                'description' => 'Standard MCB for lighting and power circuits',
                'is_active' => true,
                'created_by' => $adminUser->id,
                'brand_mappings' => [
                    [
                        'brand' => ComponentBrandMapping::BRAND_SCHNEIDER,
                        'brand_sku' => 'A9F74116',
                        'is_preferred' => true,
                        'is_verified' => true,
                        'price_factor' => 1.0,
                        'notes' => 'Acti 9 iC60N series',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_ABB,
                        'brand_sku' => 'S201-C16',
                        'is_preferred' => false,
                        'is_verified' => true,
                        'price_factor' => 0.95,
                        'notes' => 'System pro M compact series',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_SIEMENS,
                        'brand_sku' => '5SL6116-7',
                        'is_preferred' => false,
                        'is_verified' => true,
                        'price_factor' => 1.05,
                        'notes' => '5SL6 series',
                    ],
                ],
            ],

            // MCB 32A 3P C-curve
            [
                'code' => 'IEC-MCB-3P-32A-C',
                'name' => 'MCB 3-Pole 32A C-Curve',
                'category' => ComponentStandard::CATEGORY_CIRCUIT_BREAKER,
                'subcategory' => ComponentStandard::SUBCATEGORY_MCB,
                'standard' => 'IEC 60898-1',
                'specifications' => [
                    'rating_amps' => 32,
                    'poles' => 3,
                    'curve' => 'C',
                    'breaking_capacity_ka' => 6,
                    'voltage' => '400V AC',
                ],
                'unit' => 'pcs',
                'description' => 'MCB for 3-phase motor and power distribution',
                'is_active' => true,
                'created_by' => $adminUser->id,
                'brand_mappings' => [
                    [
                        'brand' => ComponentBrandMapping::BRAND_SCHNEIDER,
                        'brand_sku' => 'A9F84332',
                        'is_preferred' => true,
                        'is_verified' => true,
                        'price_factor' => 1.0,
                        'notes' => 'Acti 9 iC60N 3P',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_ABB,
                        'brand_sku' => 'S203-C32',
                        'is_preferred' => false,
                        'is_verified' => true,
                        'price_factor' => 0.92,
                        'notes' => 'System pro M compact 3P',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_CHINT,
                        'brand_sku' => 'NB1-63C32-3',
                        'is_preferred' => false,
                        'is_verified' => true,
                        'price_factor' => 0.65,
                        'notes' => 'NB1 series budget option',
                    ],
                ],
            ],

            // MCCB 100A 3P
            [
                'code' => 'IEC-MCCB-3P-100A',
                'name' => 'MCCB 3-Pole 100A',
                'category' => ComponentStandard::CATEGORY_CIRCUIT_BREAKER,
                'subcategory' => ComponentStandard::SUBCATEGORY_MCCB,
                'standard' => 'IEC 60947-2',
                'specifications' => [
                    'rating_amps' => 100,
                    'poles' => 3,
                    'breaking_capacity_ka' => 36,
                    'voltage' => '415V AC',
                    'frame_size' => 100,
                ],
                'unit' => 'pcs',
                'description' => 'MCCB for main distribution and feeder protection',
                'is_active' => true,
                'created_by' => $adminUser->id,
                'brand_mappings' => [
                    [
                        'brand' => ComponentBrandMapping::BRAND_SCHNEIDER,
                        'brand_sku' => 'NSX100F-TM100D-3P3D',
                        'is_preferred' => true,
                        'is_verified' => true,
                        'price_factor' => 1.0,
                        'notes' => 'NSX100F with TM-D trip unit',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_ABB,
                        'brand_sku' => 'T2N160-TMD100-1000-3P',
                        'is_preferred' => false,
                        'is_verified' => true,
                        'price_factor' => 1.08,
                        'notes' => 'Tmax T2 series',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_SIEMENS,
                        'brand_sku' => '3VT1710-2DA36-0AA0',
                        'is_preferred' => false,
                        'is_verified' => true,
                        'price_factor' => 1.15,
                        'notes' => 'Sentron 3VT1 series',
                    ],
                ],
            ],

            // Contactor 25A AC3
            [
                'code' => 'IEC-CONT-25A-AC3',
                'name' => 'Contactor 25A AC3 3-Pole',
                'category' => ComponentStandard::CATEGORY_CONTACTOR,
                'subcategory' => null,
                'standard' => 'IEC 60947-4-1',
                'specifications' => [
                    'rating_amps' => 25,
                    'poles' => 3,
                    'utilization_category' => 'AC3',
                    'coil_voltage' => '220V AC',
                    'aux_contacts' => '1NO+1NC',
                    'power_rating_kw' => 11,
                ],
                'unit' => 'pcs',
                'description' => 'Contactor for motor control up to 11kW',
                'is_active' => true,
                'created_by' => $adminUser->id,
                'brand_mappings' => [
                    [
                        'brand' => ComponentBrandMapping::BRAND_SCHNEIDER,
                        'brand_sku' => 'LC1D25M7',
                        'is_preferred' => true,
                        'is_verified' => true,
                        'price_factor' => 1.0,
                        'notes' => 'TeSys D series with 220V AC coil',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_ABB,
                        'brand_sku' => 'A26-30-10-220',
                        'is_preferred' => false,
                        'is_verified' => true,
                        'price_factor' => 0.88,
                        'notes' => 'A series contactor',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_CHINT,
                        'brand_sku' => 'NC1-2510-M7',
                        'is_preferred' => false,
                        'is_verified' => true,
                        'price_factor' => 0.55,
                        'notes' => 'NC1 series budget option',
                    ],
                ],
            ],

            // Cable 2.5mm² 3-core
            [
                'code' => 'IEC-CBL-NYM-2.5-3C',
                'name' => 'Cable NYM 3x2.5mm²',
                'category' => ComponentStandard::CATEGORY_CABLE,
                'subcategory' => null,
                'standard' => 'IEC 60227',
                'specifications' => [
                    'conductor_size_mm2' => 2.5,
                    'cores' => 3,
                    'type' => 'NYM',
                    'voltage_rating' => '300/500V',
                    'conductor_material' => 'Copper',
                    'current_capacity_amps' => 24,
                ],
                'unit' => 'meter',
                'description' => 'PVC insulated cable for installation',
                'is_active' => true,
                'created_by' => $adminUser->id,
                'brand_mappings' => [
                    [
                        'brand' => ComponentBrandMapping::BRAND_SCHNEIDER,
                        'brand_sku' => 'NYM-3X2.5-SCH',
                        'is_preferred' => false,
                        'is_verified' => true,
                        'price_factor' => 1.2,
                        'notes' => 'Premium grade',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_CHINT,
                        'brand_sku' => 'NYM-3X2.5-CHT',
                        'is_preferred' => true,
                        'is_verified' => true,
                        'price_factor' => 1.0,
                        'notes' => 'Standard installation cable',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_LS,
                        'brand_sku' => 'NYM-3X2.5-LS',
                        'is_preferred' => false,
                        'is_verified' => true,
                        'price_factor' => 0.95,
                        'notes' => 'Good quality alternative',
                    ],
                ],
            ],

            // Busbar 200A copper
            [
                'code' => 'IEC-BB-CU-200A',
                'name' => 'Busbar Copper 200A',
                'category' => ComponentStandard::CATEGORY_BUSBAR,
                'subcategory' => null,
                'standard' => 'IEC 61439',
                'specifications' => [
                    'rating_amps' => 200,
                    'material' => 'Copper',
                    'dimension_mm' => '25x5',
                    'conductivity' => '100% IACS',
                    'plating' => 'Tin-plated',
                ],
                'unit' => 'meter',
                'description' => 'Copper busbar for panel distribution',
                'is_active' => true,
                'created_by' => $adminUser->id,
                'brand_mappings' => [
                    [
                        'brand' => ComponentBrandMapping::BRAND_SCHNEIDER,
                        'brand_sku' => 'BB-CU-25X5-200A-SCH',
                        'is_preferred' => true,
                        'is_verified' => true,
                        'price_factor' => 1.0,
                        'notes' => 'Prisma series busbar',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_ABB,
                        'brand_sku' => 'BB-CU-25X5-200A-ABB',
                        'is_preferred' => false,
                        'is_verified' => true,
                        'price_factor' => 1.05,
                        'notes' => 'ArTu series busbar',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_LEGRAND,
                        'brand_sku' => 'BB-CU-25X5-200A-LEG',
                        'is_preferred' => false,
                        'is_verified' => true,
                        'price_factor' => 0.92,
                        'notes' => 'XL³ series busbar',
                    ],
                ],
            ],
        ];

        $createdCount = 0;
        foreach ($standards as $standardData) {
            $brandMappings = $standardData['brand_mappings'];
            unset($standardData['brand_mappings']);

            // Create or update the component standard
            $standard = ComponentStandard::updateOrCreate(
                ['code' => $standardData['code']],
                $standardData
            );

            // Create brand mappings
            foreach ($brandMappings as $mappingData) {
                // Try to find existing product by exact SKU first
                $product = Product::where('sku', $mappingData['brand_sku'])->first();

                // If not found, create a minimal product for this brand mapping
                if (! $product) {
                    // Get the appropriate category based on component category
                    $categoryCode = match ($standardData['category']) {
                        ComponentStandard::CATEGORY_CIRCUIT_BREAKER => 'RM-EL',
                        ComponentStandard::CATEGORY_CONTACTOR => 'RM-EL',
                        ComponentStandard::CATEGORY_CABLE => 'RM-CB',
                        ComponentStandard::CATEGORY_BUSBAR => 'RM-BB',
                        default => 'RM-EL',
                    };

                    $category = ProductCategory::where('code', $categoryCode)->first();

                    $product = Product::create([
                        'sku' => $mappingData['brand_sku'],
                        'name' => $standardData['name'].' - '.strtoupper($mappingData['brand']),
                        'description' => $mappingData['notes'] ?? 'Component for '.$standardData['name'],
                        'type' => Product::TYPE_PRODUCT,
                        'category_id' => $category?->id,
                        'unit' => $standardData['unit'],
                        'purchase_price' => 0,
                        'selling_price' => 0,
                        'is_taxable' => true,
                        'tax_rate' => 11.00,
                        'track_inventory' => true,
                        'min_stock' => 0,
                        'current_stock' => 0,
                        'procurement_type' => Product::PROCUREMENT_BUY,
                        'is_active' => true,
                    ]);
                }

                ComponentBrandMapping::updateOrCreate(
                    [
                        'component_standard_id' => $standard->id,
                        'brand' => $mappingData['brand'],
                        'brand_sku' => $mappingData['brand_sku'],
                    ],
                    array_merge($mappingData, [
                        'component_standard_id' => $standard->id,
                        'product_id' => $product->id,
                        'verified_by' => $adminUser->id,
                        'verified_at' => now(),
                    ])
                );
            }

            $createdCount++;
        }

        $this->command->info("Created {$createdCount} component standards with brand mappings");
    }
}
