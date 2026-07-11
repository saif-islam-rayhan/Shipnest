<?php

return [
    'tabs' => [
        'general' => [
            'label' => 'General',
            'description' => 'Site name, logo, contact, currency & Google login',
        ],
        'database' => [
            'label' => 'Database',
            'description' => 'MySQL connection & credentials',
        ],
        'payment' => [
            'label' => 'Payment',
            'description' => 'SSLCommerz, bKash, Nagad, Stripe',
        ],
        'mail' => [
            'label' => 'Mail (SMTP)',
            'description' => 'Outgoing email configuration',
        ],
        'sms' => [
            'label' => 'SMS / OTP',
            'description' => 'Twilio & SMS providers',
        ],
        'integrations' => [
            'label' => 'Integrations',
            'description' => 'Social login, search, storage',
        ],
        'agent' => [
            'label' => 'Agent / AI',
            'description' => 'Agent name, logo, LLM, vision & search',
        ],
        'language' => [
            'label' => 'Language',
            'description' => 'Storefront locales & switcher',
        ],
        'location' => [
            'label' => 'Location & Map',
            'description' => 'Checkout map picker defaults',
        ],
        'commission' => [
            'label' => 'Commission',
            'description' => 'Default merchant commission',
        ],
        'maintenance' => [
            'label' => 'Maintenance',
            'description' => 'Site downtime & bypass secret',
        ],
        'security' => [
            'label' => 'Security',
            'description' => 'Two-factor authentication (2FA)',
        ],
    ],
];
