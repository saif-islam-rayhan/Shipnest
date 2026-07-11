<?php

namespace App\Services\Market\Llm;

use App\Services\Market\Llm\Drivers\AnthropicDriver;
use App\Services\Market\Llm\Drivers\GeminiDriver;
use App\Services\Market\Llm\Drivers\OpenAiCompatibleDriver;
use App\Services\SettingService;
use Illuminate\Support\Carbon;
use RuntimeException;

class LlmProviderManager
{
    private const GROUP = 'llm_providers';

    public function __construct(
        private readonly SettingService $settings,
        private readonly OpenAiCompatibleDriver $openAiCompatible,
        private readonly GeminiDriver $gemini,
        private readonly AnthropicDriver $anthropic,
    ) {}

    public function isLiveEnabled(): bool
    {
        return filter_var(config('market.use_live_llm'), FILTER_VALIDATE_BOOLEAN);
    }

    public function isReady(): bool
    {
        if (! $this->isLiveEnabled()) {
            return false;
        }

        try {
            $this->resolveActive();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function activeProviderId(): ?string
    {
        $active = $this->stored('active_provider');

        if ($active && $this->isProviderConfigured($active)) {
            return $active;
        }

        foreach (array_keys(config('llm_providers', [])) as $id) {
            if ($this->isProviderConfigured($id) && $this->stored("{$id}_enabled") === '1') {
                return $id;
            }
        }

        foreach (array_keys(config('llm_providers', [])) as $id) {
            if ($this->isProviderConfigured($id)) {
                return $id;
            }
        }

        return null;
    }

    public function textModel(?string $override = null): string
    {
        if ($override) {
            return $override;
        }

        $active = $this->resolveActive();

        return $active['model'] ?: config('market.model_google_search', 'gpt-4o-mini');
    }

    public function visionModel(?string $override = null): string
    {
        if ($override) {
            return $override;
        }

        $active = $this->resolveActive();

        return $active['vision_model']
            ?: $active['model']
            ?: config('market.model_vision', config('market.model_google_search', 'gpt-4o-mini'));
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveActive(): array
    {
        $id = $this->activeProviderId();

        if (! $id) {
            throw new RuntimeException('কোনো Text AI provider configured নেই। Settings → Agent / AI থেকে API key দিন।');
        }

        $meta = config("llm_providers.{$id}", []);
        $apiKey = $this->apiKey($id);

        if (! $apiKey) {
            throw new RuntimeException("{$meta['label']} provider-এর API key সেট করুন।");
        }

        $baseUrl = rtrim($this->baseUrl($id, $meta), '/');
        $model = $this->resolveModelChoice($id, $meta, 'models', 'default_model');
        $visionModel = $this->resolveModelChoice($id, $meta, 'vision_models', 'default_vision_model', $model);

        return [
            'id' => $id,
            'label' => $meta['label'] ?? $id,
            'driver' => $meta['driver'] ?? 'openai_compatible',
            'base_url' => $baseUrl,
            'api_key' => $apiKey,
            'model' => $model,
            'vision_model' => $visionModel,
            'supports_vision' => (bool) ($meta['supports_vision'] ?? false),
        ];
    }

    public function chat(
        string $systemPrompt,
        string $userPrompt,
        ?string $model = null,
        bool $jsonMode = false,
        float $temperature = 0.3,
    ): string {
        $provider = $this->resolveActive();
        $model = $this->textModel($model);

        return match ($provider['driver']) {
            'gemini' => $this->gemini->chat($provider, $systemPrompt, $userPrompt, $model, $jsonMode, $temperature),
            'anthropic' => $this->anthropic->chat($provider, $systemPrompt, $userPrompt, $model, $jsonMode, $temperature),
            default => $this->openAiCompatible->chat($provider, $systemPrompt, $userPrompt, $model, $jsonMode, $temperature),
        };
    }

    /**
     * @param  array<int, string>  $imageBase64List
     */
    public function chatWithImages(
        string $systemPrompt,
        string $textPrompt,
        array $imageBase64List,
        ?string $model = null,
        bool $jsonMode = false,
        float $temperature = 0.3,
    ): string {
        $provider = $this->resolveActive();
        $model = $this->visionModel($model);

        if (! $provider['supports_vision']) {
            throw new RuntimeException($provider['label'].' vision/image support করে না।');
        }

        return match ($provider['driver']) {
            'gemini' => $this->gemini->chatWithImages($provider, $systemPrompt, $textPrompt, $imageBase64List, $model, $jsonMode, $temperature),
            'anthropic' => $this->anthropic->chatWithImages($provider, $systemPrompt, $textPrompt, $imageBase64List, $model, $jsonMode, $temperature),
            default => $this->openAiCompatible->chatWithImages($provider, $systemPrompt, $textPrompt, $imageBase64List, $model, $jsonMode, $temperature),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function cardsForAdmin(): array
    {
        $activeId = $this->stored('active_provider');
        $cards = [];

        foreach (config('llm_providers', []) as $id => $meta) {
            $configured = $this->isProviderConfigured($id);
            $enabled = $this->stored("{$id}_enabled") === '1';
            $isActive = $activeId === $id && $configured && $enabled;
            $testStatus = $this->stored("{$id}_test_status") ?: 'not_tested';
            $lastTested = $this->stored("{$id}_last_tested_at");

            $cards[] = [
                'id' => $id,
                'label' => $meta['label'],
                'tagline' => $meta['tagline'],
                'icon' => $meta['icon'],
                'icon_bg' => $meta['icon_bg'],
                'driver' => $meta['driver'],
                'default_model' => $meta['default_model'],
                'default_vision_model' => $meta['default_vision_model'] ?? $meta['default_model'],
                'models' => $meta['models'] ?? [],
                'vision_models' => $meta['vision_models'] ?? ($meta['models'] ?? []),
                'requires_base_url' => (bool) ($meta['requires_base_url'] ?? false),
                'default_base_url' => $meta['base_url'] ?? '',
                'supports_vision' => (bool) ($meta['supports_vision'] ?? false),
                'configured' => $configured,
                'enabled' => $enabled,
                'active' => $isActive,
                'model' => $this->resolveModelChoice($id, $meta, 'models', 'default_model'),
                'model_label' => $this->modelLabel($id, $meta, 'models', 'default_model'),
                'vision_model' => $this->resolveModelChoice($id, $meta, 'vision_models', 'default_vision_model'),
                'vision_model_label' => $this->modelLabel($id, $meta, 'vision_models', 'default_vision_model'),
                'base_url' => $this->baseUrl($id, $meta),
                'has_api_key' => (bool) $this->apiKey($id),
                'test_status' => $testStatus,
                'last_tested_human' => $lastTested ? Carbon::parse($lastTested)->diffForHumans() : null,
            ];
        }

        return $cards;
    }

    public function saveProvider(string $providerId, array $data): void
    {
        if (! isset(config('llm_providers')[$providerId])) {
            throw new RuntimeException('Unknown LLM provider.');
        }

        if (! empty($data['api_key'])) {
            $this->settings->setSecure("llm_{$providerId}_api_key", $data['api_key'], self::GROUP);
        }

        if (! empty($data['model'])) {
            $this->assertAllowedModel($providerId, $data['model'], 'models');
            $this->set("{$providerId}_model", $data['model']);
        }

        if (! empty($data['vision_model'])) {
            $this->assertAllowedModel($providerId, $data['vision_model'], 'vision_models');
            $this->set("{$providerId}_vision_model", $data['vision_model']);
        }

        if (array_key_exists('base_url', $data)) {
            $this->set("{$providerId}_base_url", $data['base_url'] ?? '');
        }

        if (array_key_exists('enabled', $data) && $data['enabled'] !== null) {
            if ($data['enabled'] && ! $this->isProviderConfigured($providerId)) {
                throw new RuntimeException('API key প্রয়োজন।');
            }

            if ($data['enabled']) {
                $this->disableOtherProviders($providerId);
                $this->set('active_provider', $providerId);
            }
            $this->set("{$providerId}_enabled", $data['enabled'] ? '1' : '0');
        }
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function testProvider(string $providerId): array
    {
        if (! isset(config('llm_providers')[$providerId])) {
            return ['success' => false, 'message' => 'Unknown provider.'];
        }

        if (! $this->isLiveEnabled()) {
            return ['success' => false, 'message' => 'Enable live LLM first.'];
        }

        if (! $this->isProviderConfigured($providerId)) {
            return ['success' => false, 'message' => 'Configure API key first.'];
        }

        $previousActive = $this->stored('active_provider');
        $wasEnabled = $this->stored("{$providerId}_enabled") === '1';

        $this->set('active_provider', $providerId);
        $this->set("{$providerId}_enabled", '1');

        try {
            $reply = trim($this->chat(
                'You are a test assistant.',
                'Reply with exactly OK',
                null,
                false,
                0,
            ));

            $ok = stripos($reply, 'ok') !== false;
            $this->set("{$providerId}_test_status", $ok ? 'passed' : 'failed');
            $this->set("{$providerId}_last_tested_at", now()->toIso8601String());

            return [
                'success' => $ok,
                'message' => $ok ? 'Test passed.' : 'Unexpected response: '.$reply,
            ];
        } catch (\Throwable $e) {
            $this->set("{$providerId}_test_status", 'failed');
            $this->set("{$providerId}_last_tested_at", now()->toIso8601String());

            return ['success' => false, 'message' => $e->getMessage()];
        } finally {
            if (! $wasEnabled) {
                $this->set("{$providerId}_enabled", '0');
            }
            if ($previousActive) {
                $this->set('active_provider', $previousActive);
            }
        }
    }

    public function migrateLegacyGithubConfig(): void
    {
        if ($this->isProviderConfigured('github_models')) {
            return;
        }

        if (! filled(env('GITHUB_TOKEN'))) {
            return;
        }

        $this->settings->setSecure('llm_github_models_api_key', env('GITHUB_TOKEN'), self::GROUP);

        if (filled(env('GITHUB_MODELS_ENDPOINT'))) {
            $this->set('github_models_base_url', env('GITHUB_MODELS_ENDPOINT'));
        }

        if (filled(env('MODEL_GOOGLE_SEARCH'))) {
            $this->set('github_models_model', env('MODEL_GOOGLE_SEARCH'));
        }

        if (filled(env('MODEL_VISION'))) {
            $this->set('github_models_vision_model', env('MODEL_VISION'));
        }

        if (filter_var(env('USE_LIVE_LLM', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->set('github_models_enabled', '1');
            $this->set('active_provider', 'github_models');
        }
    }

    public function applyRuntimeConfig(): void
    {
        $activeId = $this->activeProviderId();

        if (! $activeId) {
            return;
        }

        try {
            $active = $this->resolveActive();
            config([
                'market.github_token' => $active['api_key'],
                'market.github_models_endpoint' => $active['base_url'],
                'market.model_google_search' => $active['model'],
                'market.model_vision' => $active['vision_model'],
                'market.llm_active_provider' => $activeId,
            ]);
        } catch (\Throwable) {
            // Keep env fallbacks when active provider is incomplete.
        }
    }

    private function disableOtherProviders(string $exceptId): void
    {
        foreach (array_keys(config('llm_providers', [])) as $id) {
            if ($id !== $exceptId) {
                $this->set("{$id}_enabled", '0');
            }
        }
    }

    private function isProviderConfigured(string $id): bool
    {
        return filled($this->apiKey($id));
    }

    private function apiKey(string $id): ?string
    {
        $fromDb = $this->settings->getSecure("llm_{$id}_api_key", self::GROUP);
        if ($fromDb) {
            return $fromDb;
        }

        $legacy = $id === 'github_models' ? $this->settings->getSecure('github_token', 'agent') : null;
        if ($legacy) {
            return $legacy;
        }

        $envKey = config("llm_providers.{$id}.env_api_key");
        if ($envKey && filled(env($envKey))) {
            return (string) env($envKey);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function baseUrl(string $id, array $meta): string
    {
        $custom = $this->stored("{$id}_base_url");
        if ($custom) {
            return $custom;
        }

        if ($id === 'github_models') {
            $legacy = $this->stored("{$id}_base_url")
                ?: ($this->settings->getGroup('agent')['github_models_endpoint'] ?? null)
                ?: config('market.github_models_endpoint');

            return (string) ($legacy ?: $meta['base_url']);
        }

        $envBase = $meta['env_base_url'] ?? null;
        if ($envBase && filled(env($envBase))) {
            return (string) env($envBase);
        }

        return (string) ($meta['base_url'] ?? '');
    }

    private function stored(string $key, mixed $default = null): mixed
    {
        return $this->settings->getGroup(self::GROUP)[$key] ?? $default;
    }

    private function set(string $key, mixed $value): void
    {
        $this->settings->set($key, $value, self::GROUP);
    }

    private function assertAllowedModel(string $providerId, string $model, string $listKey): void
    {
        $allowed = array_keys(config("llm_providers.{$providerId}.{$listKey}", []));

        if ($allowed === []) {
            return;
        }

        if (! in_array($model, $allowed, true)) {
            throw new RuntimeException('Invalid model selected for this provider.');
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function resolveModelChoice(string $id, array $meta, string $listKey, string $defaultKey, ?string $fallback = null): string
    {
        $stored = $this->stored("{$id}_".($listKey === 'models' ? 'model' : 'vision_model'));
        $default = $meta[$defaultKey] ?? $fallback ?? $meta['default_model'] ?? 'gpt-4o-mini';
        $allowed = array_keys($meta[$listKey] ?? []);

        if ($stored && ($allowed === [] || in_array($stored, $allowed, true))) {
            return $stored;
        }

        if ($default && ($allowed === [] || in_array($default, $allowed, true))) {
            return $default;
        }

        return $allowed[0] ?? $default;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function modelLabel(string $id, array $meta, string $listKey, string $defaultKey): string
    {
        $modelId = $this->resolveModelChoice(
            $id,
            $meta,
            $listKey,
            $defaultKey,
            $meta['default_model'] ?? null,
        );

        return $meta[$listKey][$modelId] ?? $modelId;
    }
}
