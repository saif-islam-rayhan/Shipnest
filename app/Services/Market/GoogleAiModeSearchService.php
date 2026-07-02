<?php

namespace App\Services\Market;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleAiModeSearchService
{
    public function isAvailable(): bool
    {
        return config('market.use_google_ai_mode') && (bool) config('market.serpapi_key');
    }

    /**
     * @return array{
     *     ai_answer: string,
     *     text_blocks: array<int, mixed>,
     *     references: array<int, mixed>,
     *     shopping_results: array<int, mixed>,
     *     inline_products: array<int, mixed>,
     *     subsequent_request_token: ?string,
     *     results: array<int, array<string, mixed>>,
     *     search_source: string
     * }
     */
    public function search(string $query, ?string $subsequentToken = null, bool $continuable = true): array
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException('Google AI Mode requires SERPAPI_KEY and USE_GOOGLE_AI_MODE=true');
        }

        $params = [
            'engine' => 'google_ai_mode',
            'q' => $query,
            'api_key' => config('market.serpapi_key'),
            'gl' => config('market.google_ai_mode_gl', 'bd'),
            'hl' => config('market.google_ai_mode_hl', 'en'),
            'location' => config('market.google_ai_mode_location', 'Dhaka, Bangladesh'),
        ];

        if ($continuable) {
            $params['continuable'] = 'true';
        }

        if ($subsequentToken) {
            $params['subsequent_request_token'] = $subsequentToken;
        }

        $response = Http::timeout(60)->get('https://serpapi.com/search.json', $params);

        if (! $response->successful()) {
            throw new RuntimeException('Google AI Mode HTTP '.$response->status());
        }

        $data = $response->json();
        $status = $data['search_metadata']['status'] ?? 'Success';

        if ($status === 'Error' || ! empty($data['error'])) {
            throw new RuntimeException('Google AI Mode error: '.($data['error'] ?? 'unknown'));
        }

        $aiAnswer = trim($data['reconstructed_markdown'] ?? '');
        if (! $aiAnswer && ! empty($data['text_blocks'])) {
            $aiAnswer = $this->flattenTextBlocks($data['text_blocks']);
        }

        $results = $this->normalizeToResults($data, $aiAnswer, $query);

        return [
            'ai_answer' => $aiAnswer,
            'text_blocks' => $data['text_blocks'] ?? [],
            'references' => $data['references'] ?? [],
            'shopping_results' => $data['shopping_results'] ?? [],
            'inline_products' => $data['inline_products'] ?? [],
            'subsequent_request_token' => $data['subsequent_request_token'] ?? null,
            'results' => $results,
            'search_source' => 'google_ai_mode',
        ];
    }

    /**
     * @param  array<int, mixed>  $textBlocks
     */
    private function flattenTextBlocks(array $textBlocks): string
    {
        $parts = [];
        foreach ($textBlocks as $block) {
            if (! is_array($block)) {
                continue;
            }
            if (! empty($block['snippet'])) {
                $parts[] = $block['snippet'];
            } elseif (! empty($block['paragraph'])) {
                $parts[] = $block['paragraph'];
            } elseif (! empty($block['list']) && is_array($block['list'])) {
                foreach ($block['list'] as $item) {
                    if (is_string($item)) {
                        $parts[] = $item;
                    } elseif (is_array($item) && ! empty($item['snippet'])) {
                        $parts[] = $item['snippet'];
                    }
                }
            }
        }

        return trim(implode("\n\n", $parts));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeToResults(array $data, string $aiAnswer, string $query): array
    {
        $results = [];

        if ($aiAnswer) {
            $results[] = [
                'title' => 'Google AI Mode Answer',
                'snippet' => mb_substr($aiAnswer, 0, 2000),
                'url' => '',
                'source' => 'google_ai_mode',
                'site' => 'Google AI Mode',
            ];
        }

        foreach ($data['references'] ?? [] as $ref) {
            $url = $ref['link'] ?? $ref['url'] ?? '';
            $title = $ref['title'] ?? $ref['source'] ?? '';
            if (! $title && ! $url) {
                continue;
            }
            $results[] = [
                'title' => $title,
                'snippet' => $ref['snippet'] ?? '',
                'url' => $url,
                'source' => 'google_ai_mode_ref',
                'site' => $ref['source'] ?? 'Web',
            ];
        }

        foreach ($data['shopping_results'] ?? [] as $item) {
            $title = $item['title'] ?? '';
            if (! $title) {
                continue;
            }
            $price = $item['price'] ?? '';
            $results[] = [
                'title' => $title,
                'snippet' => trim($price.' '.($item['source'] ?? '')),
                'url' => $item['product_link'] ?? $item['link'] ?? '',
                'source' => 'google_ai_mode_shopping',
                'site' => $item['source'] ?? 'Shopping',
                'price' => $item['extracted_price'] ?? null,
            ];
        }

        foreach ($data['inline_products'] ?? [] as $item) {
            $title = $item['title'] ?? $item['name'] ?? '';
            if (! $title) {
                continue;
            }
            $results[] = [
                'title' => $title,
                'snippet' => $item['price'] ?? '',
                'url' => $item['link'] ?? $item['product_link'] ?? '',
                'source' => 'google_ai_mode_product',
                'site' => $item['source'] ?? 'Shopping',
            ];
        }

        if (empty($results)) {
            throw new RuntimeException("Google AI Mode returned no usable content for `{$query}`");
        }

        return array_slice($results, 0, 15);
    }
}
