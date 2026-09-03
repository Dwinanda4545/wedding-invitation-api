<?php

return [
    'merchant_code' => env('DUITKU_MERCHANT_CODE'),
    'api_key' => env('DUITKU_API_KEY'),
    'sandbox' => env('DUITKU_SANDBOX', true),
    'base_url' => env('DUITKU_SANDBOX', true)
        ? 'https://sandbox.duitku.com'
        : 'https://passport.duitku.com',
    'callback_url' => env('DUITKU_CALLBACK_URL'),
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
];
