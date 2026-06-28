<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Redis;

class SettingService
{
    protected string $prefix = 'shipnest:settings';

    /** @var array<int, string> */
    protected array $secureKeys = [
        'db_password',
        'sslcommerz_store_password',
        'bkash_app_secret',
        'bkash_password',
        'nagad_private_key',
        'stripe_secret',
        'stripe_webhook_secret',
        'mail_password',
        'twilio_token',
        'google_client_secret',
        'facebook_client_secret',
        'pusher_app_secret',
        'aws_secret_access_key',
        'meilisearch_key',
        'redis_password',
    ];

    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("{$this->prefix}:key:{$key}", 3600, function () use ($key, $default) {
            $setting = Setting::query()->where('key', $key)->first();

            return $setting?->value ?? $default;
        });
    }

    public function set(string $key, mixed $value, string $group = 'general'): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => is_array($value) ? json_encode($value) : (string) $value, 'group' => $group]
        );

        Cache::forget("{$this->prefix}:key:{$key}");
        Cache::forget("{$this->prefix}:group:{$group}");
        Cache::forget("{$this->prefix}:all");
    }

    public function setSecure(string $key, ?string $value, string $group = 'general'): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $this->set($key, Crypt::encryptString($value), $group);
    }

    public function getSecure(string $key, string $group, mixed $default = null): ?string
    {
        $value = $this->getGroup($group)[$key] ?? null;

        if ($value === null || $value === '') {
            return $default !== null ? (string) $default : null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    public function hasSecure(string $key, string $group): bool
    {
        $value = $this->getGroup($group)[$key] ?? null;

        return filled($value);
    }

    public function isSecureKey(string $key): bool
    {
        return in_array($key, $this->secureKeys, true);
    }

    public function mergedGroup(string $group, array $defaults = []): array
    {
        return array_merge($defaults, $this->getGroup($group));
    }

    public function persist(Request $request, array $fields, string $group, array $secureFields = [], array $checkboxFields = []): void
    {
        $data = [];

        foreach ($fields as $field) {
            if (in_array($field, $secureFields, true)) {
                if ($request->filled($field)) {
                    $this->setSecure($field, $request->input($field), $group);
                }

                continue;
            }

            if (in_array($field, $checkboxFields, true)) {
                $data[$field] = $request->boolean($field) ? '1' : '0';

                continue;
            }

            if ($request->has($field)) {
                $data[$field] = $request->input($field);
            }
        }

        if ($data !== []) {
            $this->setMany($data, $group);
        }
    }

    public function getGroup(string $group): array
    {
        return Cache::remember("{$this->prefix}:group:{$group}", 3600, function () use ($group) {
            return Setting::query()->where('group', $group)->pluck('value', 'key')->toArray();
        });
    }

    public function setMany(array $settings, string $group = 'general'): void
    {
        foreach ($settings as $key => $value) {
            $this->set($key, $value, $group);
        }
    }

    public function all(): array
    {
        return Cache::remember("{$this->prefix}:all", 3600, function () {
            return Setting::query()->pluck('value', 'key')->toArray();
        });
    }

    public function flush(): void
    {
        $keys = Setting::query()->get(['key', 'group']);

        foreach ($keys as $setting) {
            Cache::forget("{$this->prefix}:key:{$setting->key}");
            Cache::forget("{$this->prefix}:group:{$setting->group}");
        }

        Cache::forget("{$this->prefix}:all");

        try {
            if (config('cache.default') === 'redis') {
                $redisKeys = Redis::connection()->keys("*{$this->prefix}*");
                foreach ($redisKeys as $key) {
                    Redis::connection()->del($key);
                }
            }
        } catch (\Throwable) {
            // Redis optional
        }
    }
}
