<?php

namespace App\Services\Sms;

use App\Contracts\SmsServiceInterface;
use Twilio\Rest\Client;

class TwilioSmsService implements SmsServiceInterface
{
    public function send(string $phone, string $message): bool
    {
        $sid = config('sms.twilio.sid');
        $token = config('sms.twilio.auth_token');
        $from = config('sms.twilio.from');

        if (! $sid || ! $token || ! $from) {
            throw new \RuntimeException('Twilio credentials are not configured.');
        }

        $client = new Client($sid, $token);

        $client->messages->create($this->formatPhone($phone), [
            'from' => $from,
            'body' => $message,
        ]);

        return true;
    }

    protected function formatPhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (str_starts_with($phone, '0')) {
            return '+880'.substr($phone, 1);
        }

        if (! str_starts_with($phone, '+')) {
            return '+'.$phone;
        }

        return $phone;
    }
}
