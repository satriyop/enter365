<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Response Validation
    |--------------------------------------------------------------------------
    |
    | Configure API response validation against OpenAPI schema.
    |
    */

    'response_validation' => [
        /*
         * Enable response validation middleware.
         *
         * When enabled, all API responses are validated against api.json schema.
         * Validation failures are logged but don't block responses (unless strict mode is enabled).
         */
        'enabled' => env('API_RESPONSE_VALIDATION_ENABLED', false),

        /*
         * Strict mode - throw exceptions on validation failures.
         *
         * When enabled, validation failures will throw exceptions instead of just logging.
         * Use with caution - only enable in development/testing environments.
         */
        'strict' => env('API_RESPONSE_VALIDATION_STRICT', false),
    ],
];
