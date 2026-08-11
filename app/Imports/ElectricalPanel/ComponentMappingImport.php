<?php

namespace App\Imports\ElectricalPanel;

use App\Models\ElectricalPanel\ComponentBrandMapping;
use App\Models\ElectricalPanel\ComponentStandard;
use App\Models\Inventory\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ComponentMappingImport implements ToCollection, WithHeadingRow, WithValidation
{
    use Importable;

    private bool $validateOnly;

    /** @var array<int, array<string, mixed>> */
    private array $validRows = [];

    /** @var array<int, array<string, mixed>> */
    private array $errors = [];

    /** @var array<int, array<string, mixed>> */
    private array $warnings = [];

    private int $createdStandards = 0;

    private int $createdMappings = 0;

    private int $updatedMappings = 0;

    public function __construct(bool $validateOnly = false)
    {
        $this->validateOnly = $validateOnly;
    }

    /**
     * @param  Collection<int, Collection<string, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +2 for header row and 0-indexing

            // Skip empty rows
            if (empty($row['product_sku']) && empty($row['standard_code'])) {
                continue;
            }

            $validationResult = $this->validateRow($row->toArray(), $rowNumber);

            if ($validationResult['valid']) {
                $this->validRows[] = array_merge($row->toArray(), ['row_number' => $rowNumber]);

                if (! empty($validationResult['warnings'])) {
                    foreach ($validationResult['warnings'] as $warning) {
                        $this->warnings[] = [
                            'row' => $rowNumber,
                            'message' => $warning,
                            'data' => $row->toArray(),
                        ];
                    }
                }

                // Process if not validate-only mode
                if (! $this->validateOnly) {
                    $this->processRow($row->toArray());
                }
            } else {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'message' => $validationResult['error'],
                    'data' => $row->toArray(),
                ];
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{valid: bool, error: string|null, warnings: array<int, string>}
     */
    private function validateRow(array $row, int $rowNumber): array
    {
        $warnings = [];

        // Required fields
        if (empty($row['product_sku'])) {
            return ['valid' => false, 'error' => 'product_sku is required', 'warnings' => []];
        }

        if (empty($row['brand'])) {
            return ['valid' => false, 'error' => 'brand is required', 'warnings' => []];
        }

        if (empty($row['standard_code'])) {
            return ['valid' => false, 'error' => 'standard_code is required', 'warnings' => []];
        }

        if (empty($row['category'])) {
            return ['valid' => false, 'error' => 'category is required', 'warnings' => []];
        }

        // Validate product exists
        $product = Product::where('sku', $row['product_sku'])->first();
        if (! $product) {
            return ['valid' => false, 'error' => "Product SKU '{$row['product_sku']}' not found", 'warnings' => []];
        }

        // Validate brand
        $validBrands = array_keys(ComponentBrandMapping::getBrands());
        if (! in_array($row['brand'], $validBrands)) {
            return ['valid' => false, 'error' => "Invalid brand '{$row['brand']}'. Valid: ".implode(', ', $validBrands), 'warnings' => []];
        }

        // Validate category
        $validCategories = array_keys(ComponentStandard::getCategories());
        if (! in_array($row['category'], $validCategories)) {
            return ['valid' => false, 'error' => "Invalid category '{$row['category']}'. Valid: ".implode(', ', $validCategories), 'warnings' => []];
        }

        // Check if standard will be created
        $existingStandard = ComponentStandard::where('code', $row['standard_code'])->first();
        if (! $existingStandard) {
            $warnings[] = "Standard '{$row['standard_code']}' will be created";
        }

        // Check if mapping already exists
        if ($existingStandard) {
            $existingMapping = ComponentBrandMapping::where('component_standard_id', $existingStandard->id)
                ->where('product_id', $product->id)
                ->first();
            if ($existingMapping) {
                $warnings[] = 'Mapping already exists and will be updated';
            }
        }

        return ['valid' => true, 'error' => null, 'warnings' => $warnings];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function processRow(array $row): void
    {
        DB::transaction(function () use ($row) {
            // Find or create the component standard
            $standard = ComponentStandard::where('code', $row['standard_code'])->first();

            if (! $standard) {
                $specifications = $this->buildSpecifications($row);

                $standard = ComponentStandard::create([
                    'code' => $row['standard_code'],
                    'name' => $row['standard_name'] ?? $row['standard_code'],
                    'category' => $row['category'],
                    'subcategory' => $row['subcategory'] ?? null,
                    'specifications' => $specifications,
                    'unit' => 'pcs',
                    'is_active' => true,
                    'created_by' => auth()->id(),
                ]);

                $this->createdStandards++;
            }

            // Find the product
            $product = Product::where('sku', $row['product_sku'])->first();

            // Find or create the mapping
            $mapping = ComponentBrandMapping::where('component_standard_id', $standard->id)
                ->where('product_id', $product->id)
                ->first();

            if ($mapping) {
                $mapping->update([
                    'brand' => $row['brand'],
                    'brand_sku' => $row['brand_sku'] ?? $row['product_sku'],
                    'is_preferred' => strtolower($row['is_preferred'] ?? 'no') === 'yes',
                ]);
                $this->updatedMappings++;
            } else {
                ComponentBrandMapping::create([
                    'component_standard_id' => $standard->id,
                    'brand' => $row['brand'],
                    'product_id' => $product->id,
                    'brand_sku' => $row['brand_sku'] ?? $row['product_sku'],
                    'is_preferred' => strtolower($row['is_preferred'] ?? 'no') === 'yes',
                    'is_verified' => false,
                    'price_factor' => 1.00,
                ]);
                $this->createdMappings++;
            }
        });
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function buildSpecifications(array $row): array
    {
        $specs = [];

        if (! empty($row['specs_amps'])) {
            $specs['rating_amps'] = (int) $row['specs_amps'];
        }
        if (! empty($row['specs_poles'])) {
            $specs['poles'] = (int) $row['specs_poles'];
        }
        if (! empty($row['specs_curve'])) {
            $specs['curve'] = $row['specs_curve'];
        }
        if (! empty($row['specs_breaking_capacity_ka'])) {
            $specs['breaking_capacity_ka'] = (float) $row['specs_breaking_capacity_ka'];
        }

        return $specs;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'product_sku' => ['required', 'string'],
            'brand' => ['required', 'string'],
            'standard_code' => ['required', 'string'],
            'category' => ['required', 'string'],
        ];
    }

    /**
     * @return array{valid_count: int, error_count: int, warning_count: int, valid_rows: array<int, array<string, mixed>>, errors: array<int, array<string, mixed>>, warnings: array<int, array<string, mixed>>}
     */
    public function getValidationResults(): array
    {
        return [
            'valid_count' => count($this->validRows),
            'error_count' => count($this->errors),
            'warning_count' => count($this->warnings),
            'valid_rows' => $this->validRows,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }

    /**
     * @return array{created_standards: int, created_mappings: int, updated_mappings: int, errors: array<int, array<string, mixed>>}
     */
    public function getImportResults(): array
    {
        return [
            'created_standards' => $this->createdStandards,
            'created_mappings' => $this->createdMappings,
            'updated_mappings' => $this->updatedMappings,
            'errors' => $this->errors,
        ];
    }
}
