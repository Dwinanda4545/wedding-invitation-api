<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login'], // Add 'login' here
    'allowed_methods' => ['*'],
    'allowed_origins' => ['http://localhost:5173'], // Your Vite URL
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true, // THIS MUST BE TRUE
];

