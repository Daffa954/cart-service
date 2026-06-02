<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CORS Configuration — Cart Service
    |--------------------------------------------------------------------------
    |
    | In production, the API Gateway handles CORS. This config is for
    | local development where the React frontend calls the service directly.
    |
    */

    'paths' => ['api/*', 'up'],

    'allowed_methods' => ['GET', 'POST', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173')),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With'],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => false,
];
