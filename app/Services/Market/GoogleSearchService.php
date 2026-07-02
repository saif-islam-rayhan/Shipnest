<?php

namespace App\Services\Market;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GoogleSearchService
{
    private const USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    public function search(string $query, ?int $maxResults = null): array
    {
        $max = $maxResults ?? config('market.google_search_max_results');
        $backend = config('market.search_backend', 'duckduckgo');

        if (in_array($backend, ['duckduckgo', 'searxng', 'free'], true)) {
            return $this->searchFree($query, $max, $backend);
        }

        $errors = [];

        if (config('market.tavily_api_key')) {
            try {
                $hits = $this->searchTavily($query, $max);
                if ($hits) {
                    return $hits;
                }
                $errors[] = 'Tavily: no results';
            } catch (\Throwable $e) {
                $errors[] = 'Tavily: '.$e->getMessage();
            }
        }

        if (config('market.serpapi_key')) {
            try {
                $hits = $this->searchSerpApi($query, $max);
                if ($hits) {
                    return $hits;
                }
                $errors[] = 'SerpAPI: no results';
            } catch (\Throwable $e) {
                $errors[] = 'SerpAPI: '.$e->getMessage();
            }
        }

        foreach (['searchDuckDuckGoHtml', 'searchDuckDuckGoGet', 'searchViaJinaProxy'] as $method) {
            try {
                $hits = $this->{$method}($query, $max);
                if ($hits) {
                    return $hits;
                }
                $errors[] = "{$method}: no results";
            } catch (\Throwable $e) {
                $errors[] = "{$method}: ".$e->getMessage();
                Log::debug("Search {$method} failed for [{$query}]: ".$e->getMessage());
            }
        }

        throw new RuntimeException(
            'No web search results for `'.$query.'`. Details: '.implode('; ', array_slice($errors, -4))
        );
    }

    /**
     * Free search backends only — DuckDuckGo and/or SearXNG (no paid APIs).
     */
    public function searchFree(string $query, int $max, ?string $backend = null): array
    {
        $backend = $backend ?? config('market.search_backend', 'duckduckgo');
        $errors = [];
        $methods = [];

        if ($backend === 'searxng' && config('market.searxng_url')) {
            $methods[] = 'searchSearXng';
        }

        $methods = array_merge($methods, ['searchDuckDuckGoHtml', 'searchDuckDuckGoGet', 'searchViaJinaProxy']);

        if ($backend === 'searxng' && ! in_array('searchSearXng', $methods, true)) {
            $methods = ['searchSearXng', ...$methods];
        }

        foreach (array_unique($methods) as $method) {
            try {
                $hits = $this->{$method}($query, $max);
                if ($hits) {
                    return $hits;
                }
                $errors[] = "{$method}: no results";
            } catch (\Throwable $e) {
                $errors[] = "{$method}: ".$e->getMessage();
                Log::debug("Search {$method} failed for [{$query}]: ".$e->getMessage());
            }
        }

        throw new RuntimeException(
            'No web search results for `'.$query.'`. Details: '.implode('; ', array_slice($errors, -4))
        );
    }

    public function searchSearXng(string $query, int $max): array
    {
        $baseUrl = rtrim((string) config('market.searxng_url'), '/');
        if ($baseUrl === '') {
            throw new RuntimeException('SEARXNG_URL not configured');
        }

        $response = Http::timeout(25)
            ->withHeaders(['User-Agent' => self::USER_AGENT])
            ->get("{$baseUrl}/search", [
                'q' => $query,
                'format' => 'json',
                'categories' => 'general',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('SearXNG HTTP '.$response->status());
        }

        return $this->normalizeHits(
            collect($response->json('results', []))
                ->map(fn ($r) => [
                    'title' => $r['title'] ?? '',
                    'snippet' => $r['content'] ?? ($r['snippet'] ?? ''),
                    'url' => $r['url'] ?? '',
                    'source' => 'searxng',
                ])
                ->all(),
            $max
        );
    }

    public function searchTavily(string $query, int $max): array
    {
        $response = Http::timeout(30)->post('https://api.tavily.com/search', [
            'api_key' => config('market.tavily_api_key'),
            'query' => $query,
            'max_results' => $max,
            'search_depth' => 'basic',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Tavily HTTP '.$response->status());
        }

        return $this->normalizeHits(
            collect($response->json('results', []))
                ->map(fn ($r) => [
                    'title' => $r['title'] ?? '',
                    'snippet' => $r['content'] ?? '',
                    'url' => $r['url'] ?? '',
                    'source' => 'tavily',
                ])
                ->all(),
            $max
        );
    }

    public function searchSerpApi(string $query, int $max): array
    {
        $response = Http::timeout(30)->get('https://serpapi.com/search', [
            'api_key' => config('market.serpapi_key'),
            'engine' => 'google',
            'q' => $query,
            'num' => $max,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('SerpAPI HTTP '.$response->status());
        }

        return $this->normalizeHits(
            collect($response->json('organic_results', []))
                ->map(fn ($r) => [
                    'title' => $r['title'] ?? '',
                    'snippet' => $r['snippet'] ?? '',
                    'url' => $r['link'] ?? '',
                    'source' => 'serpapi',
                ])
                ->all(),
            $max
        );
    }

    public function searchDuckDuckGoHtml(string $query, int $max): array
    {
        $response = Http::timeout(25)
            ->withHeaders(['User-Agent' => self::USER_AGENT])
            ->asForm()
            ->post('https://html.duckduckgo.com/html/', [
                'q' => $query,
                'b' => '',
                'l' => 'us-en',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('DDG HTML POST HTTP '.$response->status());
        }

        return $this->parseDuckDuckGoHtml($response->body(), $max);
    }

    public function searchDuckDuckGoGet(string $query, int $max): array
    {
        $response = Http::timeout(25)
            ->withHeaders(['User-Agent' => self::USER_AGENT])
            ->get('https://html.duckduckgo.com/html/', ['q' => $query]);

        if (! $response->successful()) {
            throw new RuntimeException('DDG HTML GET HTTP '.$response->status());
        }

        return $this->parseDuckDuckGoHtml($response->body(), $max);
    }

    /**
     * Jina AI reader proxy — works when direct DDG is blocked or times out.
     */
    public function searchViaJinaProxy(string $query, int $max): array
    {
        $target = 'http://html.duckduckgo.com/html/?q='.urlencode($query);
        $response = Http::timeout(35)
            ->withHeaders(['Accept' => 'text/markdown'])
            ->get('https://r.jina.ai/'.rawurlencode($target));

        if (! $response->successful()) {
            throw new RuntimeException('Jina proxy HTTP '.$response->status());
        }

        return $this->parseJinaMarkdown($response->body(), $max);
    }

    private function parseDuckDuckGoHtml(string $html, int $max): array
    {
        $hits = [];

        if (preg_match_all(
            '/class="result__a"[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>(?:.*?class="result__snippet"[^>]*>(.*?)<\/a>)?/s',
            $html,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $m) {
                $url = $this->decodeDuckDuckGoUrl(html_entity_decode($m[1]));
                $title = trim(strip_tags($m[2]));
                $snippet = isset($m[3]) ? trim(strip_tags($m[3])) : '';
                if (! $title || str_contains($url, 'duckduckgo.com/y.js')) {
                    continue;
                }
                $hits[] = [
                    'title' => $title,
                    'snippet' => $snippet,
                    'url' => $url,
                    'source' => 'duckduckgo-html',
                ];
                if (count($hits) >= $max) {
                    break;
                }
            }
        }

        // Newer DDG layout fallback
        if (empty($hits) && preg_match_all(
            '/<a[^>]+class="[^"]*result__url[^"]*"[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/s',
            $html,
            $alt,
            PREG_SET_ORDER
        )) {
            foreach ($alt as $m) {
                $url = $this->decodeDuckDuckGoUrl(html_entity_decode($m[1]));
                $title = trim(strip_tags($m[2]));
                if ($title && $url) {
                    $hits[] = ['title' => $title, 'snippet' => '', 'url' => $url, 'source' => 'duckduckgo-html'];
                }
                if (count($hits) >= $max) {
                    break;
                }
            }
        }

        if (empty($hits)) {
            throw new RuntimeException('DDG HTML parse: no results');
        }

        return $hits;
    }

    private function parseJinaMarkdown(string $markdown, int $max): array
    {
        $hits = [];
        if (! preg_match_all('/## \[([^\]]+)\]\(([^)]+)\)/', $markdown, $headers, PREG_SET_ORDER)) {
            throw new RuntimeException('Jina: no result headers');
        }

        foreach ($headers as $h) {
            $title = trim($h[1]);
            $url = $this->decodeDuckDuckGoUrl($h[2]);
            if (! $title || str_contains($url, 'duckduckgo.com/html')) {
                continue;
            }
            $snippet = '';
            if (preg_match(
                '/## \['.preg_quote($title, '/').'\]\([^)]+\)\s*\n+(?:[^\n]+\n+)*?\[([^\]]{10,})\]\([^)]+\)/s',
                $markdown,
                $sn
            )) {
                $snippet = trim($sn[1]);
            }
            $hits[] = [
                'title' => $title,
                'snippet' => $snippet,
                'url' => $url,
                'source' => 'jina-ddg',
            ];
            if (count($hits) >= $max) {
                break;
            }
        }

        if (empty($hits)) {
            throw new RuntimeException('Jina: parsed zero usable hits');
        }

        return $hits;
    }

    private function decodeDuckDuckGoUrl(string $url): string
    {
        if (preg_match('/[?&]uddg=([^&]+)/', $url, $m)) {
            return urldecode($m[1]);
        }

        return $url;
    }

    private function normalizeHits(array $hits, int $max): array
    {
        return array_values(array_filter(
            array_slice($hits, 0, $max),
            fn ($h) => ! empty($h['title']) || ! empty($h['snippet'])
        ));
    }

    /**
     * Fetch readable page text via Jina reader (for blog/listicle product extraction).
     */
    public function fetchPageMarkdown(string $url, int $maxChars = 6000): string
    {
        $response = Http::timeout(35)
            ->withHeaders(['Accept' => 'text/markdown'])
            ->get('https://r.jina.ai/'.rawurlencode($url));

        if (! $response->successful()) {
            throw new RuntimeException('Page fetch HTTP '.$response->status());
        }

        $text = trim($response->body());

        return mb_substr($text, 0, $maxChars);
    }
}
