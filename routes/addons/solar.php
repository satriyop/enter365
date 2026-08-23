<?php

/**
 * Solar EPC industry add-on routes (NEX).
 * Public routes: call from public section. Auth routes: call from auth section.
 *
 * Expected: $solarRouteContext = 'public' | 'auth'
 *
 * @see App\Providers\Addons\SolarServiceProvider
 */

use App\Http\Controllers\Api\Solar\PublicSolarCalculatorController;
use App\Http\Controllers\Api\Solar\PublicSolarProposalController;
use App\Http\Controllers\Api\V1\Solar\SolarDataController;
use App\Http\Controllers\Api\V1\Solar\SolarProposalController;
use Illuminate\Support\Facades\Route;

$context = $solarRouteContext ?? 'auth';

if ($context === 'public') {
    Route::middleware('feature:solar_proposals')->prefix('public/solar-proposals')->group(function () {
        Route::get('{token}', [PublicSolarProposalController::class, 'show']);
        Route::post('{token}/accept', [PublicSolarProposalController::class, 'accept']);
        Route::post('{token}/reject', [PublicSolarProposalController::class, 'reject']);
    })->where(['token' => '[0-9a-f\-]{32,36}']);

    Route::middleware('feature:solar_proposals')->prefix('public/solar-calculator')->group(function () {
        Route::post('calculate', [PublicSolarCalculatorController::class, 'calculate']);
        Route::get('tariffs', [PublicSolarCalculatorController::class, 'tariffs']);
    });

    return;
}

Route::middleware(['feature:solar_proposals', 'permission:quotations.view'])->group(function () {
    Route::apiResource('solar-proposals', SolarProposalController::class)
        ->parameters(['solar-proposals' => 'solarProposal']);
    Route::post('solar-proposals/{solarProposal}/calculate', [SolarProposalController::class, 'calculate']);
    Route::post('solar-proposals/{solarProposal}/attach-variants', [SolarProposalController::class, 'attachVariants']);
    Route::post('solar-proposals/{solarProposal}/select-bom', [SolarProposalController::class, 'selectBom']);
    Route::post('solar-proposals/{solarProposal}/send', [SolarProposalController::class, 'send']);
    Route::post('solar-proposals/{solarProposal}/accept', [SolarProposalController::class, 'accept']);
    Route::post('solar-proposals/{solarProposal}/reject', [SolarProposalController::class, 'reject']);
    Route::post('solar-proposals/{solarProposal}/convert-to-quotation', [SolarProposalController::class, 'convertToQuotation']);
    Route::get('solar-proposals/{solarProposal}/pdf', [SolarProposalController::class, 'pdf']);
    Route::get('solar-proposals/{solarProposal}/excel', [SolarProposalController::class, 'excel']);
    Route::get('solar-proposals-statistics', [SolarProposalController::class, 'statistics']);

    Route::prefix('solar-data')->group(function () {
        Route::get('lookup', [SolarDataController::class, 'lookup']);
        Route::get('provinces', [SolarDataController::class, 'provinces']);
        Route::get('cities', [SolarDataController::class, 'cities']);
        Route::get('locations', [SolarDataController::class, 'locations']);
    });

    Route::prefix('pln-tariffs')->group(function () {
        Route::get('/', [SolarDataController::class, 'tariffs']);
        Route::get('grouped', [SolarDataController::class, 'tariffsGrouped']);
        Route::get('{code}', [SolarDataController::class, 'tariffByCode']);
    });
});
