<?php

namespace App\Services\Market\Llm\Drivers;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiCompatibleDriver
{
    /**
     * @param  array<string, mixed>  $provider
     */
    public function chat(
        array $provider,
        string $systemPrompt,
        string $userPrompt,
        string $model,
        bool $jsonMode = false,
        float $temperature = 0.3,
    ): string {
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

        $response = $this->request($provider, $payload, 90);

        return $response->json('choices.0.message.content', '');
    }

    /**
     * @param  array<string, mixed>  $provider
     * @param  array<int, string>  $imageBase64List
     */
    public function chatWithImages(
        array $provider,
        string $systemPrompt,
        string $textPrompt,
        array $imageBase64List,
        string $model,
        bool $jsonMode = false,
        float $temperature = 0.3,
    ): string {
        $content = [['type' => 'text', 'text' => $textPrompt]];

        foreach ($imageBase64List as $dataUri) {
            $content[] = [
                'type' => 'image_url',
                'image_url' => ['url' => $dataUri],
            ];
        }

        $payload = [
            'model' => $model,
            'temperature' => $temperature,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $content],
            ],
        ];

        if ($jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = $this->request($provider, $payload, 120);

        return $response->json('choices.0.message.content', '');
    }

    /**
     * @param  array<string, mixed>  $provider
     * @param  array<string, mixed>  $payload
     */
    private function request(array $provider, array $payload, int $timeout): \Illuminate\Http\Client\Response
    {
        $baseUrl = rtrim((string) ($provider['base_url'] ?? ''), '/');

        if ($baseUrl === '') {
            throw new RuntimeException(($provider['label'] ?? 'Provider').' base URL সেট করুন।');
        }

        $url = str_ends_with($baseUrl, '/chat/completions')
            ? $baseUrl
            : $baseUrl.'/chat/completions';

        $response = Http::timeout($timeout)
            ->withToken((string) $provider['api_key'])
            ->post($url, $payload);

        if (! $response->successful()) {
            throw new RuntimeException('LLM call failed: '.$response->body());
        }

        return $response;
    }
}
