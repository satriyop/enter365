<?php

namespace App\Exports;

use App\Models\ElectricalPanel\ComponentBrandMapping;
use App\Models\ElectricalPanel\ComponentStandard;
use App\Models\Inventory\Product;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ComponentMappingTemplateExport implements WithMultipleSheets
{
    use Exportable;

    private bool $includeExamples;

    private bool $includeProducts;

    public function __construct(bool $includeExamples = true, bool $includeProducts = true)
    {
        $this->includeExamples = $includeExamples;
        $this->includeProducts = $includeProducts;
    }

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        $sheets = [
            new ComponentMappingTemplateSheet($this->includeExamples),
        ];

        if ($this->includeProducts) {
            $sheets[] = new ProductsReferenceSheet;
        }

        $sheets[] = new CategoriesReferenceSheet;
        $sheets[] = new BrandsReferenceSheet;

        return $sheets;
    }
}

/**
 * Main mapping template sheet
 */
class ComponentMappingTemplateSheet implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\ShouldAutoSize, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithStyles, \Maatwebsite\Excel\Concerns\WithTitle
{
    private bool $includeExamples;

    public function __construct(bool $includeExamples = true)
    {
        $this->includeExamples = $includeExamples;
    }

    public function title(): string
    {
        return 'Component Mappings';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'product_sku',
            'brand',
            'standard_code',
            'standard_name',
            'category',
            'subcategory',
            'specs_amps',
            'specs_poles',
            'specs_curve',
            'specs_breaking_capacity_ka',
            'brand_sku',
            'is_preferred',
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        if (! $this->includeExamples) {
            return [];
        }

        return [
            ['SCH-MCB-16A-1P', 'schneider', 'MCB-16A-1P-C', 'MCB 16A 1P Curve C', 'circuit_breaker', 'mcb', '16', '1', 'C', '6', 'A9F74116', 'yes'],
            ['ABB-S201-C16', 'abb', 'MCB-16A-1P-C', 'MCB 16A 1P Curve C', 'circuit_breaker', 'mcb', '16', '1', 'C', '6', 'S201-C16', 'no'],
            ['CHINT-NB1-16', 'chint', 'MCB-16A-1P-C', 'MCB 16A 1P Curve C', 'circuit_breaker', 'mcb', '16', '1', 'C', '6', 'NB1-63/C16', 'no'],
            ['SCH-LC1D25', 'schneider', 'CONTACTOR-25A-3P', 'Contactor 25A 3P 220V', 'contactor', '', '25', '3', '', '', 'LC1D25M7', 'yes'],
            ['ABB-AF26', 'abb', 'CONTACTOR-25A-3P', 'Contactor 25A 3P 220V', 'contactor', '', '25', '3', '', '', 'AF26-30-00-13', 'no'],
        ];
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0'],
                ],
            ],
        ];
    }
}

/**
 * Products reference sheet
 */
class ProductsReferenceSheet implements \Maatwebsite\Excel\Concerns\FromQuery, \Maatwebsite\Excel\Concerns\ShouldAutoSize, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithMapping, \Maatwebsite\Excel\Concerns\WithTitle
{
    public function title(): string
    {
        return 'Products (Reference)';
    }

    public function query(): \Illuminate\Database\Eloquent\Builder
    {
        return Product::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('sku');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['sku', 'name', 'brand', 'purchase_price', 'has_mapping'];
    }

    /**
     * @param  Product  $product
     * @return array<int, mixed>
     */
    public function map($product): array
    {
        $hasMapping = ComponentBrandMapping::where('product_id', $product->id)->exists();

        return [
            $product->sku,
            $product->name,
            $product->brand ?? '',
            $product->purchase_price,
            $hasMapping ? 'yes' : 'no',
        ];
    }
}

/**
 * Categories reference sheet
 */
class CategoriesReferenceSheet implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\ShouldAutoSize, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithTitle
{
    public function title(): string
    {
        return 'Categories (Reference)';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['category_code', 'category_label', 'subcategories'];
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        $categories = ComponentStandard::getCategories();
        $subcategories = ComponentStandard::getCircuitBreakerSubcategories();

        $data = [];
        foreach ($categories as $code => $label) {
            $subs = $code === 'circuit_breaker'
                ? implode(', ', array_keys($subcategories))
                : '';
            $data[] = [$code, $label, $subs];
        }

        return $data;
    }
}

/**
 * Brands reference sheet
 */
class BrandsReferenceSheet implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\ShouldAutoSize, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithTitle
{
    public function title(): string
    {
        return 'Brands (Reference)';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['brand_code', 'brand_name'];
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        $brands = ComponentBrandMapping::getBrands();
        $data = [];
        foreach ($brands as $code => $name) {
            $data[] = [$code, $name];
        }

        return $data;
    }
}
