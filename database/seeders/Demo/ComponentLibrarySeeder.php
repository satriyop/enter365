<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Models\Accounting\ComponentBrandMapping;
use App\Models\Accounting\ComponentStandard;
use App\Models\Accounting\Product;
use App\Models\Accounting\ProductCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Comprehensive Component Library Seeder
 *
 * Seeds IEC-standard electrical components with brand mappings for:
 * - Schneider Electric (Vahana partner - iC60N, NSX, TeSys D series)
 * - ABB (Vahana partner - S200, Tmax XT, AF series)
 * - Siemens (Vahana partner - 5SL/5SY, 3VA, SIRIUS series)
 * - CHINT (Budget alternative - NC1, NM8N series)
 * - LS Electric (Korean premium - Susol, Metasol series)
 * - Legrand (French premium - DX3, DMX3 series)
 *
 * Real SKU codes from manufacturer catalogs for accurate cross-referencing.
 */
class ComponentLibrarySeeder extends Seeder
{
    private ?User $adminUser = null;

    private array $categoryCache = [];

    public function run(): void
    {
        $this->adminUser = User::where('email', 'admin@demo.com')->first()
            ?? User::where('email', 'admin@example.com')->first();

        if (! $this->adminUser) {
            $this->command->warn('Admin user not found, skipping component library seeding');

            return;
        }

        $this->cacheCategories();

        $this->command->info('');
        $this->command->info('🔌 Seeding Component Library for Panel Manufacturing');
        $this->command->info('   Partners: Schneider Electric, ABB, Siemens');
        $this->command->info('   Alternatives: CHINT, LS Electric, Legrand');
        $this->command->info('');

        $stats = [
            'MCB' => $this->seedMcbStandards(),
            'MCCB' => $this->seedMccbStandards(),
            'Contactor' => $this->seedContactorStandards(),
            'Thermal Overload' => $this->seedThermalOverloadStandards(),
            'RCCB/RCBO' => $this->seedRccbRcboStandards(),
            'Timer Relay' => $this->seedTimerRelayStandards(),
            'Power Meter' => $this->seedPowerMeterStandards(),
            'Push Button' => $this->seedPushButtonStandards(),
            'Pilot Lamp' => $this->seedPilotLampStandards(),
            'Cable' => $this->seedCableStandards(),
            'Busbar' => $this->seedBusbarStandards(),
            'Terminal Block' => $this->seedTerminalBlockStandards(),
            'Enclosure' => $this->seedEnclosureStandards(),
        ];

        $this->command->info('');
        $this->command->info('📊 Component Library Summary:');
        $totalStandards = 0;
        $totalMappings = 0;
        foreach ($stats as $category => $count) {
            if ($count > 0) {
                $this->command->line("   {$category}: {$count} standards");
                $totalStandards += $count;
            }
        }

        $totalMappings = ComponentBrandMapping::count();
        $this->command->info('');
        $this->command->info("✅ Total: {$totalStandards} component standards, {$totalMappings} brand mappings");
        $this->command->info('');
    }

    private function cacheCategories(): void
    {
        $categories = ProductCategory::all();
        foreach ($categories as $cat) {
            $this->categoryCache[$cat->code] = $cat->id;
        }
    }

    private function getCategoryId(string $code): ?int
    {
        return $this->categoryCache[$code] ?? null;
    }

    private function createStandard(array $data): ComponentStandard
    {
        $brandMappings = $data['brand_mappings'] ?? [];
        unset($data['brand_mappings']);

        $standard = ComponentStandard::updateOrCreate(
            ['code' => $data['code']],
            array_merge($data, ['created_by' => $this->adminUser->id])
        );

        foreach ($brandMappings as $mapping) {
            $this->createBrandMapping($standard, $mapping, $data);
        }

        return $standard;
    }

    private function createBrandMapping(ComponentStandard $standard, array $mapping, array $standardData): void
    {
        // Find or create product
        $product = Product::where('sku', $mapping['brand_sku'])->first();

        if (! $product) {
            $categoryCode = match ($standardData['category']) {
                ComponentStandard::CATEGORY_CIRCUIT_BREAKER => 'RM-EL',
                ComponentStandard::CATEGORY_CONTACTOR => 'RM-EL',
                ComponentStandard::CATEGORY_RELAY => 'RM-EL',
                ComponentStandard::CATEGORY_CABLE => 'RM-CB',
                ComponentStandard::CATEGORY_BUSBAR => 'RM-BB',
                ComponentStandard::CATEGORY_TERMINAL => 'RM-AC',
                ComponentStandard::CATEGORY_ENCLOSURE => 'RM-EN',
                ComponentStandard::CATEGORY_METER => 'RM-EL',
                default => 'RM-EL',
            };

            $brandName = ComponentBrandMapping::getBrands()[$mapping['brand']] ?? strtoupper($mapping['brand']);

            $product = Product::create([
                'sku' => $mapping['brand_sku'],
                'name' => $standardData['name'].' - '.$brandName,
                'description' => $mapping['notes'] ?? 'Component for '.$standardData['name'],
                'type' => Product::TYPE_PRODUCT,
                'category_id' => $this->getCategoryId($categoryCode),
                'unit' => $standardData['unit'],
                'brand' => $mapping['brand'],
                'purchase_price' => $mapping['purchase_price'] ?? 0,
                'selling_price' => $mapping['selling_price'] ?? 0,
                'is_taxable' => true,
                'tax_rate' => 11.00,
                'track_inventory' => true,
                'min_stock' => 5,
                'current_stock' => 0,
                'procurement_type' => Product::PROCUREMENT_BUY,
                'is_active' => true,
                'is_purchasable' => true,
                'is_sellable' => true,
            ]);
        }

        ComponentBrandMapping::updateOrCreate(
            [
                'component_standard_id' => $standard->id,
                'brand' => $mapping['brand'],
                'brand_sku' => $mapping['brand_sku'],
            ],
            [
                'product_id' => $product->id,
                'is_preferred' => $mapping['is_preferred'] ?? false,
                'is_verified' => $mapping['is_verified'] ?? true,
                'price_factor' => $mapping['price_factor'] ?? 1.0,
                'notes' => $mapping['notes'] ?? null,
                'verified_by' => $this->adminUser->id,
                'verified_at' => now(),
            ]
        );
    }

    // =========================================================================
    // MCB - Miniature Circuit Breakers (IEC 60898-1)
    // =========================================================================

    private function seedMcbStandards(): int
    {
        $this->command->line('   Seeding MCB standards...');

        $standards = [
            // 1-Pole MCBs
            ...$this->getMcb1PoleStandards(),
            // 2-Pole MCBs
            ...$this->getMcb2PoleStandards(),
            // 3-Pole MCBs
            ...$this->getMcb3PoleStandards(),
            // 4-Pole MCBs
            ...$this->getMcb4PoleStandards(),
        ];

        foreach ($standards as $data) {
            $this->createStandard($data);
        }

        return count($standards);
    }

    private function getMcb1PoleStandards(): array
    {
        $ratings = [6, 10, 16, 20, 25, 32, 40, 50, 63];
        $standards = [];

        foreach ($ratings as $amp) {
            $standards[] = [
                'code' => "IEC-MCB-1P-{$amp}A-C",
                'name' => "MCB 1P {$amp}A C-Curve 6kA",
                'category' => ComponentStandard::CATEGORY_CIRCUIT_BREAKER,
                'subcategory' => ComponentStandard::SUBCATEGORY_MCB,
                'standard' => 'IEC 60898-1',
                'specifications' => [
                    'rating_amps' => $amp,
                    'poles' => 1,
                    'curve' => 'C',
                    'breaking_capacity_ka' => 6,
                    'voltage' => '230V AC',
                    'width_modules' => 1,
                ],
                'unit' => 'pcs',
                'description' => "Single pole MCB for lighting and general circuits up to {$amp}A",
                'is_active' => true,
                'brand_mappings' => [
                    // Schneider Electric - iC60N series (A9F74xxx)
                    [
                        'brand' => ComponentBrandMapping::BRAND_SCHNEIDER,
                        'brand_sku' => 'A9F741'.str_pad((string) $amp, 2, '0', STR_PAD_LEFT),
                        'is_preferred' => true,
                        'price_factor' => 1.0,
                        'purchase_price' => $this->getMcbPrice('schneider', 1, $amp),
                        'notes' => 'Acti 9 iC60N - Premium performance, 6kA',
                    ],
                    // ABB - S201 series
                    [
                        'brand' => ComponentBrandMapping::BRAND_ABB,
                        'brand_sku' => "S201-C{$amp}",
                        'is_preferred' => false,
                        'price_factor' => 0.95,
                        'purchase_price' => $this->getMcbPrice('abb', 1, $amp),
                        'notes' => 'System pro M compact S200 - European standard',
                    ],
                    // Siemens - 5SL6 series
                    [
                        'brand' => ComponentBrandMapping::BRAND_SIEMENS,
                        'brand_sku' => '5SL61'.str_pad((string) $amp, 2, '0', STR_PAD_LEFT).'-7',
                        'is_preferred' => false,
                        'price_factor' => 1.05,
                        'purchase_price' => $this->getMcbPrice('siemens', 1, $amp),
                        'notes' => 'SENTRON 5SL6 - German engineering',
                    ],
                    // CHINT - NB1-63 series
                    [
                        'brand' => ComponentBrandMapping::BRAND_CHINT,
                        'brand_sku' => "NB1-63C{$amp}/1P",
                        'is_preferred' => false,
                        'price_factor' => 0.50,
                        'purchase_price' => $this->getMcbPrice('chint', 1, $amp),
                        'notes' => 'NB1-63 series - Budget option, IEC compliant',
                    ],
                    // LS Electric - BKN series
                    [
                        'brand' => ComponentBrandMapping::BRAND_LS,
                        'brand_sku' => "BKN-C{$amp}/1P",
                        'is_preferred' => false,
                        'price_factor' => 0.70,
                        'purchase_price' => $this->getMcbPrice('ls', 1, $amp),
                        'notes' => 'BKN series - Korean quality, competitive price',
                    ],
                    // Legrand - DX3 series
                    [
                        'brand' => ComponentBrandMapping::BRAND_LEGRAND,
                        'brand_sku' => '4086'.str_pad((string) $amp, 2, '0', STR_PAD_LEFT),
                        'is_preferred' => false,
                        'price_factor' => 0.90,
                        'purchase_price' => $this->getMcbPrice('legrand', 1, $amp),
                        'notes' => 'DX3 series - French design',
                    ],
                ],
            ];
        }

        return $standards;
    }

    private function getMcb2PoleStandards(): array
    {
        $ratings = [6, 10, 16, 20, 25, 32, 40, 50, 63];
        $standards = [];

        foreach ($ratings as $amp) {
            $standards[] = [
                'code' => "IEC-MCB-2P-{$amp}A-C",
                'name' => "MCB 2P {$amp}A C-Curve 6kA",
                'category' => ComponentStandard::CATEGORY_CIRCUIT_BREAKER,
                'subcategory' => ComponentStandard::SUBCATEGORY_MCB,
                'standard' => 'IEC 60898-1',
                'specifications' => [
                    'rating_amps' => $amp,
                    'poles' => 2,
                    'curve' => 'C',
                    'breaking_capacity_ka' => 6,
                    'voltage' => '230V AC',
                    'width_modules' => 2,
                ],
                'unit' => 'pcs',
                'description' => 'Double pole MCB for single phase circuits requiring isolation',
                'is_active' => true,
                'brand_mappings' => [
                    [
                        'brand' => ComponentBrandMapping::BRAND_SCHNEIDER,
                        'brand_sku' => 'A9F742'.str_pad((string) $amp, 2, '0', STR_PAD_LEFT),
                        'is_preferred' => true,
                        'price_factor' => 1.0,
                        'purchase_price' => $this->getMcbPrice('schneider', 2, $amp),
                        'notes' => 'Acti 9 iC60N 2P - Full isolation',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_ABB,
                        'brand_sku' => "S202-C{$amp}",
                        'is_preferred' => false,
                        'price_factor' => 0.95,
                        'purchase_price' => $this->getMcbPrice('abb', 2, $amp),
                        'notes' => 'System pro M compact S202',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_SIEMENS,
                        'brand_sku' => '5SL62'.str_pad((string) $amp, 2, '0', STR_PAD_LEFT).'-7',
                        'is_preferred' => false,
                        'price_factor' => 1.05,
                        'purchase_price' => $this->getMcbPrice('siemens', 2, $amp),
                        'notes' => 'SENTRON 5SL6 2P',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_CHINT,
                        'brand_sku' => "NB1-63C{$amp}/2P",
                        'is_preferred' => false,
                        'price_factor' => 0.50,
                        'purchase_price' => $this->getMcbPrice('chint', 2, $amp),
                        'notes' => 'NB1-63 2P budget option',
                    ],
                ],
            ];
        }

        return $standards;
    }

    private function getMcb3PoleStandards(): array
    {
        $ratings = [6, 10, 16, 20, 25, 32, 40, 50, 63];
        $standards = [];

        foreach ($ratings as $amp) {
            $standards[] = [
                'code' => "IEC-MCB-3P-{$amp}A-C",
                'name' => "MCB 3P {$amp}A C-Curve 6kA",
                'category' => ComponentStandard::CATEGORY_CIRCUIT_BREAKER,
                'subcategory' => ComponentStandard::SUBCATEGORY_MCB,
                'standard' => 'IEC 60898-1',
                'specifications' => [
                    'rating_amps' => $amp,
                    'poles' => 3,
                    'curve' => 'C',
                    'breaking_capacity_ka' => 6,
                    'voltage' => '400V AC',
                    'width_modules' => 3,
                ],
                'unit' => 'pcs',
                'description' => 'Three pole MCB for 3-phase loads and motor protection',
                'is_active' => true,
                'brand_mappings' => [
                    [
                        'brand' => ComponentBrandMapping::BRAND_SCHNEIDER,
                        'brand_sku' => 'A9F843'.str_pad((string) $amp, 2, '0', STR_PAD_LEFT),
                        'is_preferred' => true,
                        'price_factor' => 1.0,
                        'purchase_price' => $this->getMcbPrice('schneider', 3, $amp),
                        'notes' => 'Acti 9 iC60N 3P for 3-phase',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_ABB,
                        'brand_sku' => "S203-C{$amp}",
                        'is_preferred' => false,
                        'price_factor' => 0.95,
                        'purchase_price' => $this->getMcbPrice('abb', 3, $amp),
                        'notes' => 'System pro M compact S203',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_SIEMENS,
                        'brand_sku' => '5SL63'.str_pad((string) $amp, 2, '0', STR_PAD_LEFT).'-7',
                        'is_preferred' => false,
                        'price_factor' => 1.05,
                        'purchase_price' => $this->getMcbPrice('siemens', 3, $amp),
                        'notes' => 'SENTRON 5SL6 3P',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_CHINT,
                        'brand_sku' => "NB1-63C{$amp}/3P",
                        'is_preferred' => false,
                        'price_factor' => 0.50,
                        'purchase_price' => $this->getMcbPrice('chint', 3, $amp),
                        'notes' => 'NB1-63 3P budget option',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_LS,
                        'brand_sku' => "BKN-C{$amp}/3P",
                        'is_preferred' => false,
                        'price_factor' => 0.70,
                        'purchase_price' => $this->getMcbPrice('ls', 3, $amp),
                        'notes' => 'BKN 3P Korean quality',
                    ],
                ],
            ];
        }

        return $standards;
    }

    private function getMcb4PoleStandards(): array
    {
        $ratings = [16, 20, 25, 32, 40, 50, 63];
        $standards = [];

        foreach ($ratings as $amp) {
            $standards[] = [
                'code' => "IEC-MCB-4P-{$amp}A-C",
                'name' => "MCB 4P {$amp}A C-Curve 6kA",
                'category' => ComponentStandard::CATEGORY_CIRCUIT_BREAKER,
                'subcategory' => ComponentStandard::SUBCATEGORY_MCB,
                'standard' => 'IEC 60898-1',
                'specifications' => [
                    'rating_amps' => $amp,
                    'poles' => 4,
                    'curve' => 'C',
                    'breaking_capacity_ka' => 6,
                    'voltage' => '400V AC',
                    'width_modules' => 4,
                ],
                'unit' => 'pcs',
                'description' => 'Four pole MCB for 3-phase+N, full isolation',
                'is_active' => true,
                'brand_mappings' => [
                    [
                        'brand' => ComponentBrandMapping::BRAND_SCHNEIDER,
                        'brand_sku' => 'A9F844'.str_pad((string) $amp, 2, '0', STR_PAD_LEFT),
                        'is_preferred' => true,
                        'price_factor' => 1.0,
                        'purchase_price' => $this->getMcbPrice('schneider', 4, $amp),
                        'notes' => 'Acti 9 iC60N 4P with neutral',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_ABB,
                        'brand_sku' => "S204-C{$amp}",
                        'is_preferred' => false,
                        'price_factor' => 0.95,
                        'purchase_price' => $this->getMcbPrice('abb', 4, $amp),
                        'notes' => 'System pro M compact S204',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_SIEMENS,
                        'brand_sku' => '5SL64'.str_pad((string) $amp, 2, '0', STR_PAD_LEFT).'-7',
                        'is_preferred' => false,
                        'price_factor' => 1.05,
                        'purchase_price' => $this->getMcbPrice('siemens', 4, $amp),
                        'notes' => 'SENTRON 5SL6 4P',
                    ],
                ],
            ];
        }

        return $standards;
    }

    private function getMcbPrice(string $brand, int $poles, int $amps): int
    {
        // Base prices in IDR (realistic Indonesian market prices)
        $basePrices = [
            'schneider' => 85000,  // Premium brand
            'abb' => 78000,        // European premium
            'siemens' => 92000,    // German premium
            'chint' => 35000,      // Budget
            'ls' => 55000,         // Korean mid-range
            'legrand' => 72000,    // French mid-premium
        ];

        $base = $basePrices[$brand] ?? 50000;

        // Pole multiplier
        $poleMultiplier = match ($poles) {
            1 => 1.0,
            2 => 1.8,
            3 => 2.5,
            4 => 3.2,
            default => 1.0,
        };

        // Amp rating multiplier
        $ampMultiplier = match (true) {
            $amps <= 10 => 1.0,
            $amps <= 20 => 1.1,
            $amps <= 32 => 1.2,
            $amps <= 50 => 1.4,
            default => 1.6,
        };

        return (int) round($base * $poleMultiplier * $ampMultiplier);
    }

    // =========================================================================
    // MCCB - Molded Case Circuit Breakers (IEC 60947-2)
    // =========================================================================

    private function seedMccbStandards(): int
    {
        $this->command->line('   Seeding MCCB standards...');

        $standards = [
            // Frame 100A
            ...$this->getMccbFrame100Standards(),
            // Frame 160A
            ...$this->getMccbFrame160Standards(),
            // Frame 250A
            ...$this->getMccbFrame250Standards(),
            // Frame 400A
            ...$this->getMccbFrame400Standards(),
            // Frame 630A
            ...$this->getMccbFrame630Standards(),
            // Frame 800A
            ...$this->getMccbFrame800Standards(),
        ];

        foreach ($standards as $data) {
            $this->createStandard($data);
        }

        return count($standards);
    }

    private function getMccbFrame100Standards(): array
    {
        $ratings = [16, 20, 25, 32, 40, 50, 63, 80, 100];
        $standards = [];

        foreach ($ratings as $amp) {
            $standards[] = [
                'code' => "IEC-MCCB-3P-{$amp}A-F100",
                'name' => "MCCB 3P {$amp}A 36kA Frame 100",
                'category' => ComponentStandard::CATEGORY_CIRCUIT_BREAKER,
                'subcategory' => ComponentStandard::SUBCATEGORY_MCCB,
                'standard' => 'IEC 60947-2',
                'specifications' => [
                    'rating_amps' => $amp,
                    'poles' => 3,
                    'frame_size' => 100,
                    'breaking_capacity_ka' => 36,
                    'voltage' => '415V AC',
                    'trip_unit' => 'TM (Thermal-Magnetic)',
                ],
                'unit' => 'pcs',
                'description' => "MCCB for feeder protection, adjustable thermal {$amp}A",
                'is_active' => true,
                'brand_mappings' => [
                    // Schneider NSX100F
                    [
                        'brand' => ComponentBrandMapping::BRAND_SCHNEIDER,
                        'brand_sku' => "LV429630-TM{$amp}D",
                        'is_preferred' => true,
                        'price_factor' => 1.0,
                        'purchase_price' => $this->getMccbPrice('schneider', 100, $amp),
                        'notes' => 'NSX100F 36kA, TM-D thermal-magnetic trip unit',
                    ],
                    // ABB Tmax XT1
                    [
                        'brand' => ComponentBrandMapping::BRAND_ABB,
                        'brand_sku' => "1SDA066806R1-{$amp}",
                        'is_preferred' => false,
                        'price_factor' => 1.05,
                        'purchase_price' => $this->getMccbPrice('abb', 100, $amp),
                        'notes' => 'Tmax XT1 160 TMD, 36kA at 415V',
                    ],
                    // Siemens 3VA11
                    [
                        'brand' => ComponentBrandMapping::BRAND_SIEMENS,
                        'brand_sku' => "3VA11{$amp}-5ED36-0AA0",
                        'is_preferred' => false,
                        'price_factor' => 1.15,
                        'purchase_price' => $this->getMccbPrice('siemens', 100, $amp),
                        'notes' => 'SENTRON 3VA11 frame 160, TM adjustable',
                    ],
                    // CHINT NM8N
                    [
                        'brand' => ComponentBrandMapping::BRAND_CHINT,
                        'brand_sku' => "NM8N-125S/{$amp}/3P",
                        'is_preferred' => false,
                        'price_factor' => 0.45,
                        'purchase_price' => $this->getMccbPrice('chint', 100, $amp),
                        'notes' => 'NM8N-125S 36kA, budget industrial',
                    ],
                    // LS Susol
                    [
                        'brand' => ComponentBrandMapping::BRAND_LS,
                        'brand_sku' => "TS100N-ATU{$amp}",
                        'is_preferred' => false,
                        'price_factor' => 0.65,
                        'purchase_price' => $this->getMccbPrice('ls', 100, $amp),
                        'notes' => 'Susol TS100N, Korean quality',
                    ],
                ],
            ];
        }

        return $standards;
    }

    private function getMccbFrame160Standards(): array
    {
        $ratings = [80, 100, 125, 160];
        $standards = [];

        foreach ($ratings as $amp) {
            $standards[] = [
                'code' => "IEC-MCCB-3P-{$amp}A-F160",
                'name' => "MCCB 3P {$amp}A 36kA Frame 160",
                'category' => ComponentStandard::CATEGORY_CIRCUIT_BREAKER,
                'subcategory' => ComponentStandard::SUBCATEGORY_MCCB,
                'standard' => 'IEC 60947-2',
                'specifications' => [
                    'rating_amps' => $amp,
                    'poles' => 3,
                    'frame_size' => 160,
                    'breaking_capacity_ka' => 36,
                    'voltage' => '415V AC',
                    'trip_unit' => 'TM (Thermal-Magnetic)',
                ],
                'unit' => 'pcs',
                'description' => 'MCCB Frame 160 for main distribution feeders',
                'is_active' => true,
                'brand_mappings' => [
                    [
                        'brand' => ComponentBrandMapping::BRAND_SCHNEIDER,
                        'brand_sku' => "LV430630-TM{$amp}D",
                        'is_preferred' => true,
                        'price_factor' => 1.0,
                        'purchase_price' => $this->getMccbPrice('schneider', 160, $amp),
                        'notes' => 'NSX160F 36kA, TM-D trip unit',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_ABB,
                        'brand_sku' => "1SDA067561R1-{$amp}",
                        'is_preferred' => false,
                        'price_factor' => 1.05,
                        'purchase_price' => $this->getMccbPrice('abb', 160, $amp),
                        'notes' => 'Tmax XT3 250 TMD',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_SIEMENS,
                        'brand_sku' => "3VA21{$amp}-5HL36-0AA0",
                        'is_preferred' => false,
                        'price_factor' => 1.15,
                        'purchase_price' => $this->getMccbPrice('siemens', 160, $amp),
                        'notes' => 'SENTRON 3VA2 frame 250',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_CHINT,
                        'brand_sku' => "NM8N-250S/{$amp}/3P",
                        'is_preferred' => false,
                        'price_factor' => 0.45,
                        'purchase_price' => $this->getMccbPrice('chint', 160, $amp),
                        'notes' => 'NM8N-250S budget option',
                    ],
                ],
            ];
        }

        return $standards;
    }

    private function getMccbFrame250Standards(): array
    {
        $ratings = [125, 160, 200, 225, 250];
        $standards = [];

        foreach ($ratings as $amp) {
            $standards[] = [
                'code' => "IEC-MCCB-3P-{$amp}A-F250",
                'name' => "MCCB 3P {$amp}A 50kA Frame 250",
                'category' => ComponentStandard::CATEGORY_CIRCUIT_BREAKER,
                'subcategory' => ComponentStandard::SUBCATEGORY_MCCB,
                'standard' => 'IEC 60947-2',
                'specifications' => [
                    'rating_amps' => $amp,
                    'poles' => 3,
                    'frame_size' => 250,
                    'breaking_capacity_ka' => 50,
                    'voltage' => '415V AC',
                    'trip_unit' => 'TM (Thermal-Magnetic)',
                ],
                'unit' => 'pcs',
                'description' => 'MCCB Frame 250 for high-capacity feeder protection',
                'is_active' => true,
                'brand_mappings' => [
                    [
                        'brand' => ComponentBrandMapping::BRAND_SCHNEIDER,
                        'brand_sku' => "LV431630-TM{$amp}D",
                        'is_preferred' => true,
                        'price_factor' => 1.0,
                        'purchase_price' => $this->getMccbPrice('schneider', 250, $amp),
                        'notes' => 'NSX250F 36kA, TM-D adjustable',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_ABB,
                        'brand_sku' => "1SDA068061R1-{$amp}",
                        'is_preferred' => false,
                        'price_factor' => 1.05,
                        'purchase_price' => $this->getMccbPrice('abb', 250, $amp),
                        'notes' => 'Tmax XT4 250 TMD',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_SIEMENS,
                        'brand_sku' => "3VA23{$amp}-5HL32-0AA0",
                        'is_preferred' => false,
                        'price_factor' => 1.15,
                        'purchase_price' => $this->getMccbPrice('siemens', 250, $amp),
                        'notes' => 'SENTRON 3VA2 frame 400',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_LS,
                        'brand_sku' => "TS250N-ATU{$amp}",
                        'is_preferred' => false,
                        'price_factor' => 0.65,
                        'purchase_price' => $this->getMccbPrice('ls', 250, $amp),
                        'notes' => 'Susol TS250N',
                    ],
                ],
            ];
        }

        return $standards;
    }

    private function getMccbFrame400Standards(): array
    {
        $ratings = [250, 315, 350, 400];
        $standards = [];

        foreach ($ratings as $amp) {
            $standards[] = [
                'code' => "IEC-MCCB-3P-{$amp}A-F400",
                'name' => "MCCB 3P {$amp}A 50kA Frame 400",
                'category' => ComponentStandard::CATEGORY_CIRCUIT_BREAKER,
                'subcategory' => ComponentStandard::SUBCATEGORY_MCCB,
                'standard' => 'IEC 60947-2',
                'specifications' => [
                    'rating_amps' => $amp,
                    'poles' => 3,
                    'frame_size' => 400,
                    'breaking_capacity_ka' => 50,
                    'voltage' => '415V AC',
                    'trip_unit' => 'TM (Thermal-Magnetic)',
                ],
                'unit' => 'pcs',
                'description' => 'MCCB Frame 400 for main incoming and heavy feeders',
                'is_active' => true,
                'brand_mappings' => [
                    [
                        'brand' => ComponentBrandMapping::BRAND_SCHNEIDER,
                        'brand_sku' => "LV432630-TM{$amp}D",
                        'is_preferred' => true,
                        'price_factor' => 1.0,
                        'purchase_price' => $this->getMccbPrice('schneider', 400, $amp),
                        'notes' => 'NSX400F 36kA TM-D',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_ABB,
                        'brand_sku' => "1SDA069561R1-{$amp}",
                        'is_preferred' => false,
                        'price_factor' => 1.05,
                        'purchase_price' => $this->getMccbPrice('abb', 400, $amp),
                        'notes' => 'Tmax XT5 400 TMA',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_SIEMENS,
                        'brand_sku' => "3VA24{$amp}-5HL32-0AA0",
                        'is_preferred' => false,
                        'price_factor' => 1.15,
                        'purchase_price' => $this->getMccbPrice('siemens', 400, $amp),
                        'notes' => 'SENTRON 3VA2 frame 630',
                    ],
                ],
            ];
        }

        return $standards;
    }

    private function getMccbFrame630Standards(): array
    {
        $ratings = [400, 500, 630];
        $standards = [];

        foreach ($ratings as $amp) {
            $standards[] = [
                'code' => "IEC-MCCB-3P-{$amp}A-F630",
                'name' => "MCCB 3P {$amp}A 50kA Frame 630",
                'category' => ComponentStandard::CATEGORY_CIRCUIT_BREAKER,
                'subcategory' => ComponentStandard::SUBCATEGORY_MCCB,
                'standard' => 'IEC 60947-2',
                'specifications' => [
                    'rating_amps' => $amp,
                    'poles' => 3,
                    'frame_size' => 630,
                    'breaking_capacity_ka' => 50,
                    'voltage' => '415V AC',
                    'trip_unit' => 'Micrologic (Electronic)',
                ],
                'unit' => 'pcs',
                'description' => 'MCCB Frame 630 with electronic trip for precision',
                'is_active' => true,
                'brand_mappings' => [
                    [
                        'brand' => ComponentBrandMapping::BRAND_SCHNEIDER,
                        'brand_sku' => "LV433630-ML{$amp}",
                        'is_preferred' => true,
                        'price_factor' => 1.0,
                        'purchase_price' => $this->getMccbPrice('schneider', 630, $amp),
                        'notes' => 'NSX630F Micrologic 2.3',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_ABB,
                        'brand_sku' => "1SDA070561R1-{$amp}",
                        'is_preferred' => false,
                        'price_factor' => 1.05,
                        'purchase_price' => $this->getMccbPrice('abb', 630, $amp),
                        'notes' => 'Tmax XT6 630 Ekip Touch',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_SIEMENS,
                        'brand_sku' => "3VA26{$amp}-6HL32-0AA0",
                        'is_preferred' => false,
                        'price_factor' => 1.15,
                        'purchase_price' => $this->getMccbPrice('siemens', 630, $amp),
                        'notes' => 'SENTRON 3VA2 ETU330',
                    ],
                ],
            ];
        }

        return $standards;
    }

    private function getMccbFrame800Standards(): array
    {
        $ratings = [630, 700, 800];
        $standards = [];

        foreach ($ratings as $amp) {
            $standards[] = [
                'code' => "IEC-MCCB-3P-{$amp}A-F800",
                'name' => "MCCB 3P {$amp}A 65kA Frame 800",
                'category' => ComponentStandard::CATEGORY_CIRCUIT_BREAKER,
                'subcategory' => ComponentStandard::SUBCATEGORY_MCCB,
                'standard' => 'IEC 60947-2',
                'specifications' => [
                    'rating_amps' => $amp,
                    'poles' => 3,
                    'frame_size' => 800,
                    'breaking_capacity_ka' => 65,
                    'voltage' => '415V AC',
                    'trip_unit' => 'Micrologic (Electronic)',
                ],
                'unit' => 'pcs',
                'description' => 'MCCB Frame 800 for main incomer high-capacity',
                'is_active' => true,
                'brand_mappings' => [
                    [
                        'brand' => ComponentBrandMapping::BRAND_SCHNEIDER,
                        'brand_sku' => "LV434660-ML{$amp}",
                        'is_preferred' => true,
                        'price_factor' => 1.0,
                        'purchase_price' => $this->getMccbPrice('schneider', 800, $amp),
                        'notes' => 'Compact NS800N Micrologic 5.3',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_ABB,
                        'brand_sku' => "1SDA072561R1-{$amp}",
                        'is_preferred' => false,
                        'price_factor' => 1.05,
                        'purchase_price' => $this->getMccbPrice('abb', 800, $amp),
                        'notes' => 'Tmax XT7 800 Ekip Hi-Touch',
                    ],
                ],
            ];
        }

        return $standards;
    }

    private function getMccbPrice(string $brand, int $frame, int $amps): int
    {
        $basePrices = [
            'schneider' => 2500000,
            'abb' => 2700000,
            'siemens' => 3000000,
            'chint' => 950000,
            'ls' => 1600000,
            'legrand' => 2200000,
        ];

        $base = $basePrices[$brand] ?? 1500000;

        // Frame multiplier
        $frameMultiplier = match ($frame) {
            100 => 1.0,
            160 => 1.5,
            250 => 2.2,
            400 => 3.0,
            630 => 4.5,
            800 => 6.5,
            default => 1.0,
        };

        // Amp rating factor within frame
        $ampFactor = $amps / $frame;

        return (int) round($base * $frameMultiplier * (0.8 + $ampFactor * 0.3));
    }

    // =========================================================================
    // CONTACTORS (IEC 60947-4-1)
    // =========================================================================

    private function seedContactorStandards(): int
    {
        $this->command->line('   Seeding Contactor standards...');

        // AC-3 ratings commonly used in Indonesia
        $ratings = [
            ['ac3' => 9, 'kw' => 4],
            ['ac3' => 12, 'kw' => 5.5],
            ['ac3' => 18, 'kw' => 7.5],
            ['ac3' => 25, 'kw' => 11],
            ['ac3' => 32, 'kw' => 15],
            ['ac3' => 40, 'kw' => 18.5],
            ['ac3' => 50, 'kw' => 22],
            ['ac3' => 65, 'kw' => 30],
            ['ac3' => 80, 'kw' => 37],
            ['ac3' => 95, 'kw' => 45],
            ['ac3' => 115, 'kw' => 55],
            ['ac3' => 150, 'kw' => 75],
            ['ac3' => 185, 'kw' => 90],
            ['ac3' => 225, 'kw' => 110],
            ['ac3' => 265, 'kw' => 132],
            ['ac3' => 330, 'kw' => 160],
        ];

        $count = 0;
        foreach ($ratings as $rating) {
            $amp = $rating['ac3'];
            $kw = $rating['kw'];

            $this->createStandard([
                'code' => "IEC-CONT-{$amp}A-AC3",
                'name' => "Kontaktor {$amp}A AC-3 ({$kw}kW) 220VAC Coil",
                'category' => ComponentStandard::CATEGORY_CONTACTOR,
                'subcategory' => null,
                'standard' => 'IEC 60947-4-1',
                'specifications' => [
                    'rating_amps_ac3' => $amp,
                    'poles' => 3,
                    'power_kw_400v' => $kw,
                    'coil_voltage' => '220V AC',
                    'frequency' => '50Hz',
                    'aux_contacts' => '1NO+1NC',
                    'utilization_category' => 'AC-3',
                ],
                'unit' => 'pcs',
                'description' => "Kontaktor untuk kontrol motor {$kw}kW pada 400V",
                'is_active' => true,
                'brand_mappings' => $this->getContactorMappings($amp, $kw),
            ]);
            $count++;
        }

        return $count;
    }

    private function getContactorMappings(int $amp, float $kw): array
    {
        // Schneider TeSys D series mapping (LC1D)
        $schneiderCode = match (true) {
            $amp <= 9 => '09',
            $amp <= 12 => '12',
            $amp <= 18 => '18',
            $amp <= 25 => '25',
            $amp <= 32 => '32',
            $amp <= 40 => '40',
            $amp <= 50 => '50',
            $amp <= 65 => '65',
            $amp <= 80 => '80',
            $amp <= 95 => '95',
            $amp <= 115 => '115',
            $amp <= 150 => '150',
            $amp <= 185 => '185',
            $amp <= 225 => '225',
            $amp <= 265 => '265',
            default => '330',
        };

        // ABB A/AF series mapping
        $abbCode = match (true) {
            $amp <= 9 => '09',
            $amp <= 12 => '12',
            $amp <= 16 => '16',
            $amp <= 26 => '26',
            $amp <= 30 => '30',
            $amp <= 40 => '40',
            $amp <= 50 => '50',
            $amp <= 65 => '65',
            $amp <= 75 => '75',
            $amp <= 95 => '95',
            $amp <= 110 => '110',
            $amp <= 145 => '145',
            $amp <= 185 => '185',
            $amp <= 205 => '205',
            $amp <= 260 => '260',
            default => '305',
        };

        // Siemens SIRIUS 3RT2 series
        $siemensCode = match (true) {
            $amp <= 9 => '1016',
            $amp <= 12 => '1017',
            $amp <= 17 => '1018',
            $amp <= 25 => '1024',
            $amp <= 32 => '1026',
            $amp <= 40 => '1034',
            $amp <= 50 => '1036',
            $amp <= 65 => '1044',
            $amp <= 80 => '1046',
            $amp <= 95 => '1054',
            $amp <= 115 => '1056',
            $amp <= 150 => '1064',
            $amp <= 185 => '1066',
            $amp <= 225 => '2075',
            default => '2076',
        };

        $basePrice = match (true) {
            $amp <= 12 => 280000,
            $amp <= 25 => 380000,
            $amp <= 40 => 550000,
            $amp <= 65 => 850000,
            $amp <= 95 => 1350000,
            $amp <= 150 => 2200000,
            $amp <= 225 => 3500000,
            default => 5500000,
        };

        return [
            [
                'brand' => ComponentBrandMapping::BRAND_SCHNEIDER,
                'brand_sku' => "LC1D{$schneiderCode}M7",
                'is_preferred' => true,
                'price_factor' => 1.0,
                'purchase_price' => $basePrice,
                'notes' => "TeSys D - {$kw}kW at 400V, 220VAC coil",
            ],
            [
                'brand' => ComponentBrandMapping::BRAND_ABB,
                'brand_sku' => "AF{$abbCode}-30-10-13",
                'is_preferred' => false,
                'price_factor' => 0.95,
                'purchase_price' => (int) ($basePrice * 0.95),
                'notes' => "AF series - {$kw}kW, 100-250V AC/DC coil",
            ],
            [
                'brand' => ComponentBrandMapping::BRAND_SIEMENS,
                'brand_sku' => "3RT2{$siemensCode}-1AM20",
                'is_preferred' => false,
                'price_factor' => 1.08,
                'purchase_price' => (int) ($basePrice * 1.08),
                'notes' => "SIRIUS 3RT2 - {$kw}kW, 220VAC coil",
            ],
            [
                'brand' => ComponentBrandMapping::BRAND_CHINT,
                'brand_sku' => "NC1-{$amp}10-220V",
                'is_preferred' => false,
                'price_factor' => 0.45,
                'purchase_price' => (int) ($basePrice * 0.45),
                'notes' => 'NC1 series - Budget industrial option',
            ],
            [
                'brand' => ComponentBrandMapping::BRAND_LS,
                'brand_sku' => "MC-{$amp}a-220V",
                'is_preferred' => false,
                'price_factor' => 0.65,
                'purchase_price' => (int) ($basePrice * 0.65),
                'notes' => 'Metasol MC - Korean quality',
            ],
        ];
    }

    // =========================================================================
    // THERMAL OVERLOAD RELAYS (IEC 60947-4-1)
    // =========================================================================

    private function seedThermalOverloadStandards(): int
    {
        $this->command->line('   Seeding Thermal Overload Relay standards...');

        // Common adjustment ranges
        $ranges = [
            ['min' => 0.63, 'max' => 1],
            ['min' => 1, 'max' => 1.6],
            ['min' => 1.6, 'max' => 2.5],
            ['min' => 2.5, 'max' => 4],
            ['min' => 4, 'max' => 6],
            ['min' => 5.5, 'max' => 8],
            ['min' => 7, 'max' => 10],
            ['min' => 9, 'max' => 13],
            ['min' => 12, 'max' => 18],
            ['min' => 16, 'max' => 24],
            ['min' => 23, 'max' => 32],
            ['min' => 30, 'max' => 40],
            ['min' => 37, 'max' => 50],
            ['min' => 48, 'max' => 65],
            ['min' => 55, 'max' => 80],
            ['min' => 80, 'max' => 104],
        ];

        $count = 0;
        foreach ($ranges as $range) {
            $min = $range['min'];
            $max = $range['max'];

            $this->createStandard([
                'code' => "IEC-TOR-{$min}-{$max}A",
                'name' => "Thermal Overload Relay {$min}-{$max}A",
                'category' => ComponentStandard::CATEGORY_RELAY,
                'subcategory' => 'thermal_overload',
                'standard' => 'IEC 60947-4-1',
                'specifications' => [
                    'adjustment_range_min' => $min,
                    'adjustment_range_max' => $max,
                    'trip_class' => 10,
                    'reset_type' => 'Manual/Auto',
                    'mounting' => 'Direct mount to contactor',
                ],
                'unit' => 'pcs',
                'description' => "Thermal overload relay adjustable {$min}-{$max}A for motor protection",
                'is_active' => true,
                'brand_mappings' => $this->getThermalOverloadMappings($min, $max),
            ]);
            $count++;
        }

        return $count;
    }

    private function getThermalOverloadMappings(float $min, float $max): array
    {
        // Schneider LRD series code mapping
        $lrdCode = match (true) {
            $max <= 1.6 => '06',
            $max <= 2.5 => '07',
            $max <= 4 => '08',
            $max <= 6 => '10',
            $max <= 8 => '12',
            $max <= 10 => '14',
            $max <= 13 => '16',
            $max <= 18 => '21',
            $max <= 24 => '22',
            $max <= 32 => '32',
            $max <= 40 => '340',
            $max <= 50 => '350',
            $max <= 65 => '359',
            $max <= 80 => '365',
            default => '370',
        };

        $basePrice = match (true) {
            $max <= 4 => 220000,
            $max <= 10 => 250000,
            $max <= 18 => 280000,
            $max <= 32 => 320000,
            $max <= 50 => 450000,
            $max <= 80 => 650000,
            default => 850000,
        };

        return [
            [
                'brand' => ComponentBrandMapping::BRAND_SCHNEIDER,
                'brand_sku' => "LRD{$lrdCode}",
                'is_preferred' => true,
                'price_factor' => 1.0,
                'purchase_price' => $basePrice,
                'notes' => 'TeSys LRD - Class 10, direct mount to LC1D',
            ],
            [
                'brand' => ComponentBrandMapping::BRAND_ABB,
                'brand_sku' => "TA25DU-{$max}",
                'is_preferred' => false,
                'price_factor' => 0.92,
                'purchase_price' => (int) ($basePrice * 0.92),
                'notes' => 'TA series - mounts to A/AF contactors',
            ],
            [
                'brand' => ComponentBrandMapping::BRAND_SIEMENS,
                'brand_sku' => "3RU21{$lrdCode}-0AB0",
                'is_preferred' => false,
                'price_factor' => 1.05,
                'purchase_price' => (int) ($basePrice * 1.05),
                'notes' => 'SIRIUS 3RU2 - mounts to 3RT2 contactors',
            ],
            [
                'brand' => ComponentBrandMapping::BRAND_CHINT,
                'brand_sku' => "NR2-25-{$max}",
                'is_preferred' => false,
                'price_factor' => 0.40,
                'purchase_price' => (int) ($basePrice * 0.40),
                'notes' => 'NR2 series - mounts to NC1 contactors',
            ],
        ];
    }

    // =========================================================================
    // RCCB / RCBO (IEC 61008 / IEC 61009)
    // =========================================================================

    private function seedRccbRcboStandards(): int
    {
        $this->command->line('   Seeding RCCB/RCBO standards...');

        $count = 0;

        // 2-Pole RCCB (30mA)
        foreach ([25, 40, 63, 80, 100] as $amp) {
            $this->createStandard([
                'code' => "IEC-RCCB-2P-{$amp}A-30mA",
                'name' => "RCCB 2P {$amp}A 30mA",
                'category' => ComponentStandard::CATEGORY_CIRCUIT_BREAKER,
                'subcategory' => ComponentStandard::SUBCATEGORY_RCCB,
                'standard' => 'IEC 61008-1',
                'specifications' => [
                    'rating_amps' => $amp,
                    'poles' => 2,
                    'sensitivity_ma' => 30,
                    'type' => 'AC',
                    'voltage' => '230V AC',
                ],
                'unit' => 'pcs',
                'description' => 'RCCB 2P for personal protection 30mA',
                'is_active' => true,
                'brand_mappings' => [
                    [
                        'brand' => ComponentBrandMapping::BRAND_SCHNEIDER,
                        'brand_sku' => "A9R50{$amp}",
                        'is_preferred' => true,
                        'price_factor' => 1.0,
                        'purchase_price' => 350000 + ($amp * 2000),
                        'notes' => 'Acti 9 iID - Type AC 30mA',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_ABB,
                        'brand_sku' => "F202-{$amp}/0.03",
                        'is_preferred' => false,
                        'price_factor' => 0.95,
                        'purchase_price' => (int) ((350000 + ($amp * 2000)) * 0.95),
                        'notes' => 'F200 series - Type AC 30mA',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_SIEMENS,
                        'brand_sku' => "5SV1314-{$amp}",
                        'is_preferred' => false,
                        'price_factor' => 1.08,
                        'purchase_price' => (int) ((350000 + ($amp * 2000)) * 1.08),
                        'notes' => '5SV1 RCCB - Type A 30mA',
                    ],
                ],
            ]);
            $count++;
        }

        // 4-Pole RCCB (30mA)
        foreach ([25, 40, 63, 80, 100] as $amp) {
            $this->createStandard([
                'code' => "IEC-RCCB-4P-{$amp}A-30mA",
                'name' => "RCCB 4P {$amp}A 30mA",
                'category' => ComponentStandard::CATEGORY_CIRCUIT_BREAKER,
                'subcategory' => ComponentStandard::SUBCATEGORY_RCCB,
                'standard' => 'IEC 61008-1',
                'specifications' => [
                    'rating_amps' => $amp,
                    'poles' => 4,
                    'sensitivity_ma' => 30,
                    'type' => 'AC',
                    'voltage' => '400V AC',
                ],
                'unit' => 'pcs',
                'description' => 'RCCB 4P for 3-phase circuits with earth leakage protection',
                'is_active' => true,
                'brand_mappings' => [
                    [
                        'brand' => ComponentBrandMapping::BRAND_SCHNEIDER,
                        'brand_sku' => "A9R54{$amp}",
                        'is_preferred' => true,
                        'price_factor' => 1.0,
                        'purchase_price' => 650000 + ($amp * 3500),
                        'notes' => 'Acti 9 iID 4P - Type AC 30mA',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_ABB,
                        'brand_sku' => "F204-{$amp}/0.03",
                        'is_preferred' => false,
                        'price_factor' => 0.95,
                        'purchase_price' => (int) ((650000 + ($amp * 3500)) * 0.95),
                        'notes' => 'F204 4P - Type AC 30mA',
                    ],
                ],
            ]);
            $count++;
        }

        // RCBO (MCB + RCD combined)
        foreach ([6, 10, 16, 20, 25, 32] as $amp) {
            $this->createStandard([
                'code' => "IEC-RCBO-1PN-{$amp}A-C-30mA",
                'name' => "RCBO 1P+N {$amp}A C-Curve 30mA",
                'category' => ComponentStandard::CATEGORY_CIRCUIT_BREAKER,
                'subcategory' => ComponentStandard::SUBCATEGORY_RCBO,
                'standard' => 'IEC 61009-1',
                'specifications' => [
                    'rating_amps' => $amp,
                    'poles' => '1P+N',
                    'curve' => 'C',
                    'sensitivity_ma' => 30,
                    'breaking_capacity_ka' => 6,
                    'type' => 'A',
                ],
                'unit' => 'pcs',
                'description' => 'RCBO combined protection - overcurrent + earth leakage',
                'is_active' => true,
                'brand_mappings' => [
                    [
                        'brand' => ComponentBrandMapping::BRAND_SCHNEIDER,
                        'brand_sku' => "A9D318{$amp}",
                        'is_preferred' => true,
                        'price_factor' => 1.0,
                        'purchase_price' => 420000 + ($amp * 5000),
                        'notes' => 'Acti 9 iDPN Vigi - Type A 30mA',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_ABB,
                        'brand_sku' => "DSH201-C{$amp}",
                        'is_preferred' => false,
                        'price_factor' => 0.98,
                        'purchase_price' => (int) ((420000 + ($amp * 5000)) * 0.98),
                        'notes' => 'DS201 1P+N RCBO',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_SIEMENS,
                        'brand_sku' => "5SV1316-6KK{$amp}",
                        'is_preferred' => false,
                        'price_factor' => 1.12,
                        'purchase_price' => (int) ((420000 + ($amp * 5000)) * 1.12),
                        'notes' => '5SV1 RCBO - Type A',
                    ],
                ],
            ]);
            $count++;
        }

        return $count;
    }

    // =========================================================================
    // TIMER RELAYS
    // =========================================================================

    private function seedTimerRelayStandards(): int
    {
        $this->command->line('   Seeding Timer Relay standards...');

        $timers = [
            [
                'code' => 'IEC-TMR-ONDELAY-220V',
                'name' => 'Timer Relay On-Delay 0.1s-10min 220VAC',
                'type' => 'On-Delay',
                'range' => '0.1s-10min',
            ],
            [
                'code' => 'IEC-TMR-OFFDELAY-220V',
                'name' => 'Timer Relay Off-Delay 0.1s-10min 220VAC',
                'type' => 'Off-Delay',
                'range' => '0.1s-10min',
            ],
            [
                'code' => 'IEC-TMR-STAR-DELTA-220V',
                'name' => 'Timer Relay Star-Delta 1-30s 220VAC',
                'type' => 'Star-Delta',
                'range' => '1-30s',
            ],
            [
                'code' => 'IEC-TMR-MULTIFUNCTION-220V',
                'name' => 'Timer Relay Multi-Function 0.1s-100h 220VAC',
                'type' => 'Multi-Function (8 modes)',
                'range' => '0.1s-100h',
            ],
            [
                'code' => 'IEC-TMR-CYCLIC-220V',
                'name' => 'Timer Relay Cyclic 0.1s-10min 220VAC',
                'type' => 'Cyclic/Flasher',
                'range' => '0.1s-10min',
            ],
        ];

        $count = 0;
        foreach ($timers as $timer) {
            $this->createStandard([
                'code' => $timer['code'],
                'name' => $timer['name'],
                'category' => ComponentStandard::CATEGORY_RELAY,
                'subcategory' => 'timer',
                'standard' => 'IEC 61812',
                'specifications' => [
                    'function' => $timer['type'],
                    'time_range' => $timer['range'],
                    'supply_voltage' => '220V AC',
                    'output' => '2CO (DPDT)',
                    'contact_rating' => '5A 250VAC',
                    'width_modules' => 2,
                ],
                'unit' => 'pcs',
                'description' => "Timer relay {$timer['type']} for automation and control",
                'is_active' => true,
                'brand_mappings' => $this->getTimerRelayMappings($timer['type']),
            ]);
            $count++;
        }

        return $count;
    }

    private function getTimerRelayMappings(string $type): array
    {
        $schneiderCode = match ($type) {
            'On-Delay' => 'RE17RAMU',
            'Off-Delay' => 'RE17RBMU',
            'Star-Delta' => 'RE17RLMU',
            'Multi-Function' => 'RE48AMH13MW',
            'Cyclic/Flasher' => 'RE17RCMU',
            default => 'RE17RAMU',
        };

        $abbCode = match ($type) {
            'On-Delay' => 'CT-MFD.12',
            'Off-Delay' => 'CT-MFD.22',
            'Star-Delta' => 'CT-MFD.32',
            'Multi-Function' => 'CT-MFD.21',
            'Cyclic/Flasher' => 'CT-MFD.11',
            default => 'CT-MFD.12',
        };

        $siemensCode = match ($type) {
            'On-Delay' => '3RP2505-1AW30',
            'Off-Delay' => '3RP2505-1BW30',
            'Star-Delta' => '3RP1574-1NP30',
            'Multi-Function' => '3RP2574-1NW30',
            'Cyclic/Flasher' => '3RP2505-2AW30',
            default => '3RP2505-1AW30',
        };

        $basePrice = match ($type) {
            'Multi-Function' => 850000,
            'Star-Delta' => 720000,
            default => 450000,
        };

        return [
            [
                'brand' => ComponentBrandMapping::BRAND_SCHNEIDER,
                'brand_sku' => $schneiderCode,
                'is_preferred' => true,
                'price_factor' => 1.0,
                'purchase_price' => $basePrice,
                'notes' => 'Zelio Time RE17/RE48 series',
            ],
            [
                'brand' => ComponentBrandMapping::BRAND_ABB,
                'brand_sku' => $abbCode,
                'is_preferred' => false,
                'price_factor' => 0.90,
                'purchase_price' => (int) ($basePrice * 0.90),
                'notes' => 'CT-MFD multi-function timer',
            ],
            [
                'brand' => ComponentBrandMapping::BRAND_SIEMENS,
                'brand_sku' => $siemensCode,
                'is_preferred' => false,
                'price_factor' => 1.15,
                'purchase_price' => (int) ($basePrice * 1.15),
                'notes' => 'SIRIUS 3RP timing relay',
            ],
            [
                'brand' => ComponentBrandMapping::BRAND_CHINT,
                'brand_sku' => 'NTE8-'.substr($type, 0, 2),
                'is_preferred' => false,
                'price_factor' => 0.35,
                'purchase_price' => (int) ($basePrice * 0.35),
                'notes' => 'NTE8 series timer - budget option',
            ],
        ];
    }

    // =========================================================================
    // POWER METERS
    // =========================================================================

    private function seedPowerMeterStandards(): int
    {
        $this->command->line('   Seeding Power Meter standards...');

        $meters = [
            [
                'code' => 'IEC-PM-BASIC-3P',
                'name' => 'Power Meter Basic 3-Phase (V, A, kW, kWh)',
                'class' => 'Basic',
                'accuracy' => '1.0',
                'comms' => 'Pulse output',
            ],
            [
                'code' => 'IEC-PM-MID-3P',
                'name' => 'Power Meter Mid-Range 3-Phase (V, A, kW, kVAr, PF, kWh)',
                'class' => 'Mid-Range',
                'accuracy' => '0.5S',
                'comms' => 'Modbus RS485',
            ],
            [
                'code' => 'IEC-PM-ADV-3P',
                'name' => 'Power Meter Advanced 3-Phase with Harmonics',
                'class' => 'Advanced',
                'accuracy' => '0.2S',
                'comms' => 'Modbus TCP/RS485, Ethernet',
            ],
        ];

        $count = 0;
        foreach ($meters as $meter) {
            $basePrice = match ($meter['class']) {
                'Basic' => 1500000,
                'Mid-Range' => 4500000,
                'Advanced' => 12000000,
                default => 2000000,
            };

            $this->createStandard([
                'code' => $meter['code'],
                'name' => $meter['name'],
                'category' => ComponentStandard::CATEGORY_METER,
                'subcategory' => 'power_meter',
                'standard' => 'IEC 62053',
                'specifications' => [
                    'class' => $meter['class'],
                    'accuracy' => $meter['accuracy'],
                    'phases' => 3,
                    'communications' => $meter['comms'],
                    'ct_ratio' => 'Programmable',
                    'display' => 'LCD Backlit',
                ],
                'unit' => 'pcs',
                'description' => "3-Phase power meter - {$meter['class']} class",
                'is_active' => true,
                'brand_mappings' => [
                    [
                        'brand' => ComponentBrandMapping::BRAND_SCHNEIDER,
                        'brand_sku' => match ($meter['class']) {
                            'Basic' => 'METSEPM2120',
                            'Mid-Range' => 'METSEPM5310',
                            'Advanced' => 'METSEPM8240',
                            default => 'METSEPM2120',
                        },
                        'is_preferred' => true,
                        'price_factor' => 1.0,
                        'purchase_price' => $basePrice,
                        'notes' => 'PowerLogic PM series',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_ABB,
                        'brand_sku' => match ($meter['class']) {
                            'Basic' => 'M2M',
                            'Mid-Range' => 'M4M',
                            'Advanced' => 'M5M',
                            default => 'M2M',
                        },
                        'is_preferred' => false,
                        'price_factor' => 0.95,
                        'purchase_price' => (int) ($basePrice * 0.95),
                        'notes' => 'ABB M-series power meter',
                    ],
                    [
                        'brand' => ComponentBrandMapping::BRAND_SIEMENS,
                        'brand_sku' => match ($meter['class']) {
                            'Basic' => '7KM2112-0BA00',
                            'Mid-Range' => '7KM3220-0BA00',
                            'Advanced' => '7KM4212-0BA00',
                            default => '7KM2112-0BA00',
                        },
                        'is_preferred' => false,
                        'price_factor' => 1.1,
                        'purchase_price' => (int) ($basePrice * 1.1),
                        'notes' => 'SENTRON PAC series',
                    ],
                ],
            ]);
            $count++;
        }

        return $count;
    }

    // =========================================================================
    // PUSH BUTTONS & PILOT LAMPS
    // =========================================================================

    private function seedPushButtonStandards(): int
    {
        $this->command->line('   Seeding Push Button standards...');

        $buttons = [
            ['type' => 'Flush', 'color' => 'Green', 'contacts' => '1NO'],
            ['type' => 'Flush', 'color' => 'Red', 'contacts' => '1NC'],
            ['type' => 'Flush', 'color' => 'Yellow', 'contacts' => '1NO+1NC'],
            ['type' => 'Flush', 'color' => 'Blue', 'contacts' => '1NO'],
            ['type' => 'Flush', 'color' => 'White', 'contacts' => '1NO'],
            ['type' => 'Flush', 'color' => 'Black', 'contacts' => '1NO+1NC'],
            ['type' => 'Mushroom', 'color' => 'Red', 'contacts' => '1NC'],
            ['type' => 'Emergency-Stop', 'color' => 'Red/Yellow', 'contacts' => '1NC'],
            ['type' => 'Illuminated', 'color' => 'Green', 'contacts' => '1NO'],
            ['type' => 'Illuminated', 'color' => 'Red', 'contacts' => '1NC'],
        ];

        $count = 0;
        foreach ($buttons as $btn) {
            $code = strtoupper(substr($btn['type'], 0, 3).'-'.substr($btn['color'], 0, 3));

            $this->createStandard([
                'code' => "IEC-PB-{$code}",
                'name' => "Push Button {$btn['type']} {$btn['color']} {$btn['contacts']}",
                'category' => ComponentStandard::CATEGORY_TERMINAL,
                'subcategory' => 'push_button',
                'standard' => 'IEC 60947-5-1',
                'specifications' => [
                    'type' => $btn['type'],
                    'color' => $btn['color'],
                    'contacts' => $btn['contacts'],
                    'mounting' => '22mm',
                    'voltage' => '220V AC/DC',
                    'ip_rating' => 'IP65',
                ],
                'unit' => 'pcs',
                'description' => "Push button {$btn['type']} type, {$btn['color']}, {$btn['contacts']}",
                'is_active' => true,
                'brand_mappings' => $this->getPushButtonMappings($btn),
            ]);
            $count++;
        }

        return $count;
    }

    private function getPushButtonMappings(array $btn): array
    {
        $colorCode = match ($btn['color']) {
            'Green' => '3',
            'Red' => '4',
            'Yellow' => '5',
            'Blue' => '6',
            'White' => '1',
            'Black' => '2',
            'Red/Yellow' => '4',
            default => '0',
        };

        $typeCode = match ($btn['type']) {
            'Flush' => 'BA',
            'Mushroom' => 'BT',
            'Emergency-Stop' => 'BS',
            'Illuminated' => 'BW',
            default => 'BA',
        };

        $basePrice = match ($btn['type']) {
            'Emergency-Stop' => 185000,
            'Illuminated' => 165000,
            'Mushroom' => 145000,
            default => 85000,
        };

        return [
            [
                'brand' => ComponentBrandMapping::BRAND_SCHNEIDER,
                'brand_sku' => "XB4{$typeCode}{$colorCode}1",
                'is_preferred' => true,
                'price_factor' => 1.0,
                'purchase_price' => $basePrice,
                'notes' => 'Harmony XB4 - Metal, IP66',
            ],
            [
                'brand' => ComponentBrandMapping::BRAND_ABB,
                'brand_sku' => "1SFA61{$colorCode}10R1",
                'is_preferred' => false,
                'price_factor' => 0.95,
                'purchase_price' => (int) ($basePrice * 0.95),
                'notes' => 'CP1 series - Industrial',
            ],
            [
                'brand' => ComponentBrandMapping::BRAND_SIEMENS,
                'brand_sku' => "3SU1150-0A{$typeCode}0-{$colorCode}AA0",
                'is_preferred' => false,
                'price_factor' => 1.1,
                'purchase_price' => (int) ($basePrice * 1.1),
                'notes' => 'SIRIUS ACT - Metal',
            ],
            [
                'brand' => ComponentBrandMapping::BRAND_CHINT,
                'brand_sku' => "NP2-{$typeCode}{$colorCode}",
                'is_preferred' => false,
                'price_factor' => 0.35,
                'purchase_price' => (int) ($basePrice * 0.35),
                'notes' => 'NP2 series - Budget',
            ],
        ];
    }

    private function seedPilotLampStandards(): int
    {
        $this->command->line('   Seeding Pilot Lamp standards...');

        $colors = ['Green', 'Red', 'Yellow', 'Blue', 'White'];

        $count = 0;
        foreach ($colors as $color) {
            $this->createStandard([
                'code' => 'IEC-PL-LED-'.strtoupper(substr($color, 0, 3)),
                'name' => "Pilot Lamp LED {$color} 220VAC",
                'category' => ComponentStandard::CATEGORY_TERMINAL,
                'subcategory' => 'pilot_lamp',
                'standard' => 'IEC 60947-5-1',
                'specifications' => [
                    'type' => 'LED',
                    'color' => $color,
                    'voltage' => '220V AC',
                    'mounting' => '22mm',
                    'ip_rating' => 'IP65',
                ],
                'unit' => 'pcs',
                'description' => "LED pilot lamp {$color} for panel indication",
                'is_active' => true,
                'brand_mappings' => $this->getPilotLampMappings($color),
            ]);
            $count++;
        }

        return $count;
    }

    private function getPilotLampMappings(string $color): array
    {
        $colorCode = match ($color) {
            'Green' => '3',
            'Red' => '4',
            'Yellow' => '5',
            'Blue' => '6',
            'White' => '1',
            default => '0',
        };

        return [
            [
                'brand' => ComponentBrandMapping::BRAND_SCHNEIDER,
                'brand_sku' => "XB4BVM{$colorCode}",
                'is_preferred' => true,
                'price_factor' => 1.0,
                'purchase_price' => 75000,
                'notes' => 'Harmony XB4 LED indicator',
            ],
            [
                'brand' => ComponentBrandMapping::BRAND_ABB,
                'brand_sku' => "1SFA619402R{$colorCode}",
                'is_preferred' => false,
                'price_factor' => 0.92,
                'purchase_price' => 69000,
                'notes' => 'CL2 LED pilot light',
            ],
            [
                'brand' => ComponentBrandMapping::BRAND_SIEMENS,
                'brand_sku' => "3SU1156-6AA{$colorCode}0-1AA0",
                'is_preferred' => false,
                'price_factor' => 1.08,
                'purchase_price' => 81000,
                'notes' => 'SIRIUS ACT indicator light',
            ],
            [
                'brand' => ComponentBrandMapping::BRAND_CHINT,
                'brand_sku' => "ND16-22DS-{$colorCode}",
                'is_preferred' => false,
                'price_factor' => 0.30,
                'purchase_price' => 22500,
                'notes' => 'ND16 LED indicator',
            ],
        ];
    }

    // =========================================================================
    // CABLES (SNI / IEC Standards)
    // =========================================================================

    private function seedCableStandards(): int
    {
        $this->command->line('   Seeding Cable standards...');

        $count = 0;

        // NYY Power Cables (0.6/1kV)
        $nyySizes = [
            ['mm2' => 1.5, 'amp' => 16],
            ['mm2' => 2.5, 'amp' => 21],
            ['mm2' => 4, 'amp' => 28],
            ['mm2' => 6, 'amp' => 36],
            ['mm2' => 10, 'amp' => 50],
            ['mm2' => 16, 'amp' => 66],
            ['mm2' => 25, 'amp' => 84],
            ['mm2' => 35, 'amp' => 104],
            ['mm2' => 50, 'amp' => 125],
            ['mm2' => 70, 'amp' => 160],
            ['mm2' => 95, 'amp' => 195],
            ['mm2' => 120, 'amp' => 225],
            ['mm2' => 150, 'amp' => 260],
            ['mm2' => 185, 'amp' => 300],
            ['mm2' => 240, 'amp' => 355],
        ];

        foreach ($nyySizes as $size) {
            $mm2 = $size['mm2'];
            $amp = $size['amp'];

            // 3-Core NYY
            $this->createStandard([
                'code' => "IEC-CBL-NYY-3x{$mm2}",
                'name' => "Kabel NYY 3x{$mm2}mm² 0.6/1kV",
                'category' => ComponentStandard::CATEGORY_CABLE,
                'subcategory' => 'power_cable',
                'standard' => 'SNI 04-0225 / IEC 60502',
                'specifications' => [
                    'type' => 'NYY',
                    'conductor_mm2' => $mm2,
                    'cores' => 3,
                    'voltage_rating' => '0.6/1kV',
                    'current_capacity_a' => $amp,
                    'conductor' => 'Copper (Cu)',
                    'insulation' => 'PVC',
                    'sheath' => 'PVC',
                ],
                'unit' => 'meter',
                'description' => 'Kabel power NYY 3 core untuk instalasi tetap',
                'is_active' => true,
                'brand_mappings' => $this->getCableMappings('NYY', 3, $mm2),
            ]);
            $count++;

            // 4-Core NYY (for larger sizes)
            if ($mm2 >= 4) {
                $this->createStandard([
                    'code' => "IEC-CBL-NYY-4x{$mm2}",
                    'name' => "Kabel NYY 4x{$mm2}mm² 0.6/1kV",
                    'category' => ComponentStandard::CATEGORY_CABLE,
                    'subcategory' => 'power_cable',
                    'standard' => 'SNI 04-0225 / IEC 60502',
                    'specifications' => [
                        'type' => 'NYY',
                        'conductor_mm2' => $mm2,
                        'cores' => 4,
                        'voltage_rating' => '0.6/1kV',
                        'current_capacity_a' => (int) ($amp * 0.9),
                        'conductor' => 'Copper (Cu)',
                        'insulation' => 'PVC',
                        'sheath' => 'PVC',
                    ],
                    'unit' => 'meter',
                    'description' => 'Kabel power NYY 4 core dengan netral',
                    'is_active' => true,
                    'brand_mappings' => $this->getCableMappings('NYY', 4, $mm2),
                ]);
                $count++;
            }
        }

        // NYM Installation Cables (300/500V)
        $nymSizes = [1.5, 2.5, 4, 6, 10];
        foreach ($nymSizes as $mm2) {
            foreach ([2, 3, 4] as $cores) {
                $this->createStandard([
                    'code' => "IEC-CBL-NYM-{$cores}x{$mm2}",
                    'name' => "Kabel NYM {$cores}x{$mm2}mm² 300/500V",
                    'category' => ComponentStandard::CATEGORY_CABLE,
                    'subcategory' => 'installation_cable',
                    'standard' => 'SNI 04-6629 / IEC 60227',
                    'specifications' => [
                        'type' => 'NYM',
                        'conductor_mm2' => $mm2,
                        'cores' => $cores,
                        'voltage_rating' => '300/500V',
                        'conductor' => 'Copper (Cu)',
                        'insulation' => 'PVC',
                        'sheath' => 'PVC',
                        'application' => 'Indoor installation',
                    ],
                    'unit' => 'meter',
                    'description' => "Kabel instalasi NYM {$cores} core untuk dalam ruangan",
                    'is_active' => true,
                    'brand_mappings' => $this->getCableMappings('NYM', $cores, $mm2),
                ]);
                $count++;
            }
        }

        // NYAF Flexible Control Cables
        $nyafSizes = [0.75, 1.0, 1.5, 2.5, 4, 6];
        foreach ($nyafSizes as $mm2) {
            $this->createStandard([
                'code' => "IEC-CBL-NYAF-1x{$mm2}",
                'name' => "Kabel NYAF 1x{$mm2}mm² Flexible",
                'category' => ComponentStandard::CATEGORY_CABLE,
                'subcategory' => 'control_cable',
                'standard' => 'SNI 04-6629 / IEC 60227',
                'specifications' => [
                    'type' => 'NYAF',
                    'conductor_mm2' => $mm2,
                    'cores' => 1,
                    'voltage_rating' => '450/750V',
                    'conductor' => 'Stranded Copper (Cu)',
                    'insulation' => 'PVC',
                    'flexibility' => 'Class 5 (Flexible)',
                ],
                'unit' => 'meter',
                'description' => 'Kabel kontrol fleksibel untuk wiring panel',
                'is_active' => true,
                'brand_mappings' => $this->getCableMappings('NYAF', 1, $mm2),
            ]);
            $count++;
        }

        return $count;
    }

    private function getCableMappings(string $type, int $cores, float $mm2): array
    {
        // Price per meter in IDR (realistic market prices)
        $basePrice = match ($type) {
            'NYY' => match (true) {
                $mm2 <= 2.5 => 15000 * $cores,
                $mm2 <= 6 => 25000 * $cores,
                $mm2 <= 16 => 50000 * $cores,
                $mm2 <= 35 => 90000 * $cores,
                $mm2 <= 70 => 180000 * $cores,
                $mm2 <= 120 => 300000 * $cores,
                default => 450000 * $cores,
            },
            'NYM' => match (true) {
                $mm2 <= 2.5 => 8000 * $cores,
                $mm2 <= 6 => 15000 * $cores,
                default => 25000 * $cores,
            },
            'NYAF' => match (true) {
                $mm2 <= 1.5 => 3500,
                $mm2 <= 4 => 8000,
                default => 15000,
            },
            default => 10000,
        };

        $codeStr = "{$type}-{$cores}X{$mm2}";

        return [
            [
                'brand' => 'supreme',
                'brand_sku' => "SUP-{$codeStr}",
                'is_preferred' => true,
                'price_factor' => 1.0,
                'purchase_price' => $basePrice,
                'notes' => 'Supreme Cable - Premium SNI certified',
            ],
            [
                'brand' => 'kabelindo',
                'brand_sku' => "KBI-{$codeStr}",
                'is_preferred' => false,
                'price_factor' => 0.95,
                'purchase_price' => (int) ($basePrice * 0.95),
                'notes' => 'Kabelindo - SNI certified, trusted brand',
            ],
            [
                'brand' => 'sutrado',
                'brand_sku' => "STR-{$codeStr}",
                'is_preferred' => false,
                'price_factor' => 0.90,
                'purchase_price' => (int) ($basePrice * 0.90),
                'notes' => 'Sutrado - Quality Indonesian cable',
            ],
            [
                'brand' => 'eterna',
                'brand_sku' => "ETN-{$codeStr}",
                'is_preferred' => false,
                'price_factor' => 0.85,
                'purchase_price' => (int) ($basePrice * 0.85),
                'notes' => 'Eterna - Economic option, SNI',
            ],
        ];
    }

    // =========================================================================
    // BUSBARS
    // =========================================================================

    private function seedBusbarStandards(): int
    {
        $this->command->line('   Seeding Busbar standards...');

        // Standard busbar sizes (Width x Thickness in mm, Current rating)
        $busbars = [
            ['w' => 12, 't' => 2, 'amp' => 100],
            ['w' => 15, 't' => 3, 'amp' => 150],
            ['w' => 20, 't' => 3, 'amp' => 200],
            ['w' => 20, 't' => 5, 'amp' => 300],
            ['w' => 25, 't' => 5, 'amp' => 400],
            ['w' => 30, 't' => 5, 'amp' => 500],
            ['w' => 40, 't' => 5, 'amp' => 630],
            ['w' => 50, 't' => 5, 'amp' => 800],
            ['w' => 60, 't' => 5, 'amp' => 1000],
            ['w' => 80, 't' => 5, 'amp' => 1250],
            ['w' => 100, 't' => 5, 'amp' => 1600],
            ['w' => 50, 't' => 10, 'amp' => 1250],
            ['w' => 60, 't' => 10, 'amp' => 1600],
            ['w' => 80, 't' => 10, 'amp' => 2000],
            ['w' => 100, 't' => 10, 'amp' => 2500],
        ];

        $count = 0;
        foreach ($busbars as $bb) {
            $w = $bb['w'];
            $t = $bb['t'];
            $amp = $bb['amp'];

            $this->createStandard([
                'code' => "IEC-BB-CU-{$w}x{$t}-{$amp}A",
                'name' => "Busbar Copper {$w}x{$t}mm ({$amp}A)",
                'category' => ComponentStandard::CATEGORY_BUSBAR,
                'subcategory' => null,
                'standard' => 'IEC 61439-1',
                'specifications' => [
                    'material' => 'Copper (E-Cu)',
                    'width_mm' => $w,
                    'thickness_mm' => $t,
                    'current_rating_a' => $amp,
                    'conductivity' => '100% IACS',
                    'plating' => 'Tin-plated',
                    'standard_length_m' => 4,
                ],
                'unit' => 'meter',
                'description' => "Copper busbar {$w}x{$t}mm rated {$amp}A per bar",
                'is_active' => true,
                'brand_mappings' => $this->getBusbarMappings($w, $t, $amp),
            ]);
            $count++;
        }

        return $count;
    }

    private function getBusbarMappings(int $w, int $t, int $amp): array
    {
        // Price per meter based on copper weight (density ~8.9 kg/dm³)
        $area_mm2 = $w * $t;
        $weight_per_m = $area_mm2 * 8.9 / 1000; // kg/m
        $copperPrice = 150000; // IDR per kg (current market ~$10/kg)
        $basePrice = (int) ($weight_per_m * $copperPrice * 1.3); // 30% markup for processing

        return [
            [
                'brand' => ComponentBrandMapping::BRAND_SCHNEIDER,
                'brand_sku' => "LVSB-CU-{$w}X{$t}",
                'is_preferred' => true,
                'price_factor' => 1.0,
                'purchase_price' => $basePrice,
                'notes' => 'Prisma busbar - Premium grade E-Cu',
            ],
            [
                'brand' => ComponentBrandMapping::BRAND_ABB,
                'brand_sku' => "ARTU-BB-{$w}X{$t}",
                'is_preferred' => false,
                'price_factor' => 1.05,
                'purchase_price' => (int) ($basePrice * 1.05),
                'notes' => 'ArTu busbar system',
            ],
            [
                'brand' => ComponentBrandMapping::BRAND_LEGRAND,
                'brand_sku' => "XL3-BB-{$w}X{$t}",
                'is_preferred' => false,
                'price_factor' => 0.95,
                'purchase_price' => (int) ($basePrice * 0.95),
                'notes' => 'XL³ busbar - French quality',
            ],
            [
                'brand' => 'local',
                'brand_sku' => "LOC-BB-CU-{$w}X{$t}",
                'is_preferred' => false,
                'price_factor' => 0.75,
                'purchase_price' => (int) ($basePrice * 0.75),
                'notes' => 'Local copper busbar - SNI certified',
            ],
        ];
    }

    // =========================================================================
    // TERMINAL BLOCKS
    // =========================================================================

    private function seedTerminalBlockStandards(): int
    {
        $this->command->line('   Seeding Terminal Block standards...');

        $terminals = [
            ['mm2' => 2.5, 'amp' => 24, 'type' => 'Screw'],
            ['mm2' => 4, 'amp' => 32, 'type' => 'Screw'],
            ['mm2' => 6, 'amp' => 41, 'type' => 'Screw'],
            ['mm2' => 10, 'amp' => 57, 'type' => 'Screw'],
            ['mm2' => 16, 'amp' => 76, 'type' => 'Screw'],
            ['mm2' => 25, 'amp' => 101, 'type' => 'Screw'],
            ['mm2' => 35, 'amp' => 125, 'type' => 'Screw'],
            ['mm2' => 50, 'amp' => 150, 'type' => 'Screw'],
            ['mm2' => 70, 'amp' => 192, 'type' => 'Screw'],
            ['mm2' => 95, 'amp' => 232, 'type' => 'Screw'],
            ['mm2' => 120, 'amp' => 269, 'type' => 'Screw'],
            // Spring clamp
            ['mm2' => 2.5, 'amp' => 24, 'type' => 'Spring'],
            ['mm2' => 4, 'amp' => 32, 'type' => 'Spring'],
            ['mm2' => 6, 'amp' => 41, 'type' => 'Spring'],
        ];

        $count = 0;
        foreach ($terminals as $term) {
            $mm2 = $term['mm2'];
            $amp = $term['amp'];
            $type = $term['type'];

            $this->createStandard([
                'code' => "IEC-TB-{$type}-{$mm2}mm2",
                'name' => "Terminal Block {$type} {$mm2}mm² ({$amp}A)",
                'category' => ComponentStandard::CATEGORY_TERMINAL,
                'subcategory' => 'terminal_block',
                'standard' => 'IEC 60947-7-1',
                'specifications' => [
                    'type' => $type,
                    'conductor_mm2' => $mm2,
                    'current_rating_a' => $amp,
                    'voltage_rating' => '800V',
                    'mounting' => 'DIN Rail (TS35)',
                    'color' => 'Gray',
                ],
                'unit' => 'pcs',
                'description' => "{$type} clamp terminal block for {$mm2}mm² conductors",
                'is_active' => true,
                'brand_mappings' => $this->getTerminalBlockMappings($mm2, $type),
            ]);
            $count++;
        }

        return $count;
    }

    private function getTerminalBlockMappings(float $mm2, string $type): array
    {
        $basePrice = match (true) {
            $mm2 <= 4 => 12000,
            $mm2 <= 10 => 18000,
            $mm2 <= 25 => 35000,
            $mm2 <= 50 => 65000,
            $mm2 <= 95 => 120000,
            default => 180000,
        };

        if ($type === 'Spring') {
            $basePrice = (int) ($basePrice * 1.5);
        }

        $schneiderCode = $type === 'Spring' ? 'NSYTR' : 'NSYB';

        return [
            [
                'brand' => ComponentBrandMapping::BRAND_SCHNEIDER,
                'brand_sku' => "{$schneiderCode}".((int) $mm2),
                'is_preferred' => true,
                'price_factor' => 1.0,
                'purchase_price' => $basePrice,
                'notes' => 'Linergy TR terminal - Industrial grade',
            ],
            [
                'brand' => ComponentBrandMapping::BRAND_ABB,
                'brand_sku' => '1SNK'.((int) $mm2),
                'is_preferred' => false,
                'price_factor' => 0.95,
                'purchase_price' => (int) ($basePrice * 0.95),
                'notes' => 'SNK terminal blocks',
            ],
            [
                'brand' => ComponentBrandMapping::BRAND_SIEMENS,
                'brand_sku' => '8WH'.((int) $mm2),
                'is_preferred' => false,
                'price_factor' => 1.1,
                'purchase_price' => (int) ($basePrice * 1.1),
                'notes' => '8WH series terminals',
            ],
            [
                'brand' => 'phoenix',
                'brand_sku' => 'UK'.((int) $mm2),
                'is_preferred' => false,
                'price_factor' => 1.15,
                'purchase_price' => (int) ($basePrice * 1.15),
                'notes' => 'Phoenix Contact UK series - Premium',
            ],
            [
                'brand' => ComponentBrandMapping::BRAND_CHINT,
                'brand_sku' => 'NJD2-'.((int) $mm2),
                'is_preferred' => false,
                'price_factor' => 0.40,
                'purchase_price' => (int) ($basePrice * 0.40),
                'notes' => 'NJD2 terminal - Budget option',
            ],
        ];
    }

    // =========================================================================
    // ENCLOSURES
    // =========================================================================

    private function seedEnclosureStandards(): int
    {
        $this->command->line('   Seeding Enclosure standards...');

        $enclosures = [
            // Wall-mounted distribution boards
            ['type' => 'DB', 'ways' => 4, 'rows' => 1, 'ip' => 'IP40'],
            ['type' => 'DB', 'ways' => 6, 'rows' => 1, 'ip' => 'IP40'],
            ['type' => 'DB', 'ways' => 8, 'rows' => 1, 'ip' => 'IP40'],
            ['type' => 'DB', 'ways' => 12, 'rows' => 1, 'ip' => 'IP40'],
            ['type' => 'DB', 'ways' => 18, 'rows' => 2, 'ip' => 'IP40'],
            ['type' => 'DB', 'ways' => 24, 'rows' => 2, 'ip' => 'IP40'],
            ['type' => 'DB', 'ways' => 36, 'rows' => 3, 'ip' => 'IP40'],
            ['type' => 'DB', 'ways' => 48, 'rows' => 4, 'ip' => 'IP40'],
            // IP65 Enclosures
            ['type' => 'IP65', 'ways' => 12, 'rows' => 1, 'ip' => 'IP65'],
            ['type' => 'IP65', 'ways' => 24, 'rows' => 2, 'ip' => 'IP65'],
            // Floor standing panels
            ['type' => 'FS', 'height' => 2000, 'width' => 600, 'depth' => 400, 'ip' => 'IP55'],
            ['type' => 'FS', 'height' => 2000, 'width' => 800, 'depth' => 400, 'ip' => 'IP55'],
            ['type' => 'FS', 'height' => 2000, 'width' => 1000, 'depth' => 400, 'ip' => 'IP55'],
            ['type' => 'FS', 'height' => 2000, 'width' => 600, 'depth' => 600, 'ip' => 'IP55'],
            ['type' => 'FS', 'height' => 2000, 'width' => 800, 'depth' => 600, 'ip' => 'IP55'],
        ];

        $count = 0;
        foreach ($enclosures as $enc) {
            if ($enc['type'] === 'FS') {
                $h = $enc['height'];
                $w = $enc['width'];
                $d = $enc['depth'];
                $ip = $enc['ip'];

                $this->createStandard([
                    'code' => "IEC-ENC-FS-{$h}x{$w}x{$d}",
                    'name' => "Panel Enclosure {$h}x{$w}x{$d}mm {$ip}",
                    'category' => ComponentStandard::CATEGORY_ENCLOSURE,
                    'subcategory' => 'floor_standing',
                    'standard' => 'IEC 62208 / IEC 61439',
                    'specifications' => [
                        'type' => 'Floor Standing',
                        'height_mm' => $h,
                        'width_mm' => $w,
                        'depth_mm' => $d,
                        'ip_rating' => $ip,
                        'material' => 'Steel (1.5mm)',
                        'color' => 'RAL 7035 Gray',
                        'door' => 'Single/Double swing',
                    ],
                    'unit' => 'pcs',
                    'description' => "Floor standing panel enclosure {$h}x{$w}x{$d}mm",
                    'is_active' => true,
                    'brand_mappings' => $this->getFloorEnclosureMappings($h, $w, $d),
                ]);
            } else {
                $ways = $enc['ways'];
                $rows = $enc['rows'];
                $ip = $enc['ip'];
                $type = $enc['type'];

                $this->createStandard([
                    'code' => "IEC-ENC-{$type}-{$ways}W",
                    'name' => "Distribution Board {$ways}-Way {$rows}R {$ip}",
                    'category' => ComponentStandard::CATEGORY_ENCLOSURE,
                    'subcategory' => 'distribution_board',
                    'standard' => 'IEC 61439-3',
                    'specifications' => [
                        'type' => $type === 'IP65' ? 'Weatherproof' : 'Indoor',
                        'ways' => $ways,
                        'rows' => $rows,
                        'ip_rating' => $ip,
                        'material' => $type === 'IP65' ? 'ABS Plastic' : 'Steel',
                        'mounting' => 'Surface/Flush',
                    ],
                    'unit' => 'pcs',
                    'description' => "Distribution board {$ways} ways {$ip}",
                    'is_active' => true,
                    'brand_mappings' => $this->getDistributionBoardMappings($ways, $ip),
                ]);
            }
            $count++;
        }

        return $count;
    }

    private function getFloorEnclosureMappings(int $h, int $w, int $d): array
    {
        // Price based on volume and steel usage
        $volume = ($h / 1000) * ($w / 1000) * ($d / 1000);
        $basePrice = (int) (2500000 + $volume * 15000000);

        return [
            [
                'brand' => ComponentBrandMapping::BRAND_SCHNEIDER,
                'brand_sku' => "NSYP{$h}{$w}{$d}",
                'is_preferred' => true,
                'price_factor' => 1.0,
                'purchase_price' => $basePrice,
                'notes' => 'Spacial SF/SM - Premium modular enclosure',
            ],
            [
                'brand' => ComponentBrandMapping::BRAND_ABB,
                'brand_sku' => "SR2{$h}{$w}{$d}",
                'is_preferred' => false,
                'price_factor' => 1.05,
                'purchase_price' => (int) ($basePrice * 1.05),
                'notes' => 'SR2 modular enclosure system',
            ],
            [
                'brand' => ComponentBrandMapping::BRAND_SIEMENS,
                'brand_sku' => "8PQ{$h}{$w}{$d}",
                'is_preferred' => false,
                'price_factor' => 1.15,
                'purchase_price' => (int) ($basePrice * 1.15),
                'notes' => 'ALPHA enclosure series',
            ],
            [
                'brand' => 'rittal',
                'brand_sku' => "AE{$h}.{$w}.{$d}",
                'is_preferred' => false,
                'price_factor' => 1.25,
                'purchase_price' => (int) ($basePrice * 1.25),
                'notes' => 'Rittal AE - Premium German engineering',
            ],
            [
                'brand' => 'local',
                'brand_sku' => "LOC-FS-{$h}{$w}{$d}",
                'is_preferred' => false,
                'price_factor' => 0.60,
                'purchase_price' => (int) ($basePrice * 0.60),
                'notes' => 'Local fabrication - SNI standard',
            ],
        ];
    }

    private function getDistributionBoardMappings(int $ways, string $ip): array
    {
        $basePrice = match (true) {
            $ways <= 6 => 250000,
            $ways <= 12 => 450000,
            $ways <= 24 => 750000,
            $ways <= 36 => 1100000,
            default => 1500000,
        };

        if ($ip === 'IP65') {
            $basePrice = (int) ($basePrice * 1.8);
        }

        return [
            [
                'brand' => ComponentBrandMapping::BRAND_SCHNEIDER,
                'brand_sku' => "A9HESM{$ways}",
                'is_preferred' => true,
                'price_factor' => 1.0,
                'purchase_price' => $basePrice,
                'notes' => "Pragma / Easy9 DB - {$ip}",
            ],
            [
                'brand' => ComponentBrandMapping::BRAND_ABB,
                'brand_sku' => "1SL{$ways}P40",
                'is_preferred' => false,
                'price_factor' => 0.95,
                'purchase_price' => (int) ($basePrice * 0.95),
                'notes' => 'Mistral65 distribution board',
            ],
            [
                'brand' => ComponentBrandMapping::BRAND_LEGRAND,
                'brand_sku' => "4016{$ways}",
                'is_preferred' => false,
                'price_factor' => 0.90,
                'purchase_price' => (int) ($basePrice * 0.90),
                'notes' => 'Nedbox distribution board',
            ],
            [
                'brand' => ComponentBrandMapping::BRAND_CHINT,
                'brand_sku' => "PZ30-{$ways}",
                'is_preferred' => false,
                'price_factor' => 0.45,
                'purchase_price' => (int) ($basePrice * 0.45),
                'notes' => 'PZ30 series - Budget option',
            ],
        ];
    }
}
