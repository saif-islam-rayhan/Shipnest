<?php

namespace App\Support;

class SocialLogin
{
    public static function isGoogleEnabled(): bool
    {
        return (bool) config('shipnest.google_login_enabled', false);
    }

    public static function isGoogleConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    public static function isGoogleReady(): bool
    {
        return self::isGoogleEnabled() && self::isGoogleConfigured();
    }

    public static function isFacebookEnabled(): bool
    {
        return (bool) config('shipnest.facebook_login_enabled', false);
    }

    public static function isFacebookConfigured(): bool
    {
        return filled(config('services.facebook.client_id'))
            && filled(config('services.facebook.client_secret'));
    }

    public static function isFacebookReady(): bool
    {
        return self::isFacebookEnabled() && self::isFacebookConfigured();
    }

    public static function hasAnyProvider(): bool
    {
        return self::isGoogleReady() || self::isFacebookReady();
    }
}
