<?php

$defaultOrigins = 'http://localhost:5173,http://127.0.0.1:5173';

$allowedOrigins = array_values(array_filter(array_map(
    static fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', $defaultOrigins)),
)));

return [
    // broadcasting/auth required for Laravel Echo private channels (Pusher guestbook).
    // Without it, production SPA (different subdomain) cannot authorize subscriptions.
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout', 'broadcasting/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $allowedOrigins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
