<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DynamicConfigService;
use App\Services\Market\Llm\LlmProviderManager;
use App\Services\Payment\SSLCommerzService;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(
        private readonly SettingService $settings,
        private readonly DynamicConfigService $dynamicConfig,
        private readonly SSLCommerzService $sslCommerz,
        private readonly LlmProviderManager $llmProviders,
    ) {}

    public function edit(): View
    {
        $tab = request('tab', 'general');

        $general = $this->settings->mergedGroup('general', [
            'site_name' => config('shipnest.name'),
            'app_url' => config('app.url'),
            'app_timezone' => config('app.timezone'),
            'currency' => config('shipnest.currency'),
            'currency_symbol' => config('shipnest.currency_symbol'),
            'free_shipping_threshold' => config('shipnest.free_shipping_threshold'),
            'contact_email' => config('shipnest.support_email'),
            'contact_phone' => config('shipnest.support_phone'),
            'google_login_enabled' => config('shipnest.google_login_enabled') ? '1' : '0',
            'google_client_id' => config('services.google.client_id'),
            'google_redirect_uri' => config('services.google.redirect', url('/auth/google/callback')),
        ]);

        $database = $this->settings->mergedGroup('database', [
            'db_connection' => config('database.default'),
            'db_host' => config('database.connections.mysql.host'),
            'db_port' => config('database.connections.mysql.port'),
            'db_database' => config('database.connections.mysql.database'),
            'db_username' => config('database.connections.mysql.username'),
        ]);

        $paymentDefaults = [
            'sslcommerz_store_id' => config('payment.sslcommerz.store_id'),
            'sslcommerz_api_url' => config('payment.sslcommerz.api_url', 'https://sandbox.sslcommerz.com'),
            'sslcommerz_sandbox' => config('payment.sslcommerz.sandbox', true) ? '1' : '0',
            'bkash_base_url' => config('payment.bkash.base_url'),
            'nagad_base_url' => config('payment.nagad.base_url'),
            'stripe_currency' => config('payment.stripe.currency'),
            'payment_cod_enabled' => config('payment.enabled.cod') ? '1' : '0',
            'payment_sslcommerz_enabled' => config('payment.enabled.sslcommerz') ? '1' : '0',
            'payment_bkash_enabled' => config('payment.enabled.bkash') ? '1' : '0',
            'payment_nagad_enabled' => config('payment.enabled.nagad') ? '1' : '0',
            'payment_stripe_enabled' => config('payment.enabled.stripe') ? '1' : '0',
        ];

        $payment = $this->settings->mergedGroup('payment', $paymentDefaults);
        $payment = $this->fillEmptyPaymentFallbacks($payment, $paymentDefaults);

        $sslPasswordInDb = $this->settings->hasSecure('sslcommerz_store_password', 'payment');
        $sslPasswordInEnv = filled(env('SSLCOMMERZ_STORE_PASSWORD'));
        $sslStoreIdInDb = filled($this->settings->get('sslcommerz_store_id'));
        $sslConfigured = $this->sslCommerz->isConfigured();

        $paymentMeta = [
            'ssl_configured' => $sslConfigured,
            'ssl_store_id_source' => $sslStoreIdInDb ? 'database' : (filled($paymentDefaults['sslcommerz_store_id']) ? 'env' : null),
            'ssl_password_source' => $sslPasswordInDb ? 'database' : ($sslPasswordInEnv ? 'env' : null),
        ];

        $mail = $this->settings->mergedGroup('mail', [
            'mail_mailer' => config('mail.default'),
            'mail_host' => config('mail.mailers.smtp.host'),
            'mail_port' => config('mail.mailers.smtp.port'),
            'mail_encryption' => config('mail.mailers.smtp.encryption'),
            'mail_from_address' => config('mail.from.address'),
            'mail_from_name' => config('mail.from.name'),
        ]);

        $sms = $this->settings->mergedGroup('sms', [
            'sms_driver' => config('sms.driver'),
        ]);

        $integrations = $this->settings->mergedGroup('integrations', [
            'scout_driver' => config('scout.driver'),
            'meilisearch_host' => config('scout.meilisearch.host'),
            'pusher_app_cluster' => config('broadcasting.connections.pusher.options.cluster'),
        ]);

        $commission = $this->settings->mergedGroup('commission', [
            'default_commission_rate' => config('shipnest.commission_rate'),
        ]);

        $language = $this->settings->mergedGroup('language', [
            'default_locale' => config('shipnest.localization.default_locale', config('app.locale', 'en')),
            'locale_en_enabled' => in_array('en', config('shipnest.localization.available_locales', ['en']), true) ? '1' : '0',
            'locale_bn_enabled' => in_array('bn', config('shipnest.localization.available_locales', ['en', 'bn']), true) ? '1' : '0',
            'language_switcher_enabled' => config('shipnest.localization.language_switcher_enabled', true) ? '1' : '0',
        ]);

        $location = $this->settings->mergedGroup('location', [
            'map_enabled' => config('shipnest.map.enabled', true) ? '1' : '0',
            'map_provider' => config('shipnest.map.provider', 'leaflet'),
            'map_default_lat' => (string) config('shipnest.map.default_lat', 23.8103),
            'map_default_lng' => (string) config('shipnest.map.default_lng', 90.4125),
            'map_default_zoom' => (string) config('shipnest.map.default_zoom', 12),
            'map_country_code' => config('shipnest.map.country_code', 'bd'),
            'google_maps_api_key' => config('shipnest.map.google_maps_api_key'),
        ]);

        $agent = $this->settings->mergedGroup('agent', [
            'agent_name' => config('shipnest.agent.name', 'ShipNest AI'),
            'agent_logo' => config('shipnest.agent.logo'),
            'use_live_llm' => config('market.use_live_llm') ? '1' : '0',
            'github_models_endpoint' => config('market.github_models_endpoint'),
            'model_google_search' => config('market.model_google_search'),
            'model_vision' => config('market.model_vision'),
            'use_google_ai_mode' => config('market.use_google_ai_mode') ? '1' : '0',
            'search_backend' => config('market.search_backend'),
            'searxng_url' => config('market.searxng_url'),
            'google_ai_mode_gl' => config('market.google_ai_mode_gl'),
            'google_ai_mode_hl' => config('market.google_ai_mode_hl'),
            'google_ai_mode_location' => config('market.google_ai_mode_location'),
        ]);

        $secureHints = [
            'database' => ['db_password'],
            'payment' => ['sslcommerz_store_password', 'bkash_app_secret', 'bkash_password', 'nagad_private_key', 'stripe_secret', 'stripe_webhook_secret'],
            'mail' => ['mail_password'],
            'sms' => ['twilio_token'],
            'general' => ['google_client_secret'],
            'integrations' => ['google_client_secret', 'facebook_client_secret', 'pusher_app_secret', 'aws_secret_access_key', 'meilisearch_key', 'redis_password'],
            'location' => ['google_maps_api_key'],
            'agent' => ['tavily_api_key', 'serpapi_key'],
        ];

        $hasSecure = [];
        foreach ($secureHints as $group => $keys) {
            foreach ($keys as $key) {
                $hasSecure[$key] = $this->settings->hasSecure($key, $group);
            }
        }

        if (! $hasSecure['sslcommerz_store_password'] && $sslPasswordInEnv) {
            $hasSecure['sslcommerz_store_password'] = true;
        }

        foreach (['tavily_api_key' => 'TAVILY_API_KEY', 'serpapi_key' => 'SERPAPI_KEY'] as $key => $envKey) {
            if (! ($hasSecure[$key] ?? false) && filled(env($envKey))) {
                $hasSecure[$key] = true;
            }
        }

        if (! ($hasSecure['google_client_secret'] ?? false) && filled(env('GOOGLE_CLIENT_SECRET'))) {
            $hasSecure['google_client_secret'] = true;
        }

        $this->llmProviders->migrateLegacyGithubConfig();
        $llmProviderCards = $this->llmProviders->cardsForAdmin();

        return view('admin.settings.edit', compact(
            'tab', 'general', 'database', 'payment', 'paymentMeta', 'mail', 'sms', 'integrations', 'commission', 'language', 'location', 'agent', 'hasSecure', 'llmProviderCards',
        ));
    }

    public function update(Request $request): RedirectResponse
    {
        $group = $request->input('group', 'general');

        match ($group) {
            'general' => $this->updateGeneral($request),
            'database' => $this->updateDatabase($request),
            'payment' => $this->updatePayment($request),
            'mail' => $this->updateMail($request),
            'sms' => $this->updateSms($request),
            'integrations' => $this->updateIntegrations($request),
            'commission' => $this->updateCommission($request),
            'language' => $this->updateLanguage($request),
            'location' => $this->updateLocation($request),
            'agent' => $this->updateAgent($request),
            default => null,
        };

        $this->settings->flush();
        $this->dynamicConfig->apply();

        if ($group === 'database') {
            DB::purge(config('database.default'));
        }

        Artisan::call('config:clear');

        return redirect()
            ->route('admin.settings.edit', ['tab' => $group])
            ->with('success', ucfirst($group).' settings saved and applied.');
    }

    public function toggleMaintenance(Request $request): RedirectResponse
    {
        $enabled = $request->boolean('maintenance_mode');

        if ($enabled) {
            Artisan::call('down', ['--secret' => $request->input('secret', 'shipnest-admin')]);
        } else {
            Artisan::call('up');
        }

        $this->settings->set('maintenance_mode', $enabled ? '1' : '0', 'general');

        return redirect()
            ->route('admin.settings.edit', ['tab' => 'maintenance'])
            ->with('success', $enabled ? 'Maintenance mode enabled.' : 'Maintenance mode disabled.');
    }

    protected function updateGeneral(Request $request): void
    {
        $request->validate([
            'site_name' => ['nullable', 'string', 'max:255'],
            'app_url' => ['nullable', 'url'],
            'app_timezone' => ['nullable', 'string', 'max:64'],
            'currency' => ['nullable', 'string', 'max:10'],
            'currency_symbol' => ['nullable', 'string', 'max:8'],
            'free_shipping_threshold' => ['nullable', 'numeric', 'min:0'],
            'contact_email' => ['nullable', 'email'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'contact_address' => ['nullable', 'string'],
            'logo' => ['nullable', 'image'],
            'favicon' => ['nullable', 'image'],
            'google_client_id' => ['nullable', 'string', 'max:512'],
            'google_redirect_uri' => ['nullable', 'url', 'max:512'],
            'google_client_secret' => ['nullable', 'string', 'max:512'],
        ]);

        $this->settings->persist($request, [
            'site_name', 'app_url', 'app_timezone', 'currency', 'currency_symbol',
            'free_shipping_threshold', 'contact_email', 'contact_phone', 'contact_address',
            'google_client_id', 'google_redirect_uri',
        ], 'general', ['google_client_secret'], ['google_login_enabled']);

        if ($request->hasFile('logo')) {
            $this->settings->set('logo', $request->file('logo')->store('settings', 'public'), 'general');
        }
        if ($request->hasFile('favicon')) {
            $this->settings->set('favicon', $request->file('favicon')->store('settings', 'public'), 'general');
        }
    }

    protected function updateDatabase(Request $request): void
    {
        $request->validate([
            'db_connection' => ['nullable', 'string', 'in:mysql,mariadb,sqlite,pgsql'],
            'db_host' => ['nullable', 'string', 'max:255'],
            'db_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'db_database' => ['nullable', 'string', 'max:255'],
            'db_username' => ['nullable', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:255'],
        ]);

        $this->settings->persist($request, [
            'db_connection', 'db_host', 'db_port', 'db_database', 'db_username',
        ], 'database', ['db_password']);
    }

    public function testSslCommerz(Request $request): JsonResponse
    {
        $request->validate([
            'sslcommerz_store_id' => ['nullable', 'string', 'max:255'],
            'sslcommerz_store_password' => ['nullable', 'string', 'max:255'],
            'sslcommerz_api_url' => ['nullable', 'url'],
        ]);

        $storeId = $request->filled('sslcommerz_store_id')
            ? $request->input('sslcommerz_store_id')
            : config('payment.sslcommerz.store_id');

        $storePassword = $request->filled('sslcommerz_store_password')
            ? $request->input('sslcommerz_store_password')
            : ($this->settings->getSecure('sslcommerz_store_password', 'payment')
                ?? config('payment.sslcommerz.store_password'));

        $apiUrl = $request->input('sslcommerz_api_url')
            ?: config('payment.sslcommerz.api_url', 'https://sandbox.sslcommerz.com');

        $result = $this->sslCommerz->testConnection($storeId, $storePassword, $apiUrl);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    protected function updatePayment(Request $request): void
    {
        $this->settings->persist($request, [
            'sslcommerz_store_id', 'sslcommerz_api_url',
            'bkash_app_key', 'bkash_username', 'bkash_base_url', 'bkash_merchant_number',
            'nagad_merchant_id', 'nagad_merchant_number', 'nagad_base_url', 'nagad_challenge', 'nagad_public_key',
            'stripe_key', 'stripe_currency',
        ], 'payment', [
            'sslcommerz_store_password', 'bkash_app_secret', 'bkash_password',
            'nagad_private_key', 'stripe_secret', 'stripe_webhook_secret',
        ], [
            'sslcommerz_sandbox', 'bkash_sandbox', 'nagad_sandbox',
            'payment_cod_enabled', 'payment_sslcommerz_enabled',
            'payment_bkash_enabled', 'payment_nagad_enabled', 'payment_stripe_enabled',
        ]);

        $this->syncSslCommerzApiUrl($request);
    }

    protected function syncSslCommerzApiUrl(Request $request): void
    {
        $sandbox = $request->boolean('sslcommerz_sandbox');
        $sandboxUrl = 'https://sandbox.sslcommerz.com';
        $liveUrl = 'https://securepay.sslcommerz.com';
        $currentUrl = $request->input('sslcommerz_api_url', '');

        if ($currentUrl === '' || in_array($currentUrl, [$sandboxUrl, $liveUrl], true)) {
            $this->settings->set('sslcommerz_api_url', $sandbox ? $sandboxUrl : $liveUrl, 'payment');
        }
    }

    protected function fillEmptyPaymentFallbacks(array $payment, array $defaults): array
    {
        foreach (['sslcommerz_store_id', 'sslcommerz_api_url', 'sslcommerz_sandbox'] as $key) {
            if (empty($payment[$key]) && filled($defaults[$key] ?? null)) {
                $payment[$key] = $defaults[$key];
            }
        }

        return $payment;
    }

    protected function updateMail(Request $request): void
    {
        $this->settings->persist($request, [
            'mail_mailer', 'mail_host', 'mail_port', 'mail_username',
            'mail_encryption', 'mail_from_address', 'mail_from_name',
        ], 'mail', ['mail_password']);
    }

    protected function updateSms(Request $request): void
    {
        $this->settings->persist($request, [
            'sms_driver', 'twilio_sid', 'twilio_from', 'bulksms_api_key',
        ], 'sms', ['twilio_token']);
    }

    protected function updateIntegrations(Request $request): void
    {
        $this->settings->persist($request, [
            'facebook_client_id', 'facebook_redirect_uri',
            'pusher_app_id', 'pusher_app_key', 'pusher_app_cluster',
            'aws_access_key_id', 'aws_default_region', 'aws_bucket',
            'scout_driver', 'meilisearch_host',
            'redis_host', 'redis_port',
        ], 'integrations', [
            'facebook_client_secret', 'pusher_app_secret',
            'aws_secret_access_key', 'meilisearch_key', 'redis_password',
        ]);
    }

    protected function updateCommission(Request $request): void
    {
        $request->validate(['default_commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100']]);
        $this->settings->set('default_commission_rate', $request->input('default_commission_rate', 10), 'commission');
        config(['shipnest.commission_rate' => (float) $request->input('default_commission_rate', 10)]);
    }

    protected function updateLanguage(Request $request): void
    {
        $request->validate([
            'default_locale' => ['required', 'string', 'in:en,bn'],
        ]);

        $this->settings->persist($request, [
            'default_locale',
        ], 'language', [], [
            'locale_en_enabled', 'locale_bn_enabled', 'language_switcher_enabled',
        ]);
    }

    protected function updateLocation(Request $request): void
    {
        $request->validate([
            'map_provider' => ['nullable', 'string', 'in:leaflet,google'],
            'map_default_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'map_default_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'map_default_zoom' => ['nullable', 'integer', 'min:1', 'max:20'],
            'map_country_code' => ['nullable', 'string', 'max:2'],
            'google_maps_api_key' => ['nullable', 'string', 'max:255'],
        ]);

        $this->settings->persist($request, [
            'map_provider', 'map_default_lat', 'map_default_lng', 'map_default_zoom', 'map_country_code',
        ], 'location', ['google_maps_api_key'], ['map_enabled']);
    }

    public function updateLlmProvider(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => ['required', 'string'],
            'api_key' => ['nullable', 'string', 'max:512'],
            'model' => ['nullable', 'string', 'max:128'],
            'vision_model' => ['nullable', 'string', 'max:128'],
            'base_url' => ['nullable', 'string', 'max:255'],
            'enabled' => ['nullable', 'boolean'],
        ]);

        $providerId = $request->string('provider')->toString();

        if ($request->has('enabled') && $request->boolean('enabled')) {
            $cards = collect($this->llmProviders->cardsForAdmin());
            $card = $cards->firstWhere('id', $providerId);
            $hasKey = filled($request->input('api_key')) || ($card['configured'] ?? false);

            if (! $hasKey) {
                return response()->json(['message' => 'Configure API key before enabling this provider.'], 422);
            }
        }

        try {
            $this->llmProviders->saveProvider($providerId, [
                'api_key' => $request->input('api_key'),
                'model' => $request->input('model'),
                'vision_model' => $request->input('vision_model'),
                'base_url' => $request->input('base_url'),
                'enabled' => $request->has('enabled') ? $request->boolean('enabled') : null,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->settings->flush();
        $this->dynamicConfig->apply();
        Artisan::call('config:clear');

        return response()->json([
            'message' => 'Provider saved.',
            'providers' => $this->llmProviders->cardsForAdmin(),
        ]);
    }

    public function testLlmProvider(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => ['required', 'string'],
        ]);

        $providerId = $request->string('provider')->toString();
        $result = $this->llmProviders->testProvider($providerId);

        $this->settings->flush();
        $this->dynamicConfig->apply();

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'providers' => $this->llmProviders->cardsForAdmin(),
        ], $result['success'] ? 200 : 422);
    }

    protected function updateAgent(Request $request): void
    {
        $request->validate([
            'agent_name' => ['nullable', 'string', 'max:128'],
            'agent_logo' => ['nullable', 'image', 'max:2048'],
            'search_backend' => ['nullable', 'string', 'in:duckduckgo,searxng,tavily,serpapi,free'],
            'searxng_url' => ['nullable', 'url'],
            'google_ai_mode_gl' => ['nullable', 'string', 'max:8'],
            'google_ai_mode_hl' => ['nullable', 'string', 'max:8'],
            'google_ai_mode_location' => ['nullable', 'string', 'max:128'],
            'tavily_api_key' => ['nullable', 'string', 'max:512'],
            'serpapi_key' => ['nullable', 'string', 'max:512'],
        ]);

        $this->settings->persist($request, [
            'agent_name',
            'search_backend',
            'searxng_url',
            'google_ai_mode_gl',
            'google_ai_mode_hl',
            'google_ai_mode_location',
        ], 'agent', [
            'tavily_api_key',
            'serpapi_key',
        ], [
            'use_live_llm',
            'use_google_ai_mode',
        ]);

        if ($request->hasFile('agent_logo')) {
            $this->settings->set('agent_logo', $request->file('agent_logo')->store('settings', 'public'), 'agent');
        }
    }
}
