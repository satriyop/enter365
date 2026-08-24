<?php

declare(strict_types=1);

$preset = strtolower((string) env('FEATURE_PRESET', 'general'));
$cafeAddOn = $preset === 'pos';

return [
    /*
    |--------------------------------------------------------------------------
    | Till pricing
    |--------------------------------------------------------------------------
    |
    | inclusive — tile is the amount paid; PPN extracted per line (ADR-0056).
    | add — tile is menu (Harga Cafe); session adds service then PBJT on the bill.
    |
    */
    'pricing_mode' => env('POS_PRICING_MODE', $cafeAddOn ? 'add' : 'inclusive'),
    'service_rate' => (float) env('POS_SERVICE_RATE', $cafeAddOn ? 5 : 0),
    'tax_rate' => (float) env('POS_TAX_RATE', $cafeAddOn ? 10 : 0),
    'tax_name' => env('POS_TAX_NAME', 'PBJT'),
];
