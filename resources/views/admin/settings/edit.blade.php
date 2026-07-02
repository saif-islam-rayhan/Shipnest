@extends('layouts.admin')
@section('title','Settings') @section('page-title','Platform Settings')
@section('content')
@php
    $tabs = [
        'general' => 'General',
        'database' => 'Database',
        'payment' => 'Payment & API Keys',
        'mail' => 'Mail (SMTP)',
        'sms' => 'SMS / OTP',
        'integrations' => 'Integrations',
        'commission' => 'Commission',
        'maintenance' => 'Maintenance',
    ];
    $paymentMeta = $paymentMeta ?? [];
    $pwd = function (string $key) use ($hasSecure, $paymentMeta) {
        if (! ($hasSecure[$key] ?? false)) {
            return '';
        }

        if ($key === 'sslcommerz_store_password' && ($paymentMeta['ssl_password_source'] ?? null) === 'env') {
            return '•••••••• (from .env — enter here to save to database)';
        }

        return '•••••••• (saved — leave blank to keep)';
    };
@endphp

<div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-xl text-sm text-blue-900">
    Settings saved here are stored in the database and applied dynamically. Empty password fields keep the existing value. <code class="bg-white px-1 rounded">.env</code> values are used as fallback until you save here.
</div>

<div class="flex gap-2 mb-6 flex-wrap">
    @foreach($tabs as $key => $label)
        <a href="?tab={{ $key }}" class="px-4 py-1.5 rounded-full text-sm {{ $tab===$key ? 'bg-[#F57C00] text-white' : 'bg-white ring-1 ring-gray-200' }}">{{ $label }}</a>
    @endforeach
</div>

@if($tab==='maintenance')
<div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 max-w-lg">
    <h2 class="font-semibold mb-4">Maintenance Mode</h2>
    <form action="{{ route('admin.settings.maintenance') }}" method="POST" class="space-y-3">@csrf
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="maintenance_mode" value="1" @checked(($general['maintenance_mode'] ?? '0')==='1')> Enable maintenance mode</label>
        <div class="form-group">
            <label class="form-label">Bypass secret</label>
            <input name="secret" class="input-field" value="shipnest-admin" placeholder="Optional secret to access site during maintenance">
        </div>
        <button class="btn-primary">Save</button>
    </form>
</div>
@else
<form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-xl ring-1 ring-gray-200 p-6 max-w-3xl space-y-4">@csrf @method('PUT')
    <input type="hidden" name="group" value="{{ $tab }}">

    @if($tab==='general')
        <h3 class="font-semibold text-gray-800 border-b pb-2">Site Identity</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div><label class="form-label">Site Name</label><input name="site_name" value="{{ $general['site_name'] ?? '' }}" class="input-field"></div>
            <div><label class="form-label">App URL</label><input name="app_url" value="{{ $general['app_url'] ?? '' }}" class="input-field" placeholder="https://shipnest.com"></div>
            <div><label class="form-label">Timezone</label><input name="app_timezone" value="{{ $general['app_timezone'] ?? 'Asia/Dhaka' }}" class="input-field"></div>
            <div><label class="form-label">Currency</label><input name="currency" value="{{ $general['currency'] ?? 'BDT' }}" class="input-field"></div>
            <div><label class="form-label">Currency Symbol</label><input name="currency_symbol" value="{{ $general['currency_symbol'] ?? '৳' }}" class="input-field"></div>
            <div><label class="form-label">Free Shipping Threshold</label><input name="free_shipping_threshold" type="number" value="{{ $general['free_shipping_threshold'] ?? 500 }}" class="input-field"></div>
        </div>
        <h3 class="font-semibold text-gray-800 border-b pb-2 pt-2">Contact</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div><label class="form-label">Email</label><input name="contact_email" value="{{ $general['contact_email'] ?? '' }}" class="input-field"></div>
            <div><label class="form-label">Phone</label><input name="contact_phone" value="{{ $general['contact_phone'] ?? '' }}" class="input-field"></div>
            <div class="md:col-span-2"><label class="form-label">Address</label><textarea name="contact_address" class="input-field" rows="2">{{ $general['contact_address'] ?? '' }}</textarea></div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="form-label">Logo</label><input type="file" name="logo" accept="image/*" class="text-sm mt-1">@if(!empty($general['logo']))<p class="text-xs text-gray-500 mt-1">{{ $general['logo'] }}</p>@endif</div>
            <div><label class="form-label">Favicon</label><input type="file" name="favicon" accept="image/*" class="text-sm mt-1"></div>
        </div>

    @elseif($tab==='database')
        <p class="text-sm text-amber-700 bg-amber-50 p-3 rounded-lg">Database changes apply on the next request. Wrong credentials can break the site — keep <code>.env</code> as backup.</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div><label class="form-label">Connection</label><select name="db_connection" class="input-field"><option value="mysql" @selected(($database['db_connection'] ?? 'mysql')==='mysql')>MySQL</option><option value="mariadb" @selected(($database['db_connection'] ?? '')==='mariadb')>MariaDB</option></select></div>
            <div><label class="form-label">Host</label><input name="db_host" value="{{ $database['db_host'] ?? '127.0.0.1' }}" class="input-field"></div>
            <div><label class="form-label">Port</label><input name="db_port" value="{{ $database['db_port'] ?? '3306' }}" class="input-field"></div>
            <div><label class="form-label">Database Name</label><input name="db_database" value="{{ $database['db_database'] ?? '' }}" class="input-field" placeholder="shipnest"></div>
            <div><label class="form-label">Username</label><input name="db_username" value="{{ $database['db_username'] ?? '' }}" class="input-field"></div>
            <div><label class="form-label">Password</label><input name="db_password" type="password" class="input-field" placeholder="{{ $pwd('db_password') ?: 'Database password' }}" autocomplete="new-password"></div>
        </div>

    @elseif($tab==='payment')
        <h3 class="font-semibold text-gray-800">Payment Methods</h3>
        <div class="flex flex-wrap gap-4 text-sm">
            @foreach(['payment_cod_enabled'=>'COD','payment_sslcommerz_enabled'=>'SSLCommerz','payment_bkash_enabled'=>'bKash','payment_nagad_enabled'=>'Nagad','payment_stripe_enabled'=>'Stripe'] as $k=>$label)
                <label class="flex items-center gap-2"><input type="checkbox" name="{{ $k }}" value="1" @checked(($payment[$k] ?? '0')==='1')> {{ $label }}</label>
            @endforeach
        </div>

        <div class="border-t pt-4" x-data="sslCommerzSettings()">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <h3 class="font-semibold text-gray-800">SSLCommerz</h3>
                @if($paymentMeta['ssl_configured'] ?? false)
                    <span class="text-xs font-medium px-2.5 py-1 rounded-full bg-green-100 text-green-800">Configured — checkout ready</span>
                @else
                    <span class="text-xs font-medium px-2.5 py-1 rounded-full bg-amber-100 text-amber-800">Not configured — add Store ID &amp; Password</span>
                @endif
            </div>

            <p class="text-sm text-gray-600 mb-4">
                SSLCommerz dashboard থেকে <strong>Store ID</strong> ও <strong>Store Password</strong> নিন, এখানে দিন, <strong>Save &amp; Apply</strong> চাপুন।
                তারপর checkout-এ SSLCommerz payment option কাজ করবে।
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Store ID</label>
                    <input name="sslcommerz_store_id" x-ref="storeId" value="{{ $payment['sslcommerz_store_id'] ?? '' }}" placeholder="e.g. testbox" class="input-field">
                    @if(($paymentMeta['ssl_store_id_source'] ?? null) === 'env')
                        <p class="text-xs text-gray-500 mt-1">Currently loaded from <code>.env</code> — save here to store in database.</p>
                    @endif
                </div>
                <div>
                    <label class="form-label">Store Password</label>
                    <input name="sslcommerz_store_password" x-ref="storePassword" type="password" placeholder="{{ $pwd('sslcommerz_store_password') ?: 'Store Password' }}" class="input-field" autocomplete="new-password">
                </div>
                <div class="md:col-span-2">
                    <label class="form-label">API URL</label>
                    <input name="sslcommerz_api_url" x-ref="apiUrl" x-model="apiUrl" value="{{ $payment['sslcommerz_api_url'] ?? 'https://sandbox.sslcommerz.com' }}" placeholder="API URL" class="input-field">
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="sslcommerz_sandbox" value="1" x-ref="sandbox" @change="toggleSandbox($event)" @checked(($payment['sslcommerz_sandbox'] ?? '1')==='1')>
                    Sandbox (test mode)
                </label>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-3">
                <button type="button" class="btn-outline text-sm" @click="testConnection()" :disabled="testing">
                    <span x-show="!testing">Test connection</span>
                    <span x-show="testing">Testing…</span>
                </button>
                <p class="text-xs" x-show="testMessage" x-text="testMessage" :class="testOk ? 'text-green-700' : 'text-red-700'"></p>
            </div>

            <div class="mt-3 space-y-1 text-xs text-gray-500">
                <p>IPN URL (SSLCommerz dashboard-এ দিন): <code>{{ url('/payment/ipn/sslcommerz') }}</code></p>
                <p>Sandbox credentials: <a href="https://developer.sslcommerz.com/registration/" target="_blank" rel="noopener" class="text-[#F57C00] underline">SSLCommerz developer portal</a></p>
            </div>
        </div>

        @push('scripts')
        <script>
            function sslCommerzSettings() {
                return {
                    apiUrl: @json($payment['sslcommerz_api_url'] ?? 'https://sandbox.sslcommerz.com'),
                    testing: false,
                    testMessage: '',
                    testOk: false,
                    toggleSandbox(event) {
                        this.apiUrl = event.target.checked
                            ? 'https://sandbox.sslcommerz.com'
                            : 'https://securepay.sslcommerz.com';
                    },
                    async testConnection() {
                        this.testing = true;
                        this.testMessage = '';
                        try {
                            const response = await fetch(@json(route('admin.settings.sslcommerz.test')), {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({
                                    sslcommerz_store_id: this.$refs.storeId?.value ?? '',
                                    sslcommerz_store_password: this.$refs.storePassword?.value ?? '',
                                    sslcommerz_api_url: this.apiUrl,
                                }),
                            });
                            const data = await response.json();
                            this.testOk = !!data.success;
                            this.testMessage = data.message ?? (data.success ? 'Connected.' : 'Test failed.');
                        } catch (error) {
                            this.testOk = false;
                            this.testMessage = 'Could not reach the server. Try again.';
                        } finally {
                            this.testing = false;
                        }
                    },
                };
            }
        </script>
        @endpush

        <h3 class="font-semibold text-gray-800 border-t pt-4">bKash</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input name="bkash_app_key" value="{{ $payment['bkash_app_key'] ?? '' }}" placeholder="App Key" class="input-field">
            <input name="bkash_app_secret" type="password" placeholder="{{ $pwd('bkash_app_secret') ?: 'App Secret' }}" class="input-field" autocomplete="new-password">
            <input name="bkash_username" value="{{ $payment['bkash_username'] ?? '' }}" placeholder="Username" class="input-field">
            <input name="bkash_password" type="password" placeholder="{{ $pwd('bkash_password') ?: 'Password' }}" class="input-field" autocomplete="new-password">
            <input name="bkash_base_url" value="{{ $payment['bkash_base_url'] ?? '' }}" placeholder="Base URL" class="input-field">
            <input name="bkash_merchant_number" value="{{ $payment['bkash_merchant_number'] ?? '' }}" placeholder="Merchant Number (manual)" class="input-field">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="bkash_sandbox" value="1" @checked(($payment['bkash_sandbox'] ?? '1')==='1')> Sandbox</label>
        </div>

        <h3 class="font-semibold text-gray-800 border-t pt-4">Nagad</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input name="nagad_merchant_id" value="{{ $payment['nagad_merchant_id'] ?? '' }}" placeholder="Merchant ID" class="input-field">
            <input name="nagad_merchant_number" value="{{ $payment['nagad_merchant_number'] ?? '' }}" placeholder="Merchant Number" class="input-field">
            <input name="nagad_base_url" value="{{ $payment['nagad_base_url'] ?? '' }}" placeholder="Base URL" class="input-field md:col-span-2">
            <input name="nagad_challenge" value="{{ $payment['nagad_challenge'] ?? '' }}" placeholder="Challenge" class="input-field">
            <textarea name="nagad_public_key" placeholder="Public Key" class="input-field font-mono text-xs" rows="2">{{ $payment['nagad_public_key'] ?? '' }}</textarea>
            <textarea name="nagad_private_key" placeholder="{{ $pwd('nagad_private_key') ?: 'Private Key' }}" class="input-field font-mono text-xs md:col-span-2" rows="2"></textarea>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="nagad_sandbox" value="1" @checked(($payment['nagad_sandbox'] ?? '1')==='1')> Sandbox</label>
        </div>

        <h3 class="font-semibold text-gray-800 border-t pt-4">Stripe</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input name="stripe_key" value="{{ $payment['stripe_key'] ?? '' }}" placeholder="Publishable Key (pk_...)" class="input-field">
            <input name="stripe_secret" type="password" placeholder="{{ $pwd('stripe_secret') ?: 'Secret Key (sk_...)' }}" class="input-field" autocomplete="new-password">
            <input name="stripe_webhook_secret" type="password" placeholder="{{ $pwd('stripe_webhook_secret') ?: 'Webhook Secret (whsec_...)' }}" class="input-field" autocomplete="new-password">
            <input name="stripe_currency" value="{{ $payment['stripe_currency'] ?? 'usd' }}" placeholder="Currency (usd/bdt)" class="input-field">
        </div>
        <p class="text-xs text-gray-500">Webhook: <code>{{ url('/payment/webhook/stripe') }}</code></p>

    @elseif($tab==='mail')
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div><label class="form-label">Mailer</label><select name="mail_mailer" class="input-field"><option value="smtp" @selected(($mail['mail_mailer'] ?? 'smtp')==='smtp')>SMTP</option><option value="log" @selected(($mail['mail_mailer'] ?? '')==='log')>Log (dev)</option></select></div>
            <input name="mail_host" value="{{ $mail['mail_host'] ?? '' }}" placeholder="SMTP Host" class="input-field">
            <input name="mail_port" value="{{ $mail['mail_port'] ?? '587' }}" placeholder="Port" class="input-field">
            <input name="mail_encryption" value="{{ $mail['mail_encryption'] ?? 'tls' }}" placeholder="Encryption" class="input-field">
            <input name="mail_username" value="{{ $mail['mail_username'] ?? '' }}" placeholder="Username" class="input-field">
            <input name="mail_password" type="password" placeholder="{{ $pwd('mail_password') ?: 'Password' }}" class="input-field" autocomplete="new-password">
            <input name="mail_from_address" value="{{ $mail['mail_from_address'] ?? '' }}" placeholder="From Email" class="input-field">
            <input name="mail_from_name" value="{{ $mail['mail_from_name'] ?? '' }}" placeholder="From Name" class="input-field">
        </div>

    @elseif($tab==='sms')
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div><label class="form-label">Driver</label><select name="sms_driver" class="input-field"><option value="mock" @selected(($sms['sms_driver'] ?? 'mock')==='mock')>Mock (dev)</option><option value="twilio" @selected(($sms['sms_driver'] ?? '')==='twilio')>Twilio</option></select></div>
            <input name="twilio_sid" value="{{ $sms['twilio_sid'] ?? '' }}" placeholder="Twilio SID" class="input-field">
            <input name="twilio_token" type="password" placeholder="{{ $pwd('twilio_token') ?: 'Twilio Token' }}" class="input-field" autocomplete="new-password">
            <input name="twilio_from" value="{{ $sms['twilio_from'] ?? '' }}" placeholder="Twilio From Number" class="input-field">
            <input name="bulksms_api_key" value="{{ $sms['bulksms_api_key'] ?? '' }}" placeholder="BulkSMS API Key" class="input-field">
        </div>

    @elseif($tab==='integrations')
        <h3 class="font-semibold text-gray-800">Social Login</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input name="google_client_id" value="{{ $integrations['google_client_id'] ?? '' }}" placeholder="Google Client ID" class="input-field">
            <input name="google_client_secret" type="password" placeholder="{{ $pwd('google_client_secret') ?: 'Google Client Secret' }}" class="input-field" autocomplete="new-password">
            <input name="google_redirect_uri" value="{{ $integrations['google_redirect_uri'] ?? url('/auth/google/callback') }}" placeholder="Google Redirect URI" class="input-field md:col-span-2">
            <input name="facebook_client_id" value="{{ $integrations['facebook_client_id'] ?? '' }}" placeholder="Facebook App ID" class="input-field">
            <input name="facebook_client_secret" type="password" placeholder="{{ $pwd('facebook_client_secret') ?: 'Facebook App Secret' }}" class="input-field" autocomplete="new-password">
            <input name="facebook_redirect_uri" value="{{ $integrations['facebook_redirect_uri'] ?? url('/auth/facebook/callback') }}" placeholder="Facebook Redirect URI" class="input-field md:col-span-2">
        </div>

        <h3 class="font-semibold text-gray-800 border-t pt-4">Search (MeiliSearch)</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <select name="scout_driver" class="input-field"><option value="collection" @selected(($integrations['scout_driver'] ?? 'collection')==='collection')>Collection (dev)</option><option value="meilisearch" @selected(($integrations['scout_driver'] ?? '')==='meilisearch')>MeiliSearch</option></select>
            <input name="meilisearch_host" value="{{ $integrations['meilisearch_host'] ?? '' }}" placeholder="MeiliSearch Host" class="input-field">
            <input name="meilisearch_key" type="password" placeholder="{{ $pwd('meilisearch_key') ?: 'MeiliSearch Key' }}" class="input-field md:col-span-2" autocomplete="new-password">
        </div>

        <h3 class="font-semibold text-gray-800 border-t pt-4">Pusher (Realtime)</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input name="pusher_app_id" value="{{ $integrations['pusher_app_id'] ?? '' }}" placeholder="Pusher App ID" class="input-field">
            <input name="pusher_app_key" value="{{ $integrations['pusher_app_key'] ?? '' }}" placeholder="Pusher App Key" class="input-field">
            <input name="pusher_app_secret" type="password" placeholder="{{ $pwd('pusher_app_secret') ?: 'Pusher App Secret' }}" class="input-field" autocomplete="new-password">
            <input name="pusher_app_cluster" value="{{ $integrations['pusher_app_cluster'] ?? 'mt1' }}" placeholder="Cluster" class="input-field">
        </div>

        <h3 class="font-semibold text-gray-800 border-t pt-4">AWS S3 Storage</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input name="aws_access_key_id" value="{{ $integrations['aws_access_key_id'] ?? '' }}" placeholder="AWS Access Key ID" class="input-field">
            <input name="aws_secret_access_key" type="password" placeholder="{{ $pwd('aws_secret_access_key') ?: 'AWS Secret Key' }}" class="input-field" autocomplete="new-password">
            <input name="aws_default_region" value="{{ $integrations['aws_default_region'] ?? 'us-east-1' }}" placeholder="Region" class="input-field">
            <input name="aws_bucket" value="{{ $integrations['aws_bucket'] ?? '' }}" placeholder="Bucket Name" class="input-field">
        </div>

        <h3 class="font-semibold text-gray-800 border-t pt-4">Redis</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input name="redis_host" value="{{ $integrations['redis_host'] ?? '' }}" placeholder="Redis Host" class="input-field">
            <input name="redis_port" value="{{ $integrations['redis_port'] ?? '6379' }}" placeholder="Redis Port" class="input-field">
            <input name="redis_password" type="password" placeholder="{{ $pwd('redis_password') ?: 'Redis Password' }}" class="input-field md:col-span-2" autocomplete="new-password">
        </div>

    @elseif($tab==='commission')
        <label class="form-label">Default merchant commission rate (%)</label>
        <input name="default_commission_rate" type="number" step="0.01" min="0" max="100" value="{{ $commission['default_commission_rate'] ?? 10 }}" class="input-field w-40">
    @endif

    <div class="pt-4 border-t">
        <button class="btn-primary">Save & Apply Settings</button>
    </div>
</form>
@endif

<div class="mt-8 bg-white rounded-xl ring-1 ring-gray-200 p-6 max-w-lg">
    <h2 class="font-semibold mb-2">Two-Factor Authentication</h2>
    <p class="text-sm text-gray-600 mb-3">@if(auth()->user()->google2fa_enabled) 2FA is enabled. @else Protect your admin account with Google Authenticator. @endif</p>
    @if(auth()->user()->google2fa_enabled)
        <form action="{{ route('admin.2fa.disable') }}" method="POST">@csrf<button class="btn-outline text-sm text-red-600">Disable 2FA</button></form>
    @else
        <a href="{{ route('admin.2fa.setup') }}" class="btn-primary inline-block text-sm">Set up 2FA</a>
    @endif
</div>
@endsection
