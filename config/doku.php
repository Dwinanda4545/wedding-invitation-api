<?php

return [
    'client_id' => env('DOKU_CLIENT_ID'),
    'secret_key' => env('DOKU_SECRET_KEY'),
    'sandbox' => env('DOKU_SANDBOX', true),
    'base_url' => env('DOKU_SANDBOX', true)
        ? 'https://api-sandbox.doku.com'
        : 'https://api.doku.com',
    // Path used as Request-Target when verifying HTTP notifications.
    'notification_path' => env('DOKU_NOTIFICATION_PATH', '/api/doku/notification'),
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
];
