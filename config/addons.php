<?php

/**
 * Industry add-on boundaries (not core ERP modules).
 *
 * Core: Sales, Purchase, Inventory, Accounting, generic Manufacturing, Projects.
 * Add-ons: electrical_panel (Vahana), solar_proposals (NEX).
 *
 * Enable via FEATURE_PRESET / FEATURE_* — see config/features.php.
 *
 * @see App\Providers\Addons\ElectricalPanelServiceProvider
 * @see App\Providers\Addons\SolarServiceProvider
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Electrical panel (Vahana)
    |--------------------------------------------------------------------------
    |
    | Brand swap, component standards, spec validation, cost optimization.
    | Service namespace: App\Services\ElectricalPanel
    | Model namespace:   App\Models\ElectricalPanel
    | Flag:             features.modules.electrical_panel
    |
    */

    'electrical_panel' => [
        'feature' => 'electrical_panel',
        'label' => 'Electrical Panel Tools',
        'service_namespace' => 'App\\Services\\ElectricalPanel',
        'model_namespace' => 'App\\Models\\ElectricalPanel',
    ],

    /*
    |--------------------------------------------------------------------------
    | Solar EPC (NEX)
    |--------------------------------------------------------------------------
    |
    | Solar proposals, calculation, PLN / irradiance masters, public calculator.
    | Service namespace: App\Services\Solar
    | Model namespace:   App\Models\Solar
    | Flag:             features.modules.solar_proposals
    |
    */

    'solar' => [
        'feature' => 'solar_proposals',
        'label' => 'Solar Proposals',
        'service_namespace' => 'App\\Services\\Solar',
        'model_namespace' => 'App\\Models\\Solar',
    ],

];
