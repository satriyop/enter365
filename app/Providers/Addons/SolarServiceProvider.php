<?php

namespace App\Providers\Addons;

use App\Contracts\Solar\SolarCalculationServiceInterface;
use App\Contracts\Solar\SolarProposalServiceInterface;
use App\Services\Solar\SolarCalculationService;
use App\Services\Solar\SolarProposalService;
use App\Support\Features;
use Illuminate\Support\ServiceProvider;

/**
 * Industry add-on: solar EPC (NEX).
 *
 * Bindings only register when solar_proposals is enabled so core boot
 * does not depend on solar services for generic ERP deploys.
 * Morph map alias stays in AppServiceProvider (model may exist offline).
 */
class SolarServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! Features::enabled('solar_proposals')) {
            return;
        }

        $this->app->bind(SolarProposalServiceInterface::class, SolarProposalService::class);
        $this->app->bind(SolarCalculationServiceInterface::class, SolarCalculationService::class);
    }

    public function boot(): void
    {
        // Routes gated by feature:solar_proposals middleware in routes/api.php
    }

    public static function isEnabled(): bool
    {
        return Features::enabled('solar_proposals');
    }
}
