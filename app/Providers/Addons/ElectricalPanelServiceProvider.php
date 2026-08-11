<?php

namespace App\Providers\Addons;

use App\Support\Features;
use Illuminate\Support\ServiceProvider;

/**
 * Industry add-on: electrical panel tools (Vahana).
 *
 * Core Manufacturing must not depend on these services for generic BOM/WO/MRP.
 * HTTP routes remain behind feature:electrical_panel middleware.
 * Models live under App\Models\ElectricalPanel; services under App\Services\ElectricalPanel.
 */
class ElectricalPanelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->instance(
            'addon.electrical_panel.enabled',
            fn (): bool => Features::enabled('electrical_panel')
        );
    }

    public function boot(): void
    {
        // Concrete services are auto-resolved when controllers run (routes gated).
        // Morph / optional tables remain; no core boot dependency.
    }

    public static function isEnabled(): bool
    {
        return Features::enabled('electrical_panel');
    }
}
