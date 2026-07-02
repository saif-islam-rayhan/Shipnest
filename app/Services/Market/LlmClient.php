<?php

namespace App\Services\Market;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class LlmClient
{
    public function chat(
        string $model,
        string $systemPrompt,
        string $userPrompt,
        bool $jsonMode = false,
        float $temperature = 0.3,
    ): string {
        if (! config('market.use_live_llm') || ! config('market.github_token')) {
            throw new RuntimeException('USE_LIVE_LLM=true এবং GITHUB_TOKEN সেট করুন।');
        }

        $payload = [
            'model' => $model,
            'temperature' => $temperature,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ];

        if ($jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::timeout(90)
            ->withToken(config('market.github_token'))
            ->post(config('market.github_models_endpoint').'/chat/completions', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('LLM call failed: '.$response->body());
        }

        return $response->json('choices.0.message.content', '');
    }
}
