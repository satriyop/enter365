<?php

namespace App\Providers\Addons;

use App\Contracts\Solar\SolarCalculationServiceInterface;
use App\Contracts\Solar\SolarProposalServiceInterface;
use App\Models\Solar\SolarProposal;
use App\Services\Solar\SolarCalculationService;
use App\Services\Solar\SolarProposalService;
use App\Support\Features;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

/**
 * Industry add-on: solar EPC (NEX).
 *
 * Owns solar DI bindings and morph-map alias. Core AppServiceProvider has no solar surface.
 */
class SolarServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Per-resolve flag check so withFeatures() works after boot.
        $this->app->bind(SolarProposalServiceInterface::class, function ($app) {
            $this->guardEnabled();

            return $app->make(SolarProposalService::class);
        });

        $this->app->bind(SolarCalculationServiceInterface::class, function ($app) {
            $this->guardEnabled();

            return $app->make(SolarCalculationService::class);
        });
    }

    public function boot(): void
    {
        // Morph alias owned by this add-on (merged into global map).
        Relation::morphMap([
            'solar_proposal' => SolarProposal::class,
        ]);
    }

    public static function isEnabled(): bool
    {
        return Features::enabled('solar_proposals');
    }

    private function guardEnabled(): void
    {
        if (! Features::enabled('solar_proposals')) {
            throw new BindingResolutionException(
                'Solar add-on is disabled (set FEATURE_SOLAR_PROPOSALS=true or FEATURE_PRESET=solar|nex|full).'
            );
        }
    }
}
