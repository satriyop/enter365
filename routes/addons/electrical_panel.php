<?php

/**
 * Electrical panel industry add-on routes (Vahana).
 * Loaded inside feature:bom group; additionally gated by feature:electrical_panel.
 *
 * @see App\Providers\Addons\ElectricalPanelServiceProvider
 */

use App\Http\Controllers\Api\V1\ElectricalPanel\ComponentBrandMappingController;
use App\Http\Controllers\Api\V1\ElectricalPanel\ComponentCrossReferenceController;
use App\Http\Controllers\Api\V1\ElectricalPanel\ComponentMappingImportController;
use App\Http\Controllers\Api\V1\ElectricalPanel\ComponentStandardController;
use App\Http\Controllers\Api\V1\ElectricalPanel\SpecValidationRuleSetController;
use Illuminate\Support\Facades\Route;

Route::middleware('feature:electrical_panel')->group(function () {
    Route::prefix('component-standards')->group(function () {
        Route::get('/', [ComponentStandardController::class, 'index']);
        Route::post('/', [ComponentStandardController::class, 'store']);
        Route::get('/categories', [ComponentStandardController::class, 'categories']);
        Route::get('/{componentStandard}', [ComponentStandardController::class, 'show']);
        Route::put('/{componentStandard}', [ComponentStandardController::class, 'update']);
        Route::delete('/{componentStandard}', [ComponentStandardController::class, 'destroy']);
        Route::get('/{componentStandard}/brands', [ComponentStandardController::class, 'brands']);

        Route::post('/{componentStandard}/mappings', [ComponentBrandMappingController::class, 'store']);
        Route::put('/{componentStandard}/mappings/{mapping}', [ComponentBrandMappingController::class, 'update']);
        Route::delete('/{componentStandard}/mappings/{mapping}', [ComponentBrandMappingController::class, 'destroy']);
        Route::post('/{componentStandard}/mappings/{mapping}/verify', [ComponentBrandMappingController::class, 'verify']);
        Route::post('/{componentStandard}/mappings/{mapping}/set-preferred', [ComponentBrandMappingController::class, 'setPreferred']);
    });

    Route::get('products/{product}/equivalents', [ComponentCrossReferenceController::class, 'productEquivalents']);
    Route::get('component-search', [ComponentCrossReferenceController::class, 'search']);
    Route::get('available-brands', [ComponentCrossReferenceController::class, 'availableBrands']);

    Route::get('boms/{bom}/brand-comparison', [ComponentCrossReferenceController::class, 'compareBrands']);
    Route::post('boms/{bom}/swap-brand-preview', [ComponentCrossReferenceController::class, 'previewSwapBrand']);
    Route::post('boms/{bom}/swap-brand', [ComponentCrossReferenceController::class, 'swapBrand']);
    Route::post('boms/{bom}/generate-brand-variants', [ComponentCrossReferenceController::class, 'generateBrandVariants']);

    Route::get('boms/{bom}/cost-optimization', [ComponentCrossReferenceController::class, 'previewCostOptimization']);
    Route::post('boms/{bom}/apply-cost-optimization', [ComponentCrossReferenceController::class, 'applyCostOptimization']);

    Route::get('boms/{bom}/items/{item}/alternatives', [ComponentCrossReferenceController::class, 'getItemAlternatives']);
    Route::patch('boms/{bom}/items/{item}/swap', [ComponentCrossReferenceController::class, 'quickSwapItem']);

    Route::get('auto-mapping/unmapped-products', [ComponentCrossReferenceController::class, 'getUnmappedProducts']);
    Route::get('auto-mapping/products/{product}/suggest', [ComponentCrossReferenceController::class, 'suggestMapping']);
    Route::post('auto-mapping/suggest-batch', [ComponentCrossReferenceController::class, 'suggestMappingsBatch']);
    Route::post('auto-mapping/products/{product}/accept', [ComponentCrossReferenceController::class, 'acceptSuggestion']);
    Route::post('auto-mapping/bulk-accept', [ComponentCrossReferenceController::class, 'bulkAcceptSuggestions']);
    Route::get('auto-mapping/parse-name', [ComponentCrossReferenceController::class, 'parseProductName']);

    Route::get('component-mappings/template', [ComponentMappingImportController::class, 'downloadTemplate']);
    Route::post('component-mappings/validate', [ComponentMappingImportController::class, 'validate']);
    Route::post('component-mappings/import', [ComponentMappingImportController::class, 'import']);
    Route::get('component-mappings/stats', [ComponentMappingImportController::class, 'stats']);

    Route::prefix('spec-rule-sets')->group(function () {
        Route::get('/', [SpecValidationRuleSetController::class, 'index']);
        Route::post('/', [SpecValidationRuleSetController::class, 'store']);
        Route::get('/metadata', [SpecValidationRuleSetController::class, 'metadata']);
        Route::get('/{specRuleSet}', [SpecValidationRuleSetController::class, 'show']);
        Route::put('/{specRuleSet}', [SpecValidationRuleSetController::class, 'update']);
        Route::delete('/{specRuleSet}', [SpecValidationRuleSetController::class, 'destroy']);
        Route::post('/{specRuleSet}/set-default', [SpecValidationRuleSetController::class, 'setDefault']);

        Route::post('/{specRuleSet}/rules', [SpecValidationRuleSetController::class, 'storeRule']);
        Route::put('/{specRuleSet}/rules/{rule}', [SpecValidationRuleSetController::class, 'updateRule']);
        Route::delete('/{specRuleSet}/rules/{rule}', [SpecValidationRuleSetController::class, 'destroyRule']);
        Route::post('/{specRuleSet}/rules/reorder', [SpecValidationRuleSetController::class, 'reorderRules']);
    });
});
