<?php

return [

    'driver' => env('SMS_DRIVER', 'mock'),

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'from' => env('TWILIO_FROM_NUMBER'),
    ],

    'otp' => [
        'length' => 6,
        'expiry_minutes' => 10,
        'resend_cooldown_seconds' => 60,
    ],

];
