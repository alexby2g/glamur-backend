<?php

$allowedOrigins = array_values(array_filter(array_map(
    'trim',
    explode(',', env('CORS_ALLOWED_ORIGINS', 'https://glamur-frontend.vercel.app'))
)));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'Origin', 'X-Auth-Token'],

    'exposed_headers' => [],

    'max_age' => 600,

    'supports_credentials' => false,
];
