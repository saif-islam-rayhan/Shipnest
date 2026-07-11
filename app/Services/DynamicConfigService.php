<?php

namespace App\Services;

use App\Services\Market\Llm\LlmProviderManager;
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
        $this->applySocialLogin();
        $this->applySearch();
        $this->applyLanguage();
        $this->applyLocation();
        $this->applyAgent();
        $this->applyLlmProviders();
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

    protected function applySocialLogin(): void
    {
        $g = $this->settings->getGroup('general');
        $i = $this->settings->getGroup('integrations');

        $googleClientId = $g['google_client_id'] ?? $i['google_client_id'] ?? env('GOOGLE_CLIENT_ID');
        $googleRedirect = $g['google_redirect_uri']
            ?? $i['google_redirect_uri']
            ?? env('GOOGLE_REDIRECT_URI', rtrim((string) config('app.url'), '/').'/auth/google/callback');

        $googleSecret = $this->settings->getSecure('google_client_secret', 'general');
        if ($googleSecret === null) {
            $googleSecret = $this->settings->getSecure('google_client_secret', 'integrations');
        }
        if ($googleSecret === null && filled(env('GOOGLE_CLIENT_SECRET'))) {
            $googleSecret = env('GOOGLE_CLIENT_SECRET');
        }

        $this->setIfPresent('services.google.client_id', $googleClientId);
        $this->setIfPresent('services.google.redirect', $googleRedirect);
        if ($googleSecret !== null) {
            config(['services.google.client_secret' => $googleSecret]);
        }

        if (array_key_exists('google_login_enabled', $g)) {
            config(['shipnest.google_login_enabled' => ($g['google_login_enabled'] ?? '0') === '1']);
        } elseif (filled($googleClientId) && filled($googleSecret)) {
            config(['shipnest.google_login_enabled' => (bool) env('GOOGLE_LOGIN_ENABLED', true)]);
        }

        $this->setIfPresent('services.facebook.client_id', $i['facebook_client_id'] ?? env('FACEBOOK_CLIENT_ID'));
        $this->setIfPresent('services.facebook.redirect', $i['facebook_redirect_uri']
            ?? env('FACEBOOK_REDIRECT_URI', rtrim((string) config('app.url'), '/').'/auth/facebook/callback'));

        $fbSecret = $this->settings->getSecure('facebook_client_secret', 'integrations');
        if ($fbSecret === null && filled(env('FACEBOOK_CLIENT_SECRET'))) {
            $fbSecret = env('FACEBOOK_CLIENT_SECRET');
        }
        if ($fbSecret !== null) {
            config(['services.facebook.client_secret' => $fbSecret]);
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

    protected function applyLanguage(): void
    {
        $language = $this->settings->getGroup('language');
        $available = [];

        if ($this->toBool($language['locale_en_enabled'] ?? '1')) {
            $available[] = 'en';
        }
        if ($this->toBool($language['locale_bn_enabled'] ?? '1')) {
            $available[] = 'bn';
        }

        if ($available === []) {
            $available = ['en'];
        }

        $default = $language['default_locale'] ?? config('app.locale', 'en');
        if (! in_array($default, $available, true)) {
            $default = $available[0];
        }

        config([
            'app.locale' => $default,
            'shipnest.localization.default_locale' => $default,
            'shipnest.localization.available_locales' => $available,
            'shipnest.localization.language_switcher_enabled' => $this->toBool($language['language_switcher_enabled'] ?? '1'),
        ]);
    }

    protected function applyLocation(): void
    {
        $location = $this->settings->getGroup('location');

        config([
            'shipnest.map.enabled' => $this->toBool($location['map_enabled'] ?? '1'),
            'shipnest.map.provider' => $location['map_provider'] ?? config('shipnest.map.provider', 'leaflet'),
            'shipnest.map.default_lat' => (float) ($location['map_default_lat'] ?? config('shipnest.map.default_lat', 23.8103)),
            'shipnest.map.default_lng' => (float) ($location['map_default_lng'] ?? config('shipnest.map.default_lng', 90.4125)),
            'shipnest.map.default_zoom' => (int) ($location['map_default_zoom'] ?? config('shipnest.map.default_zoom', 12)),
            'shipnest.map.country_code' => strtolower($location['map_country_code'] ?? config('shipnest.map.country_code', 'bd')),
        ]);

        $googleKey = $this->settings->getSecure('google_maps_api_key', 'location')
            ?? ($location['google_maps_api_key'] ?? null)
            ?? config('shipnest.map.google_maps_api_key');

        if ($googleKey) {
            config(['shipnest.map.google_maps_api_key' => $googleKey]);
        }
    }

    protected function applyAgent(): void
    {
        $a = $this->settings->getGroup('agent');

        if (array_key_exists('use_live_llm', $a)) {
            config(['market.use_live_llm' => $this->toBool($a['use_live_llm'])]);
        }

        $this->setIfPresent('market.github_models_endpoint', $a['github_models_endpoint'] ?? null);
        $this->setIfPresent('market.model_google_search', $a['model_google_search'] ?? null);
        $this->setIfPresent('market.model_vision', $a['model_vision'] ?? null);
        $this->setIfPresent('market.search_backend', $a['search_backend'] ?? null);
        $this->setIfPresent('market.searxng_url', $a['searxng_url'] ?? null);
        $this->setIfPresent('market.google_ai_mode_gl', $a['google_ai_mode_gl'] ?? null);
        $this->setIfPresent('market.google_ai_mode_hl', $a['google_ai_mode_hl'] ?? null);
        $this->setIfPresent('market.google_ai_mode_location', $a['google_ai_mode_location'] ?? null);

        if (array_key_exists('use_google_ai_mode', $a)) {
            config(['market.use_google_ai_mode' => $this->toBool($a['use_google_ai_mode'])]);
        }

        $githubToken = $this->settings->getSecure('github_token', 'agent');
        if ($githubToken !== null) {
            config(['market.github_token' => $githubToken]);
        } elseif (filled(env('GITHUB_TOKEN'))) {
            config(['market.github_token' => env('GITHUB_TOKEN')]);
        }

        $tavilyKey = $this->settings->getSecure('tavily_api_key', 'agent');
        if ($tavilyKey !== null) {
            config(['market.tavily_api_key' => $tavilyKey]);
        } elseif (filled(env('TAVILY_API_KEY'))) {
            config(['market.tavily_api_key' => env('TAVILY_API_KEY')]);
        }

        $serpapiKey = $this->settings->getSecure('serpapi_key', 'agent');
        if ($serpapiKey !== null) {
            config(['market.serpapi_key' => $serpapiKey]);
        } elseif (filled(env('SERPAPI_KEY'))) {
            config(['market.serpapi_key' => env('SERPAPI_KEY')]);
        }

        $this->setIfPresent('shipnest.agent.name', $a['agent_name'] ?? null);
        $this->setIfPresent('shipnest.agent.logo', $a['agent_logo'] ?? null);
    }

    protected function applyLlmProviders(): void
    {
        try {
            app(LlmProviderManager::class)->migrateLegacyGithubConfig();
            app(LlmProviderManager::class)->applyRuntimeConfig();
        } catch (\Throwable) {
            // Optional during early boot.
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
