<?php

use Dedoc\Scramble\Support\Generator\OpenApi;

return [
    /*
    |--------------------------------------------------------------------------
    | API Path
    |--------------------------------------------------------------------------
    |
    | The path prefix for your API routes. Scramble will only document
    | routes that start with this prefix.
    |
    */
    'api_path' => 'api/v1',

    /*
    |--------------------------------------------------------------------------
    | API Domain
    |--------------------------------------------------------------------------
    |
    | The domain for your API. Leave null to use the current domain.
    |
    */
    'api_domain' => null,

    /*
    |--------------------------------------------------------------------------
    | Export Path
    |--------------------------------------------------------------------------
    |
    | The filename for the exported OpenAPI specification.
    |
    */
    'export_path' => 'api.json',

    /*
    |--------------------------------------------------------------------------
    | API Info
    |--------------------------------------------------------------------------
    |
    | The information about your API that will be displayed in the docs.
    |
    */
    'info' => [
        'title' => env('APP_NAME', 'Enter365').' API',
        'version' => '1.0.0',
        'description' => 'Indonesian Accounting System API - SAK EMKM Compliant. Provides comprehensive endpoints for managing accounts, contacts, invoices, bills, inventory, projects, and financial reports.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Servers
    |--------------------------------------------------------------------------
    |
    | The servers where your API is hosted. Leave null to use APP_URL.
    |
    */
    'servers' => null,

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | The middleware to apply to the documentation routes.
    |
    */
    'middleware' => [
        'web',
    ],

    /*
    |--------------------------------------------------------------------------
    | Extensions
    |--------------------------------------------------------------------------
    |
    | Custom OpenAPI extensions to add to the specification.
    |
    */
    'extensions' => [],
];
