<?php

namespace App\Providers;

use App\Contracts\FeatureManager;
use App\Support\ConfigFeatureManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FeatureManager::class, ConfigFeatureManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
