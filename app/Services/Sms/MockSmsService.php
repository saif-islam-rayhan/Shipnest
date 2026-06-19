<?php

namespace App\Services\Sms;

use App\Contracts\SmsServiceInterface;
use Illuminate\Support\Facades\Log;

class MockSmsService implements SmsServiceInterface
{
    public function send(string $phone, string $message): bool
    {
        Log::info('Mock SMS sent', [
            'phone' => $phone,
            'message' => $message,
        ]);

        return true;
    }
}
