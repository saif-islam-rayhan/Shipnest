<?php

return [
    'sslcommerz' => [
        'store_id' => env('SSLCOMMERZ_STORE_ID'),
        'store_password' => env('SSLCOMMERZ_STORE_PASSWORD'),
        'api_url' => env('SSLCOMMERZ_API_URL', 'https://sandbox.sslcommerz.com'),
        'sandbox' => env('SSLCOMMERZ_SANDBOX', true),
    ],

    'bkash' => [
        'app_key' => env('BKASH_APP_KEY'),
        'app_secret' => env('BKASH_APP_SECRET'),
        'username' => env('BKASH_USERNAME'),
        'password' => env('BKASH_PASSWORD'),
        'base_url' => env('BKASH_BASE_URL', 'https://tokenized.sandbox.bka.sh/v1.2.0-beta'),
        'sandbox' => env('BKASH_SANDBOX', true),
    ],

    'nagad' => [
        'merchant_id' => env('NAGAD_MERCHANT_ID'),
        'merchant_number' => env('NAGAD_MERCHANT_NUMBER'),
        'public_key' => env('NAGAD_PUBLIC_KEY'),
        'private_key' => env('NAGAD_PRIVATE_KEY'),
        'challenge' => env('NAGAD_CHALLENGE'),
        'base_url' => env('NAGAD_BASE_URL', 'https://api.mynagad.com/api/dfs'),
        'sandbox' => env('NAGAD_SANDBOX', true),
    ],
];
