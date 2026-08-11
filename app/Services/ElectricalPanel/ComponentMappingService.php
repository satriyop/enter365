<?php

declare(strict_types=1);

namespace App\Services\ElectricalPanel;

use App\Models\Inventory\Product;
use App\Models\ElectricalPanel\ComponentBrandMapping;
use App\Models\ElectricalPanel\ComponentStandard;
use Illuminate\Support\Collection;

/**
 * Service for component mapping operations.
 *
 * Handles auto-mapping suggestions, product name parsing, and bulk mapping operations.
 */
class ComponentMappingService
{
    public function __construct(
        private ProductEquivalenceService $equivalenceService
    ) {}

    /**
     * Get products without mappings for a given brand.
     *
     * @return Collection<int, Product>
     */
    public function getUnmappedProducts(?string $brand = null, int $limit = 50): Collection
    {
        $query = Product::query()
            ->whereDoesntHave('componentBrandMappings')
            ->where('is_active', true)
            ->where('type', 'component') // Only electrical components
            ->orderBy('name');

        if ($brand) {
            $query->where('brand', $brand);
        }

        return $query->limit($limit)->get();
    }

    /**
     * Suggest component standards for a product based on name parsing.
     *
     * @return array{product_id: int, product_name: string, suggestions: array, parsed_specs: array}
     */
    public function suggestMappingForProduct(Product $product): array
    {
        $parsedSpecs = $this->parseProductName($product->name);
        $suggestions = [];

        if (! empty($parsedSpecs['category'])) {
            // Search for matching standards
            $matchingStandards = ComponentStandard::query()
                ->active()
                ->inCategory($parsedSpecs['category'])
                ->when($parsedSpecs['subcategory'] ?? null, fn ($q, $sub) => $q->inSubcategory($sub))
                ->get()
                ->map(function ($standard) use ($parsedSpecs) {
                    $score = $this->equivalenceService->calculateMatchScore(
                        $parsedSpecs['specs'] ?? [],
                        $standard->specifications ?? []
                    );

                    return [
                        'standard' => $standard,
                        'score' => $score,
                    ];
                })
                ->filter(fn ($item) => $item['score'] >= 50)
                ->sortByDesc('score')
                ->take(5);

            foreach ($matchingStandards as $match) {
                $standard = $match['standard'];
                $suggestions[] = [
                    'component_standard_id' => $standard->id,
                    'code' => $standard->code,
                    'name' => $standard->name,
                    'category' => $standard->category,
                    'subcategory' => $standard->subcategory,
                    'specifications' => $standard->specifications,
                    'match_score' => $match['score'],
                    'existing_brands' => $standard->brandMappings()
                        ->distinct('brand')
                        ->pluck('brand')
                        ->toArray(),
                ];
            }
        }

        return [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'product_brand' => $product->brand,
            'parsed_specs' => $parsedSpecs,
            'suggestions' => $suggestions,
            'has_suggestions' => count($suggestions) > 0,
        ];
    }

    /**
     * Get mapping suggestions for multiple products.
     *
     * @param  Collection<int, Product>  $products
     * @return array<int, array>
     */
    public function suggestMappingsForProducts(Collection $products): array
    {
        return $products->map(fn ($product) => $this->suggestMappingForProduct($product))->toArray();
    }

    /**
     * Parse product name to extract category and specifications.
     *
     * @return array{category: string|null, subcategory: string|null, specs: array, brand: string|null}
     */
    public function parseProductName(string $name): array
    {
        $name = strtoupper($name);
        $result = [
            'category' => null,
            'subcategory' => null,
            'specs' => [],
            'brand' => null,
        ];

        // Detect brand
        $brands = [
            'schneider' => ['SCHNEIDER', 'EASY9', 'ACTI9', 'DOMAE', 'RCCB'],
            'abb' => ['ABB', 'S200', 'S201', 'S202', 'S203', 'SH200'],
            'siemens' => ['SIEMENS', 'SENTRON', '5SL', '5SY', '5SV'],
            'chint' => ['CHINT', 'NB1', 'NXB', 'NXBLE'],
            'hager' => ['HAGER', 'MC', 'MU', 'MY'],
            'legrand' => ['LEGRAND', 'DX3', 'RX3'],
            'ls' => ['LS', 'BKN', 'BKH', 'METASOL'],
        ];

        foreach ($brands as $brandCode => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($name, $keyword)) {
                    $result['brand'] = $brandCode;
                    break 2;
                }
            }
        }

        // Detect category and subcategory from keywords
        $categoryPatterns = $this->getCategoryPatterns();

        foreach ($categoryPatterns as $category => $config) {
            foreach ($config['keywords'] as $keyword) {
                if (str_contains($name, $keyword)) {
                    $result['category'] = $category;

                    // Check subcategories
                    foreach ($config['subcategories'] as $subcat => $subKeywords) {
                        foreach ($subKeywords as $subKeyword) {
                            if (str_contains($name, $subKeyword)) {
                                $result['subcategory'] = $subcat;
                                break 2;
                            }
                        }
                    }
                    break 2;
                }
            }
        }

        // Extract specifications based on category
        $result['specs'] = $this->extractSpecsFromName($name, $result['category']);

        return $result;
    }

    /**
     * Accept a mapping suggestion and create the mapping.
     */
    public function acceptMappingSuggestion(
        Product $product,
        ComponentStandard $standard,
        ?string $brandSku = null,
        bool $isPreferred = false
    ): ComponentBrandMapping {
        // Detect brand from product
        $parsedSpecs = $this->parseProductName($product->name);
        $brand = $parsedSpecs['brand'] ?? $product->brand ?? 'other';

        return ComponentBrandMapping::create([
            'component_standard_id' => $standard->id,
            'brand' => $brand,
            'product_id' => $product->id,
            'brand_sku' => $brandSku ?? $product->sku,
            'is_preferred' => $isPreferred,
            'is_verified' => false, // Needs manual verification
        ]);
    }

    /**
     * Bulk accept mapping suggestions.
     *
     * @param  array<int, array{product_id: int, component_standard_id: int, brand_sku?: string}>  $mappings
     * @return array{created: int, skipped: int, errors: array}
     */
    public function bulkAcceptMappingSuggestions(array $mappings): array
    {
        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($mappings as $mapping) {
            try {
                $product = Product::find($mapping['product_id']);
                $standard = ComponentStandard::find($mapping['component_standard_id']);

                if (! $product || ! $standard) {
                    $skipped++;
                    $errors[] = "Product or Standard not found for product_id: {$mapping['product_id']}";

                    continue;
                }

                // Check if mapping already exists
                $exists = ComponentBrandMapping::query()
                    ->where('product_id', $product->id)
                    ->where('component_standard_id', $standard->id)
                    ->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                $this->acceptMappingSuggestion(
                    $product,
                    $standard,
                    $mapping['brand_sku'] ?? null,
                    $mapping['is_preferred'] ?? false
                );

                $created++;
            } catch (\Exception $e) {
                $errors[] = "Error for product_id {$mapping['product_id']}: ".$e->getMessage();
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Get category patterns for product name parsing.
     *
     * @return array<string, array{subcategories: array, keywords: array}>
     */
    private function getCategoryPatterns(): array
    {
        return [
            'circuit_breaker' => [
                'subcategories' => [
                    'mcb' => ['MCB', 'MINIATURE CIRCUIT BREAKER', 'C1', 'C2', 'C4', 'C6', 'C10', 'C16', 'C20', 'C25', 'C32', 'C40', 'C50', 'C63'],
                    'mccb' => ['MCCB', 'MOLDED CASE', 'NSX', 'NZM', '3VT', 'CVS'],
                    'rccb' => ['RCCB', 'ELCB', 'RESIDUAL CURRENT', 'RCD'],
                    'rcbo' => ['RCBO', 'RCMC'],
                ],
                'keywords' => ['BREAKER', 'MCB', 'MCCB', 'RCCB', 'RCBO', 'ACB', 'CIRCUIT'],
            ],
            'contactor' => [
                'subcategories' => [
                    'power' => ['CONTACTOR', 'LC1', 'AF', 'A9C', 'NC1'],
                    'auxiliary' => ['AUXILIARY', 'CA', 'LADN', 'LADT'],
                ],
                'keywords' => ['CONTACTOR', 'LC1', 'AF0', 'AF09', 'AF12', 'AF16', 'AF26'],
            ],
            'relay' => [
                'subcategories' => [
                    'thermal' => ['THERMAL', 'OVERLOAD', 'LRD', 'LR2', 'LRE'],
                    'control' => ['CONTROL', 'TIMER', 'TIME DELAY'],
                ],
                'keywords' => ['RELAY', 'OVERLOAD', 'LRD', 'LRE', 'THERMAL'],
            ],
            'cable' => [
                'subcategories' => [
                    'power' => ['NYY', 'NYA', 'NYM', 'NYFGBY'],
                    'control' => ['NYMHY', 'NYAF', 'FLEXIBLE'],
                ],
                'keywords' => ['CABLE', 'NYY', 'NYA', 'NYM', 'KABEL', 'NYFGBY'],
            ],
            'busbar' => [
                'subcategories' => [],
                'keywords' => ['BUSBAR', 'BUS BAR', 'COPPER BAR', 'DISTRIBUSI'],
            ],
            'terminal' => [
                'subcategories' => [
                    'din_rail' => ['DIN RAIL', 'TERMINAL BLOCK'],
                    'lug' => ['LUG', 'CABLE LUG', 'SKUN'],
                ],
                'keywords' => ['TERMINAL', 'BLOCK', 'LUG', 'SKUN'],
            ],
            'enclosure' => [
                'subcategories' => [
                    'panel_box' => ['PANEL BOX', 'DISTRIBUTION BOX', 'DB BOX'],
                    'junction' => ['JUNCTION', 'PULL BOX'],
                ],
                'keywords' => ['PANEL', 'BOX', 'ENCLOSURE', 'CABINET'],
            ],
        ];
    }

    /**
     * Extract technical specifications from product name.
     *
     * @return array<string, mixed>
     */
    private function extractSpecsFromName(string $name, ?string $category): array
    {
        $specs = [];

        // Extract amperage (e.g., "16A", "16 A", "16 AMPERE")
        if (preg_match('/(\d+)\s*(A|AMP|AMPERE)\b/i', $name, $matches)) {
            $specs['rating_amps'] = (int) $matches[1];
        }

        // Extract poles (e.g., "1P", "2P", "3P", "4P", "1 POLE", "3 PHASE")
        if (preg_match('/(\d)\s*(P|POLE|PHASE)\b/i', $name, $matches)) {
            $specs['poles'] = (int) $matches[1];
        }

        // Extract curve type (e.g., "C16", "B10", "D32")
        if (preg_match('/\b([BCD])[\s-]?(\d+)/i', $name, $matches)) {
            $specs['curve'] = strtoupper($matches[1]);
            if (! isset($specs['rating_amps'])) {
                $specs['rating_amps'] = (int) $matches[2];
            }
        }

        // Extract breaking capacity (e.g., "6kA", "10 kA", "6000A")
        if (preg_match('/(\d+)\s*(K|KILO)?A\s*(BREAKING|CAPACITY)?/i', $name, $matches)) {
            $value = (int) $matches[1];
            if (stripos($matches[2] ?? '', 'K') !== false || $value < 100) {
                $specs['breaking_capacity_ka'] = $value;
            }
        }

        // Extract voltage (e.g., "220V", "380V", "400V")
        if (preg_match('/(\d{2,3})\s*V\b/i', $name, $matches)) {
            $specs['voltage'] = (int) $matches[1];
        }

        // Category-specific extractions
        if ($category === 'cable') {
            // Extract conductor size (e.g., "2.5mm2", "2.5 mm²", "4mm")
            if (preg_match('/(\d+(?:\.\d+)?)\s*mm(?:²|2)?/i', $name, $matches)) {
                $specs['conductor_size_mm2'] = (float) $matches[1];
            }

            // Extract cores (e.g., "3x2.5", "4 core", "3C")
            if (preg_match('/(\d)\s*(?:X|x|C|CORE)/i', $name, $matches)) {
                $specs['cores'] = (int) $matches[1];
            }
        }

        if ($category === 'contactor') {
            // Extract coil voltage (e.g., "220VAC", "COIL 220V")
            if (preg_match('/(?:COIL\s*)?(\d{2,3})\s*V?\s*AC/i', $name, $matches)) {
                $specs['coil_voltage'] = (int) $matches[1];
            }

            // Extract AC category (e.g., "AC3", "AC-3")
            if (preg_match('/AC[\s-]?(\d)/i', $name, $matches)) {
                $specs['ac_category'] = 'AC'.$matches[1];
            }
        }

        if ($category === 'busbar') {
            // Extract dimensions (e.g., "20x3mm", "30x5")
            if (preg_match('/(\d+)\s*[xX]\s*(\d+)\s*(?:mm)?/i', $name, $matches)) {
                $specs['width_mm'] = (int) $matches[1];
                $specs['thickness_mm'] = (int) $matches[2];
            }

            // Detect material
            if (str_contains($name, 'COPPER') || str_contains($name, 'TEMBAGA')) {
                $specs['material'] = 'copper';
            } elseif (str_contains($name, 'ALUMINIUM') || str_contains($name, 'ALUMINUM')) {
                $specs['material'] = 'aluminium';
            }
        }

        return $specs;
    }
}
