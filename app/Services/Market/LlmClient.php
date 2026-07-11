<?php

namespace App\Services\Market;

use App\Services\Market\Llm\LlmProviderManager;
use RuntimeException;

class LlmClient
{
    public function __construct(
        private readonly LlmProviderManager $providers,
    ) {}

    public function chat(
        string $model,
        string $systemPrompt,
        string $userPrompt,
        bool $jsonMode = false,
        float $temperature = 0.3,
    ): string {
        if (! $this->providers->isLiveEnabled()) {
            throw new RuntimeException('Live LLM disabled. Settings → Agent / AI থেকে Enable করুন।');
        }

        if (! $this->providers->isReady()) {
            throw new RuntimeException('কোনো Text AI provider configured নেই। Settings → Agent / AI থেকে API key দিন।');
        }

        return $this->providers->chat(
            $systemPrompt,
            $userPrompt,
            $model ?: null,
            $jsonMode,
            $temperature,
        );
    }

    /**
     * @param  array<int, string>  $imageBase64List  Each item: data URI (data:image/...;base64,...)
     */
    public function chatWithImages(
        string $model,
        string $systemPrompt,
        string $textPrompt,
        array $imageBase64List,
        bool $jsonMode = false,
        float $temperature = 0.3,
    ): string {
        if (! $this->providers->isLiveEnabled()) {
            throw new RuntimeException('Live LLM disabled. Settings → Agent / AI থেকে Enable করুন।');
        }

        if (! $this->providers->isReady()) {
            throw new RuntimeException('কোনো Text AI provider configured নেই। Settings → Agent / AI থেকে API key দিন।');
        }

        return $this->providers->chatWithImages(
            $systemPrompt,
            $textPrompt,
            $imageBase64List,
            $model ?: null,
            $jsonMode,
            $temperature,
        );
    }
}
