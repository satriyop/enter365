<?php

namespace App\Http\Controllers\Api\V1\ElectricalPanel;

use App\Exports\ComponentMappingTemplateExport;
use App\Http\Controllers\Api\V1\Controller;
use App\Imports\ComponentMappingImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ComponentMappingImportController extends Controller
{
    /**
     * Download component mapping template.
     *
     * Downloads an Excel template for bulk importing component mappings.
     * The template includes:
     * - Main sheet with column headers and example data
     * - Products reference sheet with all available products
     * - Categories reference sheet with valid category codes
     * - Brands reference sheet with valid brand codes
     *
     * @queryParam include_examples bool Include example rows in template. Default: true
     * @queryParam include_products bool Include products reference sheet. Default: true
     * @queryParam format string File format (xlsx or csv). Default: xlsx
     *
     * @response 200 scenario="Success" Binary file download
     *
     * @unauthenticated
     */
    public function downloadTemplate(Request $request): BinaryFileResponse
    {
        $includeExamples = $request->boolean('include_examples', true);
        $includeProducts = $request->boolean('include_products', true);
        $format = $request->input('format', 'xlsx');

        $export = new ComponentMappingTemplateExport($includeExamples, $includeProducts);
        $filename = 'component_mapping_template_'.date('Y-m-d');

        if ($format === 'csv') {
            return Excel::download($export, $filename.'.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return Excel::download($export, $filename.'.xlsx', \Maatwebsite\Excel\Excel::XLSX);
    }

    /**
     * Validate component mapping import file.
     *
     * Validates the uploaded file without actually importing.
     * Returns a preview of what will be imported and any errors found.
     *
     * @bodyParam file file required The Excel or CSV file to validate. Example: mapping.xlsx
     *
     * @response 200 {
     *   "data": {
     *     "valid_count": 45,
     *     "error_count": 2,
     *     "warning_count": 3,
     *     "can_import": true,
     *     "errors": [
     *       {"row": 12, "message": "Product SKU 'XYZ-123' not found"}
     *     ],
     *     "warnings": [
     *       {"row": 8, "message": "Standard 'MCB-20A-1P-C' will be created"}
     *     ],
     *     "summary": {
     *       "new_standards": 5,
     *       "new_mappings": 40,
     *       "updated_mappings": 5
     *     }
     *   }
     * }
     * @response 422 {"message": "Validation failed", "errors": {"file": ["The file field is required."]}}
     */
    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $import = new ComponentMappingImport(validateOnly: true);
        Excel::import($import, $request->file('file'));

        $results = $import->getValidationResults();

        // Count new standards vs existing
        $newStandards = collect($results['warnings'])
            ->filter(fn ($w) => str_contains($w['message'], 'will be created'))
            ->count();

        return response()->json([
            'data' => [
                'valid_count' => $results['valid_count'],
                'error_count' => $results['error_count'],
                'warning_count' => $results['warning_count'],
                'can_import' => $results['valid_count'] > 0,
                'errors' => array_slice($results['errors'], 0, 20), // Limit to first 20 errors
                'warnings' => array_slice($results['warnings'], 0, 20),
                'summary' => [
                    'new_standards' => $newStandards,
                    'new_mappings' => $results['valid_count'],
                ],
            ],
        ]);
    }

    /**
     * Import component mappings from file.
     *
     * Imports component mappings from the uploaded Excel or CSV file.
     * Creates component standards and brand mappings.
     *
     * @bodyParam file file required The Excel or CSV file to import. Example: mapping.xlsx
     * @bodyParam skip_errors bool Skip rows with errors and import valid rows only. Default: true
     *
     * @response 201 {
     *   "message": "Import berhasil.",
     *   "data": {
     *     "created_standards": 5,
     *     "created_mappings": 42,
     *     "updated_mappings": 3,
     *     "skipped_errors": 2,
     *     "errors": [
     *       {"row": 12, "message": "Product SKU 'XYZ-123' not found"}
     *     ]
     *   }
     * }
     * @response 422 {"message": "No valid rows to import"}
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'skip_errors' => 'nullable|boolean',
        ]);

        // First validate
        $validateImport = new ComponentMappingImport(validateOnly: true);
        Excel::import($validateImport, $request->file('file'));
        $validationResults = $validateImport->getValidationResults();

        if ($validationResults['valid_count'] === 0) {
            return response()->json([
                'message' => 'Tidak ada data valid untuk diimport.',
                'errors' => $validationResults['errors'],
            ], 422);
        }

        // Now actually import
        $import = new ComponentMappingImport(validateOnly: false);
        Excel::import($import, $request->file('file'));
        $results = $import->getImportResults();

        return response()->json([
            'message' => 'Import berhasil.',
            'data' => [
                'created_standards' => $results['created_standards'],
                'created_mappings' => $results['created_mappings'],
                'updated_mappings' => $results['updated_mappings'],
                'skipped_errors' => count($results['errors']),
                'errors' => array_slice($results['errors'], 0, 10),
            ],
        ], 201);
    }

    /**
     * Get import statistics.
     *
     * Returns current statistics about component mappings in the system.
     *
     * @response 200 {
     *   "data": {
     *     "total_standards": 150,
     *     "total_mappings": 450,
     *     "products_with_mapping": 380,
     *     "products_without_mapping": 120,
     *     "coverage_percentage": 76,
     *     "brands": {
     *       "schneider": 120,
     *       "abb": 110,
     *       "chint": 100
     *     }
     *   }
     * }
     */
    public function stats(): JsonResponse
    {
        $totalStandards = \App\Models\ElectricalPanel\ComponentStandard::count();
        $totalMappings = \App\Models\ElectricalPanel\ComponentBrandMapping::count();

        $productsWithMapping = \App\Models\ElectricalPanel\ComponentBrandMapping::distinct('product_id')->count();
        $totalProducts = \App\Models\Inventory\Product::count();
        $productsWithoutMapping = $totalProducts - $productsWithMapping;

        $coveragePercentage = $totalProducts > 0
            ? round(($productsWithMapping / $totalProducts) * 100)
            : 0;

        $brandCounts = \App\Models\ElectricalPanel\ComponentBrandMapping::query()
            ->selectRaw('brand, COUNT(*) as count')
            ->groupBy('brand')
            ->pluck('count', 'brand')
            ->toArray();

        return response()->json([
            'data' => [
                'total_standards' => $totalStandards,
                'total_mappings' => $totalMappings,
                'products_with_mapping' => $productsWithMapping,
                'products_without_mapping' => $productsWithoutMapping,
                'coverage_percentage' => $coveragePercentage,
                'brands' => $brandCounts,
            ],
        ]);
    }
}
