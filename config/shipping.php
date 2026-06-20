<?php

return [
    'free_shipping_enabled' => env('SHIPPING_FREE_ENABLED', false),

    'methods' => [
        'standard' => [
            'name' => 'Standard Delivery',
            'courier' => 'ShipNest Logistics',
            'rate' => 60,
            'days' => '3-5 business days',
        ],
        'express' => [
            'name' => 'Express Delivery',
            'courier' => 'ShipNest Express',
            'rate' => 120,
            'days' => '1-2 business days',
        ],
        'pathao' => [
            'name' => 'Pathao Courier',
            'courier' => 'Pathao',
            'rate' => 80,
            'days' => '2-4 business days',
        ],
        'steadfast' => [
            'name' => 'Steadfast Courier',
            'courier' => 'Steadfast',
            'rate' => 70,
            'days' => '2-4 business days',
        ],
    ],
];
