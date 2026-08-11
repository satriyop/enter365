<?php

/**
 * Product module flags.
 *
 * Default profile: **general company** (trading / jasa / UKM end-to-end).
 * Vertical packs used heavily by Vahana (panel/manufacturing) and NEX (solar EPC)
 * are OFF by default and can be enabled per deploy via .env.
 *
 * Enable packs when needed, e.g.:
 *   FEATURE_SOLAR_PROPOSALS=true
 *   FEATURE_MANUFACTURING=true FEATURE_BOM=true FEATURE_WORK_ORDERS=true ...
 *   FEATURE_PROJECTS=true
 *
 * Or turn everything on for full demo (NEX+Vahana+general):
 *   FEATURE_PRESET=full
 *
 * @see App\Support\ConfigFeatureManager
 * @see App\Http\Middleware\EnsureFeatureEnabled
 */
$preset = env('FEATURE_PRESET', 'general'); // general | manufacturing | solar | full

/** @var array<string, bool> $core Always-on for general SME ERP */
$core = [
    'products' => env('FEATURE_PRODUCTS', true),
    'quotations' => env('FEATURE_QUOTATIONS', true),
    'delivery_orders' => env('FEATURE_DELIVERY_ORDERS', true),
    'sales_returns' => env('FEATURE_SALES_RETURNS', true),
    'down_payments' => env('FEATURE_DOWN_PAYMENTS', true),
    'purchase_orders' => env('FEATURE_PURCHASE_ORDERS', true),
    'goods_receipt_notes' => env('FEATURE_GRN', true),
    'purchase_returns' => env('FEATURE_PURCHASE_RETURNS', true),
    'inventory' => env('FEATURE_INVENTORY', true),
    'stock_opname' => env('FEATURE_STOCK_OPNAME', true),
    'warehouses' => env('FEATURE_WAREHOUSES', true),
    // General accounting power tools (not NEX/Vahana-specific)
    'budgeting' => env('FEATURE_BUDGETING', true),
    'recurring' => env('FEATURE_RECURRING', true),
    'multi_currency' => env('FEATURE_MULTI_CURRENCY', true),
    'bank_reconciliation' => env('FEATURE_BANK_RECONCILIATION', true),
    // Tax: opt-in
    'pph_withholding' => env('FEATURE_PPH_WITHHOLDING', false),
];

/**
 * Vertical / specialist packs — default OFF for general company.
 * Individual FEATURE_* env always wins when set.
 */
$verticalDefaults = match ($preset) {
    'full' => [
        'manufacturing' => true,
        'bom' => true,
        'work_orders' => true,
        'material_requisitions' => true,
        'mrp' => true,
        'subcontracting' => true,
        'projects' => true,
        'solar_proposals' => true,
    ],
    'manufacturing' => [
        // Vahana-style panel / shop floor
        'manufacturing' => true,
        'bom' => true,
        'work_orders' => true,
        'material_requisitions' => true,
        'mrp' => true,
        'subcontracting' => true,
        'projects' => env('FEATURE_PROJECTS', false),
        'solar_proposals' => false,
    ],
    'solar' => [
        // NEX-style solar EPC
        'manufacturing' => env('FEATURE_MANUFACTURING', false),
        'bom' => env('FEATURE_BOM', true), // proposals often attach BOM variants
        'work_orders' => env('FEATURE_WORK_ORDERS', false),
        'material_requisitions' => env('FEATURE_MATERIAL_REQUISITIONS', false),
        'mrp' => env('FEATURE_MRP', false),
        'subcontracting' => env('FEATURE_SUBCONTRACTING', false),
        'projects' => env('FEATURE_PROJECTS', true),
        'solar_proposals' => true,
    ],
    default => [ // general
        'manufacturing' => false,
        'bom' => false,
        'work_orders' => false,
        'material_requisitions' => false,
        'mrp' => false,
        'subcontracting' => false,
        'projects' => false,
        'solar_proposals' => false,
    ],
};

// Explicit FEATURE_* env overrides preset defaults (null env → keep preset bool)
$vertical = [
    'manufacturing' => filter_var(env('FEATURE_MANUFACTURING', $verticalDefaults['manufacturing']), FILTER_VALIDATE_BOOLEAN),
    'bom' => filter_var(env('FEATURE_BOM', $verticalDefaults['bom']), FILTER_VALIDATE_BOOLEAN),
    'work_orders' => filter_var(env('FEATURE_WORK_ORDERS', $verticalDefaults['work_orders']), FILTER_VALIDATE_BOOLEAN),
    'material_requisitions' => filter_var(env('FEATURE_MATERIAL_REQUISITIONS', $verticalDefaults['material_requisitions']), FILTER_VALIDATE_BOOLEAN),
    'mrp' => filter_var(env('FEATURE_MRP', $verticalDefaults['mrp']), FILTER_VALIDATE_BOOLEAN),
    'subcontracting' => filter_var(env('FEATURE_SUBCONTRACTING', $verticalDefaults['subcontracting']), FILTER_VALIDATE_BOOLEAN),
    'projects' => filter_var(env('FEATURE_PROJECTS', $verticalDefaults['projects']), FILTER_VALIDATE_BOOLEAN),
    'solar_proposals' => filter_var(env('FEATURE_SOLAR_PROPOSALS', $verticalDefaults['solar_proposals']), FILTER_VALIDATE_BOOLEAN),
];

// Core: env may be string "false" — normalize bools for consistency
foreach ($core as $key => $value) {
    $core[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

return [
    'preset' => $preset,

    /*
    |--------------------------------------------------------------------------
    | Feature Modules
    |--------------------------------------------------------------------------
    |
    | Toggle domain modules. Routes use middleware feature:{name}.
    | Frontend should load GET /api/v1/features and hide nav accordingly.
    |
    */

    'modules' => array_merge($core, $vertical),
];
