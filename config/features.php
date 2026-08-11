<?php

/**
 * Product module flags — Odoo-like core + optional packs + industry add-ons.
 *
 * Layers:
 *   1. core_erp     — Sales / Purchase / Inventory / accounting tools (default ON)
 *   2. odoo_packs   — Manufacturing, Projects (optional apps, not verticals)
 *   3. industry     — NEX (solar) / Vahana (electrical panel) add-ons (default OFF)
 *
 * Presets (FEATURE_PRESET):
 *   general       — trading / jasa ringan (core only)
 *   services      — core + projects
 *   manufacturing — core + manufacturing packs (generic shop floor)
 *   enterprise    — core + manufacturing + projects (Odoo Enterprise–like pitch)
 *   solar | nex   — solar EPC add-on (+ projects, light BOM)
 *   vahana        — manufacturing + electrical_panel add-on
 *   full          — everything (demo / tests)
 *
 * Explicit FEATURE_* env always overrides preset defaults when set.
 *
 * @see App\Support\ConfigFeatureManager
 * @see App\Http\Middleware\EnsureFeatureEnabled
 * @see tasks/artifact/odoo-enterprise-enter365-mapping.md
 */
$preset = strtolower((string) env('FEATURE_PRESET', 'general'));

// Alias product names → internal preset keys
$preset = match ($preset) {
    'nex' => 'solar',
    default => $preset,
};

/** @var array<string, bool> $core Always-on SME ERP modules */
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
    // Accounting power tools (Odoo-like, not industry-specific)
    'budgeting' => env('FEATURE_BUDGETING', true),
    'recurring' => env('FEATURE_RECURRING', true),
    'multi_currency' => env('FEATURE_MULTI_CURRENCY', true),
    'bank_reconciliation' => env('FEATURE_BANK_RECONCILIATION', true),
    // Tax pack-ID (opt-in)
    'pph_withholding' => env('FEATURE_PPH_WITHHOLDING', false),
];

/**
 * Odoo-like optional packs + industry add-ons.
 * Keys match middleware feature:{name}.
 *
 * @return array{
 *     manufacturing: bool,
 *     bom: bool,
 *     work_orders: bool,
 *     material_requisitions: bool,
 *     mrp: bool,
 *     subcontracting: bool,
 *     projects: bool,
 *     solar_proposals: bool,
 *     electrical_panel: bool
 * }
 */
$packDefaults = match ($preset) {
    'full' => [
        'manufacturing' => true,
        'bom' => true,
        'work_orders' => true,
        'material_requisitions' => true,
        'mrp' => true,
        'subcontracting' => true,
        'projects' => true,
        'solar_proposals' => true,
        'electrical_panel' => true,
    ],
    'enterprise' => [
        // Odoo Enterprise–like suite (no industry verticals)
        'manufacturing' => true,
        'bom' => true,
        'work_orders' => true,
        'material_requisitions' => true,
        'mrp' => true,
        'subcontracting' => true,
        'projects' => true,
        'solar_proposals' => false,
        'electrical_panel' => false,
    ],
    'manufacturing' => [
        // Generic shop floor / factory (not Vahana-specific)
        'manufacturing' => true,
        'bom' => true,
        'work_orders' => true,
        'material_requisitions' => true,
        'mrp' => true,
        'subcontracting' => true,
        'projects' => false,
        'solar_proposals' => false,
        'electrical_panel' => false,
    ],
    'vahana' => [
        // Panel electrical company: manufacturing + brand/spec add-on
        'manufacturing' => true,
        'bom' => true,
        'work_orders' => true,
        'material_requisitions' => true,
        'mrp' => true,
        'subcontracting' => true,
        'projects' => false,
        'solar_proposals' => false,
        'electrical_panel' => true,
    ],
    'services' => [
        'manufacturing' => false,
        'bom' => false,
        'work_orders' => false,
        'material_requisitions' => false,
        'mrp' => false,
        'subcontracting' => false,
        'projects' => true,
        'solar_proposals' => false,
        'electrical_panel' => false,
    ],
    'solar' => [
        // NEX-style solar EPC
        'manufacturing' => false,
        'bom' => true, // proposals often attach BOM variants
        'work_orders' => false,
        'material_requisitions' => false,
        'mrp' => false,
        'subcontracting' => false,
        'projects' => true,
        'solar_proposals' => true,
        'electrical_panel' => false,
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
        'electrical_panel' => false,
    ],
};

// Explicit FEATURE_* env overrides preset defaults
$packs = [
    'manufacturing' => filter_var(env('FEATURE_MANUFACTURING', $packDefaults['manufacturing']), FILTER_VALIDATE_BOOLEAN),
    'bom' => filter_var(env('FEATURE_BOM', $packDefaults['bom']), FILTER_VALIDATE_BOOLEAN),
    'work_orders' => filter_var(env('FEATURE_WORK_ORDERS', $packDefaults['work_orders']), FILTER_VALIDATE_BOOLEAN),
    'material_requisitions' => filter_var(env('FEATURE_MATERIAL_REQUISITIONS', $packDefaults['material_requisitions']), FILTER_VALIDATE_BOOLEAN),
    'mrp' => filter_var(env('FEATURE_MRP', $packDefaults['mrp']), FILTER_VALIDATE_BOOLEAN),
    'subcontracting' => filter_var(env('FEATURE_SUBCONTRACTING', $packDefaults['subcontracting']), FILTER_VALIDATE_BOOLEAN),
    'projects' => filter_var(env('FEATURE_PROJECTS', $packDefaults['projects']), FILTER_VALIDATE_BOOLEAN),
    // Industry add-ons
    'solar_proposals' => filter_var(env('FEATURE_SOLAR_PROPOSALS', $packDefaults['solar_proposals']), FILTER_VALIDATE_BOOLEAN),
    'electrical_panel' => filter_var(env('FEATURE_ELECTRICAL_PANEL', $packDefaults['electrical_panel']), FILTER_VALIDATE_BOOLEAN),
];

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
    | Frontend loads GET /api/v1/features and hides nav accordingly.
    |
    */

    'modules' => array_merge($core, $packs),
];
