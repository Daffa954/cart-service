<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Inter-Service URLs
    |--------------------------------------------------------------------------
    |
    | All inter-service communication goes through the API Gateway.
    | These URLs are consumed by CartService for product validation,
    | promo validation, and checkout (forwarding to Order Service).
    |
    */

    'auth_service' => [
        'url' => env('AUTH_SERVICE_URL', 'http://localhost:2000/auth'),
    ],

    'product_service' => [
        'url' => env('PRODUCT_SERVICE_URL', 'http://localhost:2000/products'),
    ],

    'order_service' => [
        'url' => env('ORDER_SERVICE_URL', 'http://localhost:2000/orders'),
    ],
];
