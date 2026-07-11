<?php

namespace App\Services\Market\Llm\Drivers;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class AnthropicDriver
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
            'max_tokens' => 1024,
            'temperature' => $temperature,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ];

        $response = $this->request($provider, $payload, 90);

        return $this->extractText($response->json());
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
            if (preg_match('/^data:(.*?);base64,(.*)$/', $dataUri, $matches)) {
                $content[] = [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => $matches[1],
                        'data' => $matches[2],
                    ],
                ];
            }
        }

        $payload = [
            'model' => $model,
            'max_tokens' => 1024,
            'temperature' => $temperature,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $content],
            ],
        ];

        $response = $this->request($provider, $payload, 120);

        return $this->extractText($response->json());
    }

    /**
     * @param  array<string, mixed>  $provider
     * @param  array<string, mixed>  $payload
     */
    private function request(array $provider, array $payload, int $timeout): \Illuminate\Http\Client\Response
    {
        $baseUrl = rtrim((string) ($provider['base_url'] ?? 'https://api.anthropic.com/v1'), '/');
        $url = "{$baseUrl}/messages";

        $response = Http::timeout($timeout)
            ->withHeaders([
                'x-api-key' => (string) $provider['api_key'],
                'anthropic-version' => '2023-06-01',
            ])
            ->post($url, $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Anthropic call failed: '.$response->body());
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function extractText(?array $json): string
    {
        $blocks = $json['content'] ?? [];

        $text = '';
        foreach ($blocks as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'] ?? '';
            }
        }

        return $text;
    }
}
