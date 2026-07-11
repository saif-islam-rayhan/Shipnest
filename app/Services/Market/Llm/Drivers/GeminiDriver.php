<?php

namespace App\Services\Market\Llm\Drivers;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiDriver
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
            'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $userPrompt]]]],
            'generationConfig' => [
                'temperature' => $temperature,
                'responseMimeType' => $jsonMode ? 'application/json' : 'text/plain',
            ],
        ];

        $response = $this->request($provider, $model, $payload, 90);

        return $response->json('candidates.0.content.parts.0.text', '');
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
        $parts = [['text' => $textPrompt]];

        foreach ($imageBase64List as $dataUri) {
            if (preg_match('/^data:(.*?);base64,(.*)$/', $dataUri, $matches)) {
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => $matches[1],
                        'data' => $matches[2],
                    ],
                ];
            }
        }

        $payload = [
            'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents' => [['role' => 'user', 'parts' => $parts]],
            'generationConfig' => [
                'temperature' => $temperature,
                'responseMimeType' => $jsonMode ? 'application/json' : 'text/plain',
            ],
        ];

        $response = $this->request($provider, $model, $payload, 120);

        return $response->json('candidates.0.content.parts.0.text', '');
    }

    /**
     * @param  array<string, mixed>  $provider
     * @param  array<string, mixed>  $payload
     */
    private function request(array $provider, string $model, array $payload, int $timeout): \Illuminate\Http\Client\Response
    {
        $baseUrl = rtrim((string) ($provider['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $url = "{$baseUrl}/models/{$model}:generateContent";

        $response = Http::timeout($timeout)
            ->withQueryParameters(['key' => (string) $provider['api_key']])
            ->post($url, $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Gemini call failed: '.$response->body());
        }

        return $response;
    }
}
