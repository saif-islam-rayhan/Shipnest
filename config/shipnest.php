<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform Identity
    |--------------------------------------------------------------------------
    */

    'name' => env('SHIPNEST_NAME', env('APP_NAME', 'ShipNest')),

    /*
    |--------------------------------------------------------------------------
    | Currency & Pricing
    |--------------------------------------------------------------------------
    */

    'currency' => env('SHIPNEST_CURRENCY', 'BDT'),
    'currency_symbol' => env('SHIPNEST_CURRENCY_SYMBOL', '৳'),
    'free_shipping_threshold' => (float) env('SHIPNEST_FREE_SHIPPING_THRESHOLD', 500),

    /*
    |--------------------------------------------------------------------------
    | Commission
    |--------------------------------------------------------------------------
    */

    'commission_rate' => (float) env('SHIPNEST_COMMISSION_RATE', env('SHIPNEST_DEFAULT_COMMISSION_RATE', 10)),

    /*
    |--------------------------------------------------------------------------
    | Support
    |--------------------------------------------------------------------------
    */

    'support_email' => env('SHIPNEST_SUPPORT_EMAIL', 'support@shipnest.com'),
    'support_phone' => env('SHIPNEST_SUPPORT_PHONE', '+880 1700-000000'),

    'admin_notification_email' => env('SHIPNEST_ADMIN_NOTIFICATION_EMAIL', 'admin@shipnest.com'),

    /*
    |--------------------------------------------------------------------------
    | Social Links
    |--------------------------------------------------------------------------
    */

    'social' => [
        'facebook' => env('SHIPNEST_FACEBOOK_URL', 'https://facebook.com/shipnest'),
        'instagram' => env('SHIPNEST_INSTAGRAM_URL', 'https://instagram.com/shipnest'),
        'twitter' => env('SHIPNEST_TWITTER_URL', 'https://twitter.com/shipnest'),
        'youtube' => env('SHIPNEST_YOUTUBE_URL', 'https://youtube.com/shipnest'),
    ],

];
