<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DynamicConfigService;
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

        $secureHints = [
            'database' => ['db_password'],
            'payment' => ['sslcommerz_store_password', 'bkash_app_secret', 'bkash_password', 'nagad_private_key', 'stripe_secret', 'stripe_webhook_secret'],
            'mail' => ['mail_password'],
            'sms' => ['twilio_token'],
            'integrations' => ['google_client_secret', 'facebook_client_secret', 'pusher_app_secret', 'aws_secret_access_key', 'meilisearch_key', 'redis_password'],
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

        return view('admin.settings.edit', compact(
            'tab', 'general', 'database', 'payment', 'paymentMeta', 'mail', 'sms', 'integrations', 'commission', 'hasSecure',
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
            default => null,
        };

        $this->settings->flush();
        $this->dynamicConfig->apply();

        if ($group === 'database') {
            DB::purge(config('database.default'));
        }

        Artisan::call('config:clear');

        return back()->with('success', ucfirst($group).' settings saved and applied.');
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

        return back()->with('success', $enabled ? 'Maintenance mode enabled.' : 'Maintenance mode disabled.');
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
        ]);

        $this->settings->persist($request, [
            'site_name', 'app_url', 'app_timezone', 'currency', 'currency_symbol',
            'free_shipping_threshold', 'contact_email', 'contact_phone', 'contact_address',
        ], 'general');

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
            'google_client_id', 'google_redirect_uri',
            'facebook_client_id', 'facebook_redirect_uri',
            'pusher_app_id', 'pusher_app_key', 'pusher_app_cluster',
            'aws_access_key_id', 'aws_default_region', 'aws_bucket',
            'scout_driver', 'meilisearch_host',
            'redis_host', 'redis_port',
        ], 'integrations', [
            'google_client_secret', 'facebook_client_secret', 'pusher_app_secret',
            'aws_secret_access_key', 'meilisearch_key', 'redis_password',
        ]);
    }

    protected function updateCommission(Request $request): void
    {
        $request->validate(['default_commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100']]);
        $this->settings->set('default_commission_rate', $request->input('default_commission_rate', 10), 'commission');
        config(['shipnest.commission_rate' => (float) $request->input('default_commission_rate', 10)]);
    }
}
