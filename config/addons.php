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
        'http_namespace' => 'App\\Http\\Controllers\\Api\\V1\\ElectricalPanel',
        'export_namespace' => 'App\\Exports\\ElectricalPanel',
        'routes' => 'routes/addons/electrical_panel.php',
        /**
         * Extension data lives in add-on meta tables (not core manufacturing columns).
         * Core bom_items / boms / bom_templates have no panel FKs after Fase A.
         */
        'meta_tables' => [
            'electrical_panel_bom_item_meta',
            'electrical_panel_bom_meta',
            'electrical_panel_bom_template_item_meta',
            'electrical_panel_bom_template_meta',
        ],
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
        'http_namespace' => 'App\\Http\\Controllers\\Api\\V1\\Solar',
        'export_namespace' => 'App\\Exports\\Solar',
        'routes' => 'routes/addons/solar.php',
        'morph_aliases' => [
            'solar_proposal' => 'App\\Models\\Solar\\SolarProposal',
        ],
    ],

];
