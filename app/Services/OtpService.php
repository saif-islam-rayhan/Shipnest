<?php

namespace App\Services;

use App\Contracts\SmsServiceInterface;
use App\Models\OtpVerification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public function __construct(
        private readonly SmsServiceInterface $sms,
    ) {}

    public function send(string $phone, string $type, ?string $email = null): OtpVerification
    {
        $phone = $this->normalizePhone($phone);
        $cooldownKey = "otp_cooldown:{$phone}:{$type}";

        if (Cache::has($cooldownKey)) {
            throw ValidationException::withMessages([
                'phone' => 'Please wait before requesting another OTP.',
            ]);
        }

        OtpVerification::query()
            ->forPhone($phone)
            ->where('type', $type)
            ->whereNull('verified_at')
            ->update(['verified_at' => now()]);

        $otp = $this->generateOtp();
        $expiryMinutes = config('sms.otp.expiry_minutes', 10);

        $verification = OtpVerification::query()->create([
            'phone' => $phone,
            'email' => $email,
            'otp' => $otp,
            'type' => $type,
            'expires_at' => now()->addMinutes($expiryMinutes),
        ]);

        $message = "Your ShipNest verification code is: {$otp}. Valid for {$expiryMinutes} minutes.";
        $this->sms->send($phone, $message);

        Cache::put($cooldownKey, true, config('sms.otp.resend_cooldown_seconds', 60));

        if (config('sms.driver') === 'mock' && app()->environment('local')) {
            session()->flash('debug_otp', $otp);
        }

        return $verification;
    }

    public function verify(string $phone, string $otp, string $type): bool
    {
        $phone = $this->normalizePhone($phone);

        $verification = OtpVerification::query()
            ->forPhone($phone)
            ->where('type', $type)
            ->valid()
            ->latest()
            ->first();

        if (! $verification || $verification->otp !== $otp) {
            return false;
        }

        $verification->update(['verified_at' => now()]);

        return true;
    }

    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\s+/', '', $phone);

        if (str_starts_with($phone, '+880')) {
            $phone = '0'.substr($phone, 4);
        }

        return $phone;
    }

    protected function generateOtp(): string
    {
        $length = config('sms.otp.length', 6);

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }
}
