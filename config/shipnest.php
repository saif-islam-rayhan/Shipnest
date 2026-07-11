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
    | Social Login
    |--------------------------------------------------------------------------
    */

    'google_login_enabled' => (bool) env('GOOGLE_LOGIN_ENABLED', false),

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

    /*
    |--------------------------------------------------------------------------
    | Localization
    |--------------------------------------------------------------------------
    */

    'localization' => [
        'default_locale' => env('APP_LOCALE', 'en'),
        'available_locales' => ['en', 'bn'],
        'language_switcher_enabled' => true,
        'locale_labels' => [
            'en' => 'English',
            'bn' => 'বাংলা',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Map / Location Picker
    |--------------------------------------------------------------------------
    */

    'map' => [
        'enabled' => true,
        'provider' => env('MAP_PROVIDER', 'leaflet'),
        'google_maps_api_key' => env('GOOGLE_MAPS_API_KEY'),
        'default_lat' => (float) env('MAP_DEFAULT_LAT', 23.8103),
        'default_lng' => (float) env('MAP_DEFAULT_LNG', 90.4125),
        'default_zoom' => (int) env('MAP_DEFAULT_ZOOM', 12),
        'country_code' => env('MAP_COUNTRY_CODE', 'bd'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Agent Branding
    |--------------------------------------------------------------------------
    */

    'agent' => [
        'name' => env('SHIPNEST_AGENT_NAME', 'ShipNest AI'),
        'logo' => null,
    ],

];
