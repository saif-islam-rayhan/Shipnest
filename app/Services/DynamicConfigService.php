<?php

namespace App\Services;

use Illuminate\Support\Facades\Schema;

class DynamicConfigService
{
    public function __construct(
        private readonly SettingService $settings,
    ) {}

    public function apply(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $this->applyGeneral();
        $this->applyDatabase();
        $this->applyPayment();
        $this->applyMail();
        $this->applySms();
        $this->applyIntegrations();
        $this->applySearch();
    }

    protected function applyGeneral(): void
    {
        $g = $this->settings->getGroup('general');

        if ($name = $g['site_name'] ?? null) {
            config(['app.name' => $name, 'shipnest.name' => $name]);
        }
        if ($url = $g['app_url'] ?? null) {
            config(['app.url' => rtrim($url, '/')]);
        }
        if ($tz = $g['app_timezone'] ?? null) {
            config(['app.timezone' => $tz]);
        }
        if (isset($g['currency'])) {
            config(['shipnest.currency' => $g['currency']]);
        }
        if (isset($g['currency_symbol'])) {
            config(['shipnest.currency_symbol' => $g['currency_symbol']]);
        }
        if (isset($g['free_shipping_threshold'])) {
            config(['shipnest.free_shipping_threshold' => (float) $g['free_shipping_threshold']]);
        }
        if ($email = $g['contact_email'] ?? $g['support_email'] ?? null) {
            config(['shipnest.support_email' => $email]);
        }
        if ($phone = $g['contact_phone'] ?? $g['support_phone'] ?? null) {
            config(['shipnest.support_phone' => $phone]);
        }

        $commission = $this->settings->getGroup('commission');
        if (isset($commission['default_commission_rate'])) {
            config(['shipnest.commission_rate' => (float) $commission['default_commission_rate']]);
        }
    }

    protected function applyDatabase(): void
    {
        $d = $this->settings->getGroup('database');
        $connection = $d['db_connection'] ?? config('database.default', 'mysql');

        $map = [
            'db_host' => 'host',
            'db_port' => 'port',
            'db_database' => 'database',
            'db_username' => 'username',
        ];

        foreach ($map as $settingKey => $configKey) {
            if (! empty($d[$settingKey])) {
                config(["database.connections.{$connection}.{$configKey}" => $d[$settingKey]]);
            }
        }

        $password = $this->settings->getSecure('db_password', 'database');
        if ($password !== null) {
            config(["database.connections.{$connection}.password" => $password]);
        }
    }

    protected function applyPayment(): void
    {
        $p = $this->settings->getGroup('payment');

        $enabled = [
            'payment_cod_enabled' => 'cod',
            'payment_sslcommerz_enabled' => 'sslcommerz',
            'payment_bkash_enabled' => 'bkash',
            'payment_nagad_enabled' => 'nagad',
            'payment_stripe_enabled' => 'stripe',
        ];

        foreach ($enabled as $key => $method) {
            if (array_key_exists($key, $p)) {
                config(["payment.enabled.{$method}" => $this->toBool($p[$key])]);
            }
        }

        $this->setIfPresent('payment.sslcommerz.store_id', $p['sslcommerz_store_id'] ?? null);
        $this->setIfPresent('payment.sslcommerz.api_url', $p['sslcommerz_api_url'] ?? null);
        if (array_key_exists('sslcommerz_sandbox', $p)) {
            config(['payment.sslcommerz.sandbox' => $this->toBool($p['sslcommerz_sandbox'])]);
        }

        $sslPass = $this->settings->getSecure('sslcommerz_store_password', 'payment');
        if ($sslPass !== null) {
            config(['payment.sslcommerz.store_password' => $sslPass]);
        } elseif (filled(env('SSLCOMMERZ_STORE_PASSWORD'))) {
            config(['payment.sslcommerz.store_password' => env('SSLCOMMERZ_STORE_PASSWORD')]);
        }

        if (empty($p['sslcommerz_store_id'] ?? null) && filled(env('SSLCOMMERZ_STORE_ID'))) {
            config(['payment.sslcommerz.store_id' => env('SSLCOMMERZ_STORE_ID')]);
        }

        $this->setIfPresent('payment.bkash.app_key', $p['bkash_app_key'] ?? null);
        $this->setIfPresent('payment.bkash.username', $p['bkash_username'] ?? null);
        $this->setIfPresent('payment.bkash.base_url', $p['bkash_base_url'] ?? null);
        if (array_key_exists('bkash_sandbox', $p)) {
            config(['payment.bkash.sandbox' => $this->toBool($p['bkash_sandbox'])]);
        }
        $this->setIfPresent('payment.merchant_numbers.bkash', $p['bkash_merchant_number'] ?? null);

        $bkashSecret = $this->settings->getSecure('bkash_app_secret', 'payment');
        if ($bkashSecret !== null) {
            config(['payment.bkash.app_secret' => $bkashSecret]);
        }
        $bkashPass = $this->settings->getSecure('bkash_password', 'payment');
        if ($bkashPass !== null) {
            config(['payment.bkash.password' => $bkashPass]);
        }

        $this->setIfPresent('payment.nagad.merchant_id', $p['nagad_merchant_id'] ?? null);
        $this->setIfPresent('payment.nagad.merchant_number', $p['nagad_merchant_number'] ?? null);
        $this->setIfPresent('payment.nagad.base_url', $p['nagad_base_url'] ?? null);
        $this->setIfPresent('payment.nagad.challenge', $p['nagad_challenge'] ?? null);
        $this->setIfPresent('payment.nagad.public_key', $p['nagad_public_key'] ?? null);
        if (array_key_exists('nagad_sandbox', $p)) {
            config(['payment.nagad.sandbox' => $this->toBool($p['nagad_sandbox'])]);
        }
        $this->setIfPresent('payment.merchant_numbers.nagad', $p['nagad_merchant_number'] ?? null);

        $nagadKey = $this->settings->getSecure('nagad_private_key', 'payment');
        if ($nagadKey !== null) {
            config(['payment.nagad.private_key' => $nagadKey]);
        }

        $this->setIfPresent('payment.stripe.key', $p['stripe_key'] ?? null);
        $this->setIfPresent('payment.stripe.currency', $p['stripe_currency'] ?? null);

        $stripeSecret = $this->settings->getSecure('stripe_secret', 'payment');
        if ($stripeSecret !== null) {
            config(['payment.stripe.secret' => $stripeSecret]);
        }
        $stripeWebhook = $this->settings->getSecure('stripe_webhook_secret', 'payment');
        if ($stripeWebhook !== null) {
            config(['payment.stripe.webhook_secret' => $stripeWebhook]);
        }
    }

    protected function applyMail(): void
    {
        $m = $this->settings->getGroup('mail');

        $this->setIfPresent('mail.default', $m['mail_mailer'] ?? null);
        $this->setIfPresent('mail.mailers.smtp.host', $m['mail_host'] ?? null);
        $this->setIfPresent('mail.mailers.smtp.port', $m['mail_port'] ?? null);
        $this->setIfPresent('mail.mailers.smtp.encryption', $m['mail_encryption'] ?? null);
        $this->setIfPresent('mail.mailers.smtp.username', $m['mail_username'] ?? null);
        $this->setIfPresent('mail.from.address', $m['mail_from_address'] ?? null);
        $this->setIfPresent('mail.from.name', $m['mail_from_name'] ?? null);

        $mailPass = $this->settings->getSecure('mail_password', 'mail');
        if ($mailPass !== null) {
            config(['mail.mailers.smtp.password' => $mailPass]);
        }
    }

    protected function applySms(): void
    {
        $s = $this->settings->getGroup('sms');

        $this->setIfPresent('sms.driver', $s['sms_driver'] ?? null);
        $this->setIfPresent('sms.twilio.sid', $s['twilio_sid'] ?? null);
        $this->setIfPresent('sms.twilio.from', $s['twilio_from'] ?? null);

        $token = $this->settings->getSecure('twilio_token', 'sms');
        if ($token !== null) {
            config(['sms.twilio.auth_token' => $token]);
        }
    }

    protected function applyIntegrations(): void
    {
        $i = $this->settings->getGroup('integrations');

        $this->setIfPresent('services.google.client_id', $i['google_client_id'] ?? null);
        $this->setIfPresent('services.facebook.client_id', $i['facebook_client_id'] ?? null);
        $this->setIfPresent('services.google.redirect', $i['google_redirect_uri'] ?? null);
        $this->setIfPresent('services.facebook.redirect', $i['facebook_redirect_uri'] ?? null);

        $googleSecret = $this->settings->getSecure('google_client_secret', 'integrations');
        if ($googleSecret !== null) {
            config(['services.google.client_secret' => $googleSecret]);
        }
        $fbSecret = $this->settings->getSecure('facebook_client_secret', 'integrations');
        if ($fbSecret !== null) {
            config(['services.facebook.client_secret' => $fbSecret]);
        }

        $this->setIfPresent('broadcasting.connections.pusher.key', $i['pusher_app_key'] ?? null);
        $this->setIfPresent('broadcasting.connections.pusher.app_id', $i['pusher_app_id'] ?? null);
        $this->setIfPresent('broadcasting.connections.pusher.options.cluster', $i['pusher_app_cluster'] ?? null);

        $pusherSecret = $this->settings->getSecure('pusher_app_secret', 'integrations');
        if ($pusherSecret !== null) {
            config(['broadcasting.connections.pusher.secret' => $pusherSecret]);
        }

        $this->setIfPresent('filesystems.disks.s3.key', $i['aws_access_key_id'] ?? null);
        $this->setIfPresent('filesystems.disks.s3.region', $i['aws_default_region'] ?? null);
        $this->setIfPresent('filesystems.disks.s3.bucket', $i['aws_bucket'] ?? null);

        $awsSecret = $this->settings->getSecure('aws_secret_access_key', 'integrations');
        if ($awsSecret !== null) {
            config(['filesystems.disks.s3.secret' => $awsSecret]);
        }
    }

    protected function applySearch(): void
    {
        $i = $this->settings->getGroup('integrations');

        $this->setIfPresent('scout.driver', $i['scout_driver'] ?? null);
        $this->setIfPresent('scout.meilisearch.host', $i['meilisearch_host'] ?? null);

        $meiliKey = $this->settings->getSecure('meilisearch_key', 'integrations');
        if ($meiliKey !== null) {
            config(['scout.meilisearch.key' => $meiliKey]);
        }

        $this->setIfPresent('database.redis.default.host', $i['redis_host'] ?? null);
        $this->setIfPresent('database.redis.default.port', $i['redis_port'] ?? null);

        $redisPass = $this->settings->getSecure('redis_password', 'integrations');
        if ($redisPass !== null) {
            config(['database.redis.default.password' => $redisPass]);
        }
    }

    protected function setIfPresent(string $key, mixed $value): void
    {
        if ($value !== null && $value !== '') {
            config([$key => $value]);
        }
    }

    protected function toBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
