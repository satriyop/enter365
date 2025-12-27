<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Feature Modules
    |--------------------------------------------------------------------------
    |
    | Enable or disable application modules. All modules default to enabled
    | for backward compatibility. Set to false in .env to disable a module.
    |
    | Example: FEATURE_MRP=false
    |
    */

    'modules' => [
        // Sales & Receivables
        'products' => env('FEATURE_PRODUCTS', true),
        'quotations' => env('FEATURE_QUOTATIONS', true),
        'delivery_orders' => env('FEATURE_DELIVERY_ORDERS', true),
        'sales_returns' => env('FEATURE_SALES_RETURNS', true),
        'down_payments' => env('FEATURE_DOWN_PAYMENTS', true),

        // Purchasing & Payables
        'purchase_orders' => env('FEATURE_PURCHASE_ORDERS', true),
        'goods_receipt_notes' => env('FEATURE_GRN', true),
        'purchase_returns' => env('FEATURE_PURCHASE_RETURNS', true),

        // Inventory
        'inventory' => env('FEATURE_INVENTORY', true),
        'stock_opname' => env('FEATURE_STOCK_OPNAME', true),
        'warehouses' => env('FEATURE_WAREHOUSES', true),

        // Manufacturing
        'manufacturing' => env('FEATURE_MANUFACTURING', true),
        'bom' => env('FEATURE_BOM', true),
        'work_orders' => env('FEATURE_WORK_ORDERS', true),
        'material_requisitions' => env('FEATURE_MATERIAL_REQUISITIONS', true),
        'mrp' => env('FEATURE_MRP', true),
        'subcontracting' => env('FEATURE_SUBCONTRACTING', true),

        // Project Management
        'projects' => env('FEATURE_PROJECTS', true),

        // Financial
        'budgeting' => env('FEATURE_BUDGETING', true),
        'recurring' => env('FEATURE_RECURRING', true),
        'multi_currency' => env('FEATURE_MULTI_CURRENCY', true),
        'bank_reconciliation' => env('FEATURE_BANK_RECONCILIATION', true),
    ],
];
